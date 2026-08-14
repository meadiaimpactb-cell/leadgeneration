<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the site currently offers a search engine, and how to hand it over.
 *
 * The file itself has always worked and needs no screen to function — it is
 * built fresh on every request from the publish flags in the panel. What was
 * missing is the answer to the two questions an operator actually has: "is my
 * new page in there?" and "what do I give Google?".
 *
 * So this screen reads the very same method the XML is built from rather than
 * recounting anything. A panel that showed a different list from the file
 * would be worse than no panel at all.
 */
class SitemapController extends Controller
{
    /** The same permission that governs the other SEO screens. */
    private const PERMISSION = 'pages.view';

    public function index(Request $request, SitemapGenerator $sitemap): Response
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $locales = [];

        foreach ($sitemap->locales() as $locale) {
            $urls = $sitemap->urlsFor($locale);

            $locales[] = [
                'locale' => $locale,
                'url' => url("sitemap-{$locale}.xml"),
                'count' => count($urls),
                'urls' => array_map(fn (array $entry): array => [
                    'loc' => $entry['loc'],
                    'lastmod' => $entry['lastmod'],
                    // Shown as a share rather than a raw 0.8: the number is
                    // a hint to a crawler about relative importance, and
                    // nobody outside SEO reads it as anything else.
                    'priority' => $entry['priority'],
                ], $urls),
            ];
        }

        return Inertia::render('Admin/Seo/Sitemap', [
            'indexUrl' => url('sitemap.xml'),
            'robotsUrl' => url('robots.txt'),
            'lastModified' => $sitemap->lastModifiedAt()->toIso8601String(),
            'locales' => $locales,
            /*
             * Outside production robots.txt closes the whole site (§16), so
             * the sitemap is served and then ignored. An operator who hands
             * this URL to Search Console from a staging machine would wait
             * days for an indexing that was never going to happen, and
             * nothing on screen would explain why.
             */
            'indexingOpen' => app()->environment('production'),
        ]);
    }
}
