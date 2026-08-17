<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The rhythm rule, applied to every page at once — see DESIGN_SYSTEM.md §7b.
 *
 * Two navy sections directly against each other do not read as two sections.
 * They read as one very tall field with a line in it, and whatever is in the
 * second one is absorbed into the first. On /impact that cost the page its
 * argument: the figures sat in a navy band under the navy hero, so evidence
 * for a claim looked like part of the header.
 *
 * This is a sweep rather than a fix for one page, because the sequence is easy
 * to reintroduce and impossible to see in a diff.
 */
class NavyNeverTouchesNavyTest extends TestCase
{
    use RefreshDatabase;

    /** Every page built so far, in both locales where it matters. */
    private const PAGES = [
        '/ar',
        '/ar/impact',
        '/ar/about',
        '/ar/contact',
        '/ar/training',
        '/ar/solutions/government',
        '/ar/solutions/companies',
        '/ar/solutions/partners',
        '/ar/solutions/artisans',
    ];

    /**
     * Markers for a section that paints itself navy.
     *
     * `on-dark` is the class every navy block carries — it is what switches the
     * text to the inverse palette, so it is the most reliable signal that a
     * block is dark, and it cannot be forgotten without the text breaking too.
     */
    private const DARK = 'on-dark';

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    #[Test]
    public function no_page_places_two_navy_sections_back_to_back(): void
    {
        foreach (self::PAGES as $url) {
            $this->assertNoDarkAdjacency($url);
        }
    }

    /**
     * Walks the top-level blocks of `<main>` and fails on two dark ones in a
     * row.
     *
     * Header and footer are excluded: both are navy by design and both sit
     * outside the page's own rhythm — the footer's showroom block is the site's
     * closing note and the rule's own text exempts it.
     */
    private function assertNoDarkAdjacency(string $url): void
    {
        $body = $this->get($url)->assertOk()->getContent();

        $main = preg_match('/<main[^>]*>([\s\S]*?)<\/main>/', $body, $m) === 1 ? $m[1] : $body;
        $main = preg_replace('/<footer[\s\S]*?<\/footer>/', '', $main) ?? $main;

        /*
         * Only the opening tags of top-level sections, in document order. A
         * nested `on-dark` — the CTA band's navy card inside its light outer
         * section — is not a section-level adjacency and must not trip this.
         */
        preg_match_all('/<(?:section|div|article)\b[^>]*class="([^"]*)"/', $main, $matches);

        $wasDark = false;

        foreach ($matches[1] as $classes) {
            // Only blocks that declare themselves a section of the page.
            if (! str_contains($classes, 'section') && ! str_contains($classes, 'phero')
                && ! str_contains($classes, 'shero') && ! str_contains($classes, 'impact--')) {
                continue;
            }

            $isDark = str_contains($classes, self::DARK);

            $this->assertFalse($isDark && $wasDark,
                "{$url} places two navy sections back to back — see DESIGN_SYSTEM.md §7b.");

            $wasDark = $isDark;
        }
    }
}
