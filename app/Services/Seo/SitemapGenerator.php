<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Campaign;
use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\Report;
use App\Models\Sector;
use App\Models\ShowcaseProduct;
use App\Models\Solution;
use App\Models\Story;
use App\Models\TrainingProgram;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Builds the sitemap index and the per-language sitemaps (§13).
 *
 * Three rules govern what may appear, and all three are about not lying to a
 * crawler:
 *
 *   1. Only what a visitor can actually reach. A draft, a deactivated record
 *      or a page the client marked non-indexable is absent — a sitemap entry
 *      that answers 404 or carries `noindex` costs crawl budget and is read
 *      as a quality signal against the whole site.
 *   2. Only in locales the record genuinely exists in. §12 forbids serving
 *      Arabic to an English visitor, so listing an untranslated record in the
 *      English sitemap would invite the crawler to index exactly that.
 *   3. Reciprocal hreflang on every entry, listing only the locales that
 *      passed rule 2 — the same set the page's own <head> declares.
 *
 * Nothing here is configured in code. What is listed follows the publish and
 * active flags the client sets in the admin panel, so the sitemap is a
 * consequence of their editing rather than a separate thing to maintain.
 */
class SitemapGenerator
{
    /**
     * Locales that get a sitemap of their own.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return Locales::enabled();
    }

    /** The index that points at one sitemap per language. */
    public function index(): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($this->locales() as $locale) {
            $xml[] = '  <sitemap>';
            $xml[] = '    <loc>'.e(url("sitemap-{$locale}.xml")).'</loc>';
            $xml[] = '    <lastmod>'.$this->lastModified()->toAtomString().'</lastmod>';
            $xml[] = '  </sitemap>';
        }

        $xml[] = '</sitemapindex>';

