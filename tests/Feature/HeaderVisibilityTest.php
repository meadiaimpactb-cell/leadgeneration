<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The header must be legible on every page.
 *
 * Regression guard for a real bug: the header floats transparent with white
 * text over the home page's navy hero. Every other page starts on a white
 * background, so with no background of its own the header rendered
 * white-on-white — logo and navigation invisible. Clicking through the nav
 * looked like the site had lost its header entirely.
 *
 * The first fix made `overHero` an opt-in defaulting to false. The bug came
 * back anyway, because the header also went transparent whenever the scroll
 * state and the paint state disagreed. The current fix removes the state
 * entirely: the bar always paints navy and always sets white on it. These
 * tests hold both the old default and the new unconditional ground in place.
 *
 * Why source assertions rather than HTTP ones: the header is Vue, and the
 * feature-test process does not run the SSR server, so a `$this->get(...)`
 * here returns the Inertia shell with no header markup in it. Asserting on
 * that HTML would pass whether or not the bug exists.
 */
class HeaderVisibilityTest extends TestCase
{
    private function source(string $relative): string
    {
        $path = base_path("resources/js/{$relative}");

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[Test]
    public function the_public_layout_defaults_to_a_solid_header(): void
    {
        $source = $this->source('Layouts/PublicLayout.vue');

        // A new page must inherit a legible header without knowing this rule
        // exists.
        $this->assertMatchesRegularExpression(
            '/overHero:\s*\{[^}]*default:\s*false/s',
            $source,
            'PublicLayout must default overHero to false, or a new page will ship with an invisible header.'
        );
    }

    #[Test]
    public function the_header_has_no_transparent_state_at_all(): void
    {
        $source = $this->source('Components/sections/SiteHeader.vue');

        $this->assertMatchesRegularExpression(
            '/overHero:\s*\{[^}]*default:\s*false/s',
            $source,
            'SiteHeader must default overHero to false.'
        );

        // The bar paints its own navy unconditionally. This is stronger than
        // the rule it replaces: there is no longer a transparent state to get
        // wrong, so no combination of scroll position, hydration timing or
        // page template can render white text on a light ground.
        $this->assertMatchesRegularExpression(
            '/\.header\s*\{[^}]*background:\s*var\(--navy-900\)/s',
            $source,
            'The header must paint an unconditional navy ground.'
        );

        // No scroll-derived paint. The invisible-header bug was exactly this:
        // a colour that depended on a value the first paint did not have.
        $this->assertStringNotContainsString(
            'scrolled',
            $source,
            'The header must not derive its colour from the scroll position.'
        );
    }

    #[Test]
    public function only_the_home_page_opts_into_a_transparent_header(): void
    {
        $pages = glob(base_path('resources/js/Pages/Public/*.vue')) ?: [];

        $this->assertNotEmpty($pages);

        $optedIn = [];

        foreach ($pages as $page) {
            $source = (string) file_get_contents($page);

            // Matches `over-hero`, `:over-hero="true"` and `overHero`.
            if (preg_match('/\bover-?[hH]ero\b/', $source) === 1) {
                $optedIn[] = basename($page);
            }
        }

        $this->assertSame(
            ['Home.vue'],
            $optedIn,
            'Only the home page has a hero the header may float over. '
            .'Any other page doing this renders a white header on a white background.'
        );
    }

    #[Test]
    public function the_logo_can_render_in_both_tones(): void
    {
        $source = $this->source('Components/ui/Logo.vue');

        // The logo is a currentColor mask, so the header's tone switch only
        // works while both treatments exist (§23).
        $this->assertStringContainsString('navy:', $source);
        $this->assertStringContainsString('white:', $source);
    }
}
