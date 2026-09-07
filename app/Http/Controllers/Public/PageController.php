<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\PartnerResource;
use App\Models\ImpactMetric;
use App\Models\Page;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * About, and the legal pages.
 */
class PageController extends PublicController
{
    /** @var Collection<int, ImpactMetric>|null */
    private ?Collection $metrics = null;

    /**
     * /about — the story, the model, and the evidence that we are a serious
     * organisation to put a name beside (§5).
     *
     * It has its own Inertia page rather than the generic `Public/Page`
     * because it carries two datasets a composed page cannot: the impact
     * figures and the accreditation strip. Both are read here from the same
     * records the home page and /impact read — one row, three pages, guarded
     * by `ImpactFiguresHaveOneSourceTest`. A figure copied into a second
     * place disagrees with the first the day someone edits it, and by then a
     * public body has already published it.
     */
    public function about(string $locale): Response
    {
        $page = $this->page('about');

        if ($page === null || ! $page->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        $previewing = $this->isPreviewing($page);

        return Inertia::render('Public/About', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'impact' => ImpactMetricResource::collection($this->metrics($locale)),

            // Derived from when the figures were last edited, not a field
            // maintained beside them (§13). Same derivation as /impact.
            'measuredAt' => $this->metrics($locale)->max('updated_at')
                ?->locale($locale)->translatedFormat('F Y'),

            // The same strip the home page shows, from the same query:
            // partners and accreditations together. On the page whose job is
            // trust, an absent accreditation row is a gap.
            'partners' => PartnerResource::collection(
                Partner::query()->visible()->translatedIn($locale)
                    ->whereIn('type', [Partner::TYPE_PARTNER, Partner::TYPE_ACCREDITATION])
                    ->withTranslation()->with('media')->get()
            ),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $page->t('meta_title') ?: $page->t('title'),
                'description' => $page->t('meta_description'),
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ], $page->translatedLocales()),
        ]);
    }

    /**
     * Memoised: the figures are read twice in one request — for the strip and
     * for its measurement date — and `preventLazyLoading` would not catch a
     * second query, because each one is eager and correct on its own.
     *
     * @return Collection<int, ImpactMetric>
     */
    private function metrics(string $locale): Collection
    {
        return $this->metrics ??= ImpactMetric::query()
            ->visible()->global()->translatedIn($locale)
            ->withTranslation()->get();
    }

    public function legal(string $locale, string $slug): Response
    {
        return $this->render("legal/{$slug}", $locale, isLegal: true);
    }

    /**
     * Slugs the catch-all must never answer.
     *
     * `landing` and `home` are the ones that matter. Each is a `pages` row
     * like any other whose `publicUrl()` is `/{locale}` — so without this,
     * `/ar/landing` would render the same sections at a second address and
     * split the ranking of the page that matters most (§13). That is not
     * hypothetical: adding the landing page opened exactly that hole, and
     * `/ar/landing` answered 200 with the whole site on it until this line
     * named it. The rest are structural prefixes:
     * unreachable through this route today, because the `{locale}` group is
     * constrained to the configured locales, but named here so that stays true
     * if a locale is ever added.
     */
    private const RESERVED = [...Page::ROOT_SLUGS, 'admin', 'api', 'build', 'storage', 'vendor'];

    /** Slugs this route must not answer, given the current configuration. */
    private function reserved(): array
    {
        return [...self::RESERVED, ...Page::retiredSlugs()];
    }

    /**
     * Any page the client created in the panel (§9.1).
     *
     * It renders through the generic `Public/Page` composition — the same one
     * the legal pages use — whatever `template` the row carries. An unknown
     * template name is not an error: the template is a label the panel stores,
     * and a page is its ordered sections either way. A page needing datasets a
     * composition cannot carry earns a literal route above, as /about did.
     */
    public function show(string $locale, string $slug): Response
    {
        if (in_array($slug, $this->reserved(), true)) {
            throw new NotFoundHttpException;
        }

        return $this->render($slug, $locale);
    }

    private function render(string $slug, string $locale, bool $isLegal = false): Response
    {
        $page = $this->page($slug);

        if ($page === null || ! $page->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        $previewing = $this->isPreviewing($page);

        return Inertia::render('Public/Page', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
                'template' => $page->template,
                'narrow' => $isLegal,
            ],
            'sections' => $this->sections($page),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $page->t('meta_title') ?: $page->t('title'),
                'description' => $page->t('meta_description'),
                // Legal pages carry no ranking value and dilute the crawl
                // budget across two locales (§13).
                'robots' => $previewing ? 'noindex, nofollow' : ($isLegal ? 'noindex, follow' : null),
            ], $page->translatedLocales()),
        ]);
    }
}