        return implode("\n", $xml)."\n";
    }

    /**
     * One language's URL set.
     *
     * Built fresh on every request, deliberately. It was cached under a
     * fingerprint of the newest `updated_at`, which looks airtight and is not:
     * that column has one-second resolution, so two edits inside the same
     * second share a fingerprint and the second one is served the first one's
     * XML. In a test that reads as flakiness; on a live site it means an
     * editor unpublishes a page, reloads the sitemap, and still sees it.
     *
     * The build is half a dozen indexed queries over a few dozen rows, and a
     * sitemap is fetched by crawlers a handful of times a day — not by
     * visitors. There is no performance here worth trading correctness for.
     */
    public function forLocale(string $locale): string
    {
        return $this->build($locale);
    }

    /**
     * The same URL set the XML lists, as data.
     *
     * For the panel, so what an operator is shown and what a crawler is served
     * are produced by one method rather than two that can drift. A screen that
     * reported a different list from the file would be worse than no screen.
     *
     * @return list<array{loc: string, lastmod: string|null, changefreq: string, priority: string, alternates: array<string, string>}>
     */
    public function urlsFor(string $locale): array
    {
        return $this->entries($locale);
    }

    /** The newest change across everything listed, for the panel to show. */
    public function lastModifiedAt(): Carbon
    {
        return $this->lastModified();
    }

    private function build(string $locale): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach ($this->entries($locale) as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($entry['loc']).'</loc>';

            if ($entry['lastmod'] !== null) {
                $xml[] = '    <lastmod>'.$entry['lastmod'].'</lastmod>';
            }

            $xml[] = '    <changefreq>'.$entry['changefreq'].'</changefreq>';
            $xml[] = '    <priority>'.$entry['priority'].'</priority>';

            foreach ($entry['alternates'] as $hreflang => $href) {
                $xml[] = '    <xhtml:link rel="alternate" hreflang="'.e($hreflang)
                    .'" href="'.e($href).'"/>';
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml)."\n";
    }

    /**
     * @return list<array{loc: string, lastmod: string|null, changefreq: string, priority: string, alternates: array<string, string>}>
     */
    private function entries(string $locale): array
    {
        $entries = [];

        // ---- Managed pages -------------------------------------------------
        // is_indexable is the client's switch. The legal pages ship with it
        // off: they carry no ranking value and would dilute crawl budget
        // across two languages (§13).
        $pages = Page::query()
            ->published()
            ->where('is_indexable', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        foreach ($pages as $page) {
            if (! $page->hasTranslation($locale)) {
                continue;
            }

            /*
             * Rule 1 again, and the one case that slipped past it.
             *
             * Public pages are served by literal routes — /about, /contact,
             * /legal/{slug} — not by a `/{locale}/{slug}` catch-all. A page
             * created in the panel with any other slug is published, indexable
             * and translated, so it passed every check above and was listed
             * here while its URL answered 404. Verified: a new page appeared in
             * the sitemap and its address returned 404 on the same request.
             *
             * No existing page is affected — all eight resolve — so this is a
             * guard for the day somebody adds one, not a change to what is
             * served today.
             */
            if (! $this->reachable($page->publicUrl($locale))) {
                continue;
            }

            $entries[] = $this->entry(
                $page->publicUrl($locale),
                $page->updated_at,
                $page->slug === 'home' ? '1.0' : '0.8',
                $page->slug === 'home' ? 'weekly' : 'monthly',
                $this->alternates($page, fn (string $l): string => $page->publicUrl($l)),
            );
        }

        // ---- Solutions -----------------------------------------------------
        foreach ($this->visible(Solution::class, $locale) as $solution) {
            $entries[] = $this->entry(
                url("{$locale}/solutions/{$solution->slug}"),
                $solution->updated_at,
                '0.7',
                'monthly',
                $this->alternates($solution, fn (string $l): string => url("{$l}/solutions/{$solution->slug}")),
            );
        }

        // ---- Sectors -------------------------------------------------------
        foreach ($this->visible(Sector::class, $locale) as $sector) {
            $entries[] = $this->entry(
                // Under /solutions since the segments moved there. The old
                // /sectors/* paths 301 to these, so nothing is orphaned.
                url("{$locale}/solutions/{$sector->slug}"),
                $sector->updated_at,
                '0.7',
                'monthly',
                $this->alternates($sector, fn (string $l): string => url("{$l}/solutions/{$sector->slug}")),
            );
        }

        // ---- Product categories --------------------------------------------
        // The showcase has no per-product page, so the category filters are the
        // catalogue's indexable surface. Each carries its own canonical and its
        // own title, which is what makes it worth listing at all.
        //
        // Empty categories are excluded because ProductController answers 404
        // for one — it refuses to serve a filter that leads nowhere, rather
        // than 200 with the whole catalogue. The store publishes a category
        // with no products in it, so this is not hypothetical: without the
        // filter the sitemap advertised a URL that already returned 404.
        $categories = ProductCategory::query()
            ->visible()
            ->translatedIn($locale)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->with('translations')
            ->get()
            ->filter(fn (ProductCategory $c): bool => $c->products_count > 0);

        foreach ($categories as $category) {
            $url = fn (string $l): string => url("{$l}/products").'?category='.urlencode($category->slug);

            $entries[] = $this->entry(
                $url($locale),
                $category->updated_at,
                '0.5',
                'weekly',
                $this->alternates($category, $url),
            );
        }

        // ---- Artisan stories -----------------------------------------------
        // Only those with a body written in this locale.
        //
        // Every visible story now answers at /impact/stories/{slug}, but one
        // with no body is a headline and a pull quote — a thin page, and
        // listing it spends crawl budget to publish something a reader gains
        // nothing from. The same condition governs the «اقرأوا القصة كاملة»
        // button, so the sitemap and the site agree on what a full story is.
        foreach ($this->visible(Story::class, $locale) as $story) {
            if (blank($story->translations->firstWhere('locale', $locale)?->body)) {
                continue;
            }

            $url = fn (string $l): string => url("{$l}/impact/stories/{$story->slug}");

            $entries[] = $this->entry(
                $url($locale),
                $story->updated_at,
                '0.5',
                'monthly',
                $this->alternates($story, $url),
            );
        }

        // ---- Records with no page of their own -----------------------------
        // Reports, programs and products are rendered inside the listing pages
        // above, so they contribute a lastmod rather than a URL. Listing a URL
        // that does not exist would be the same defect as listing a draft.

        // ---- Live campaigns ------------------------------------------------
        foreach (Campaign::query()->live()->with('translations')->get() as $campaign) {
            if (! $campaign->hasTranslation($locale)) {
                continue;
            }

            $entries[] = $this->entry(
                url("{$locale}/c/{$campaign->slug}"),
                $campaign->updated_at,
                '0.6',
                'weekly',
                $this->alternates($campaign, fn (string $l): string => url("{$l}/c/{$campaign->slug}")),
            );
        }

        return $entries;
    }

    /**
     * Whether a URL on this site matches a route at all.
     *
     * Asked of the router rather than by fetching the page: a sub-request per
     * entry would be slow and would run middleware for no reason. Matching is
     * the same test the framework itself applies before deciding on a 404.
     */
    private function reachable(string $url): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($url, 'GET'));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Active, translated records of one type, ordered as the panel ordered
     * them.
     *
     * @param  class-string<Model>  $model
     * @return Collection<int, Model>
     */
    private function visible(string $model, string $locale)
    {
        return $model::query()
            ->visible()
            ->translatedIn($locale)
            ->with('translations')
            ->get();
    }

    /**
     * @param  callable(string): string  $url
     * @return array<string, string>
     */
    private function alternates(Model $record, callable $url): array
    {
        $available = array_values(array_intersect(
            $this->locales(),
            $record->translatedLocales(),
        ));

        if (count($available) < 2) {
            return [];
        }

        $alternates = [];

        foreach ($available as $locale) {
            $alternates[Locales::all()[$locale]['hreflang']] = $url($locale);
        }

        $default = config('site.x_default_locale');

        if (in_array($default, $available, true)) {
            $alternates['x-default'] = $url($default);
        }

        return $alternates;
    }

    /**
     * @param  array<string, string>  $alternates
     * @return array{loc: string, lastmod: string|null, changefreq: string, priority: string, alternates: array<string, string>}
     */
    private function entry(
        string $loc,
        ?Carbon $lastmod,
        string $priority,
        string $changefreq,
        array $alternates,
    ): array {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod?->toAtomString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
            'alternates' => $alternates,
        ];
    }

    /**
     * The newest change across everything the sitemap lists.
     *
     * Reported as <lastmod> on the index, so a crawler can tell at a glance
     * whether anything has changed since it last fetched.
     */
    private function lastModified(): Carbon
    {
        $stamps = [];

        foreach ([Page::class, Solution::class, Sector::class, ProductCategory::class,
            ShowcaseProduct::class, Story::class, Report::class,
            TrainingProgram::class, Campaign::class] as $model) {
            $stamps[] = $model::query()->max('updated_at');
        }

        $latest = collect($stamps)->filter()->max();

        return $latest ? Carbon::parse($latest) : now();
    }
}
