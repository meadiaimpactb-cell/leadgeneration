<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Support\Settings;

/**
 * Builds robots.txt (§13: "a manageable robots.txt").
 *
 * The client owns the body: `seo.robots_txt` in the admin panel replaces the
 * default rules entirely, so an SEO specialist can write whatever the site
 * needs without a deploy (§13 has one engaged, and their instructions arrive
 * as an input).
 *
 * Two things are NOT left to that field, because getting either wrong is
 * expensive and silent:
 *
 *   · The `Sitemap:` line is always appended. An SEO consultant pasting a
 *     rules block would drop it without noticing, and a sitemap no crawler is
 *     told about is a sitemap that does not exist.
 *   · Outside production the whole file becomes `Disallow: /`. §16 requires
 *     staging to be closed to indexing, and a staging copy of a corporate site
 *     competing with the real one for the same Arabic keywords is the exact
 *     duplicate-content failure §13 forbids. No setting can override this.
 */
class RobotsBuilder
{
    public function __construct(private readonly Settings $settings) {}

    public function build(): string
    {
        if (! app()->environment('production')) {
            return implode("\n", [
                '# Not production. Closed to indexing (§16).',
                'User-agent: *',
                'Disallow: /',
            ])."\n";
        }

        $custom = $this->settings->get('seo.robots_txt');

        $body = is_string($custom) && trim($custom) !== ''
            ? trim($custom)
            : $this->defaults();

        return $body."\n\n".'Sitemap: '.url('sitemap.xml')."\n";
    }

    /**
     * What ships when the client has written nothing.
     *
     * Open to crawlers — the whole point of the site is to be found (§13) —
     * minus the areas that are either private or worthless to index.
     */
    private function defaults(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            '# The admin panel and the lead endpoint are not content.',
            'Disallow: /admin',
            'Disallow: /leads',
            '',
            '# Draft previews reach a page through a secret token. They already',
            '# answer with noindex, but there is no reason to spend crawl',
            '# budget discovering them.',
            'Disallow: /*?preview=',
        ]);
    }
}
