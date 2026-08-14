<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Support\Brand;
use App\Support\Locales;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * Builds the per-page SEO block handed to the Vue layer (§13).
 *
 * Title and description are always editable per page and per language from
 * the admin panel; this class only supplies the structure and the fallbacks
 * the client configured in `settings` — it never invents copy (§22.1).
 */
class MetaBuilder
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides  title, description, image, canonical,
     *                                           plus optional `breadcrumbs` and `page`
     *                                           used only to build structured data
     * @param  list<string>|null  $availableLocales  locales this page really exists in
     * @return array<string, mixed>
     */
    public function build(array $overrides = [], ?array $availableLocales = null): array
    {
        $locale = app()->getLocale();
        $siteName = $this->settings->get("site.name.{$locale}")
            ?? $this->settings->get('site.name')
            ?? config('app.name');

        $title = $overrides['title'] ?? null;
        $description = $overrides['description']
            ?? $this->settings->get("seo.default_description.{$locale}");

        return [
            'title' => $title,
            'siteName' => $siteName,
            // "Page — Site", or just the site name on the home page.
            'fullTitle' => $title !== null && $title !== '' ? "{$title} — {$siteName}" : $siteName,
            'description' => $description,
            // Target keywords for this page, per §13's SEO input. Emitted
            // because Bing and Yandex still read the tag weakly; the value
            // that matters is the panel's check that these words reached the
            // title and description, which is where ranking is actually won.
            'keywords' => $overrides['keywords'] ?? null,
            // og:image is one of the few places that genuinely needs an
            // absolute URL — a social crawler has no page to resolve a
            // relative path against.
            // Order: this page's own image, then the SEO setting, then the
            // share image uploaded on the brand screen. Without the last one
            // that upload had nowhere to appear.
            'image' => $this->absolute(
                $overrides['image']
                    ?? $this->settings->get('seo.default_og_image')
                    ?? app(Brand::class)->url('og_image')
            ),
            'canonical' => $overrides['canonical'] ?? $this->request->url(),
            'robots' => $overrides['robots'] ?? null,
            'locale' => $locale,
            'dir' => Locales::dir($locale),
            'alternates' => $this->alternates($availableLocales),
            /*
             * Structured data (§13), built here so every page that already
             * asks for its meta block gets it without a second call site to
             * remember. What it contains depends on what the caller passed —
             * a breadcrumb trail and the page itself are optional context, and
             * the organisation is always present.
             */
            'schema' => app(SchemaBuilder::class)->build([
                'breadcrumbs' => $overrides['breadcrumbs'] ?? [],
                'owner' => $overrides['owner'] ?? null,
            ]),
        ];
    }

    /**
     * Resolve a possibly-relative path against the host actually serving the
     * request, rather than against APP_URL.
     */
    private function absolute(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
    }

    /**
     * Reciprocal hreflang plus x-default (§13).
     *
     * A locale is listed only if the page genuinely exists in it — §12
     * forbids pointing hreflang at a page that would fall back to Arabic.
     *
     * @param  list<string>|null  $availableLocales
     * @return list<array<string, string>>
     */
    private function alternates(?array $availableLocales): array
    {
        $available = $availableLocales ?? Locales::enabled();
        $locales = array_values(array_intersect(Locales::enabled(), $available));

        if (count($locales) < 2) {
            return [];
        }

        $segments = explode('/', trim($this->request->path(), '/'));
        $rest = array_key_exists($segments[0] ?? '', Locales::all())
            ? array_slice($segments, 1)
            : $segments;

        $alternates = [];

        foreach ($locales as $locale) {
            $alternates[] = [
                'hreflang' => Locales::all()[$locale]['hreflang'],
                'href' => url(trim($locale.'/'.implode('/', $rest), '/')),
            ];
        }

        $xDefault = config('site.x_default_locale');

        if (in_array($xDefault, $locales, true)) {
            $alternates[] = [
                'hreflang' => 'x-default',
                'href' => url(trim($xDefault.'/'.implode('/', $rest), '/')),
            ];
        }

        return $alternates;
    }
}
