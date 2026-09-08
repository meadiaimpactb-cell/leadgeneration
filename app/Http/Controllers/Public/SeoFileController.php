<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\RobotsBuilder;
use App\Services\Seo\SitemapGenerator;
use App\Support\Locales;
use App\Support\Palette;
use App\Support\Settings;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The files a crawler or a browser asks for before anything else (§5, §13).
 *
 * Served as routes rather than as static files in public/, because all three
 * are built from things the client edits: publishing a page has to change the
 * sitemap, robots.txt is a settings field, and the web manifest carries the
 * site's name and its two brand colours. A file on disk would need a
 * regeneration step someone eventually forgets to run — which is exactly what
 * public/site.webmanifest was, still holding the identity's navy after the
 * colours became editable, and still naming the site whatever it was called
 * the day the file was written.
 */
class SeoFileController extends Controller
{
    public function __construct(
        private readonly SitemapGenerator $sitemap,
        private readonly RobotsBuilder $robots,
    ) {}

    public function index(): Response
    {
        return $this->xml($this->sitemap->index());
    }

    public function locale(string $locale): Response
    {
        // A sitemap for a language the site does not serve is a 404, not an
        // empty file: while §12's English switch is off, /sitemap-en.xml must
        // not exist at all.
        if (! in_array($locale, $this->sitemap->locales(), true)) {
            throw new NotFoundHttpException;
        }

        return $this->xml($this->sitemap->forLocale($locale));
    }

    public function robots(): Response
    {
        return response($this->robots->build(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * The web manifest — what an installed shortcut is called and coloured.
     *
     * Both colours are the dominant one: it is the splash screen behind the
     * icon and the chrome around the window, and the identity's answer to
     * "what colour is this company" is the same in both places.
     *
     * The name comes from the settings rows the footer and the <title> already
     * use, so a site that renames itself renames its shortcut too. Nothing is
     * invented here (§22.1): with neither row filled the key is left out, and
     * the browser falls back to the page title.
     */
    public function manifest(): Response
    {
        $settings = app(Settings::class);
        $navy = app(Palette::class)->themeColor();

        $names = array_values(array_filter([
            $settings->get('site.name.ar'),
            $settings->get('site.name.en'),
        ]));

        // The site's own language, not the request's: a manifest is fetched
        // once and describes the installed shortcut, not the page in front of
        // whoever happened to trigger the fetch.
        $locale = (string) config('site.default_locale');

        $manifest = array_filter([
            // Both languages where both are written — an installed shortcut is
            // named once, and this is a bilingual company.
            'name' => implode(' — ', $names) ?: null,
            'short_name' => $settings->get("site.name.{$locale}") ?: ($names[0] ?? null),
            'lang' => $locale,
            'dir' => Locales::dir($locale),
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => $navy,
            'theme_color' => $navy,
            'icons' => [
                ['src' => '/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ]);

        return response(
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ],
        );
    }

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
