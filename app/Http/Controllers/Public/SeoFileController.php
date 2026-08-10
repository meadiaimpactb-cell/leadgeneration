<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\RobotsBuilder;
use App\Services\Seo\SitemapGenerator;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The two files crawlers ask for before anything else (§5, §13).
 *
 * Served as routes rather than as static files in public/, because both are
 * built from content the client edits: publishing a page has to change the
 * sitemap, and robots.txt is a settings field. A file on disk would need a
 * regeneration step someone eventually forgets to run.
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

    private function xml(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
