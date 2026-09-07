<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\LandingRedirectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The state production actually runs in (7 September 2026).
 *
 * `.env.testing` keeps `site.legacy_pages` ON so the rest of the suite goes on
 * covering seven controllers and their pages while they exist. That leaves the
 * configuration production DOES use untested — which is the more expensive one
 * to get wrong, because it decides what a crawler and a returning visitor meet
 * at eleven addresses that have been indexed for months.
 *
 * So this file turns the flag off and asserts the retired state directly.
 */
class RetiredPagesLeadToTheLandingPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every retired path, and where a visitor holding that link should land.
     *
     * Not read from the seeder: a test that derives its expectations from the
     * thing it is testing agrees with it by construction. These are typed out
     * so that changing a destination has to be a decision made twice.
     */
    private const MOVES = [
        '/ar/about' => '/ar',
        '/ar/solutions' => '/ar#home',
        '/ar/solutions/government' => '/ar#government',
        '/ar/solutions/partners' => '/ar#partners',
        '/ar/solutions/artisans' => '/ar#artisans',
        '/ar/contact' => '/ar#contact',
        '/ar/products' => '/ar',
        '/ar/impact' => '/ar',
        '/ar/training' => '/ar',
        '/ar/partners' => '/ar#partners',
        '/en/about' => '/en',
        '/en/contact' => '/en#contact',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingRedirectsSeeder::class);
    }

    /**
     * The eleven routes are registered only while the flag is on.
     *
     * Asserted on the route file rather than by booting with the flag off.
     * `routes/web.php` is read once per process during bootstrap, so a test
     * that flips the environment in setUp only works when it is the first
     * file to run — it passed alone and failed in the suite, which is a test
     * that reports the run order rather than the code.
     *
     * The behaviour those routes' absence produces is covered below, on paths
     * that genuinely have no route.
     */
    #[Test]
    public function the_retired_routes_are_registered_only_behind_the_flag(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString(
            "if (config('site.legacy_pages')) {",
            $routes,
            'The retired pages are no longer gated behind the flag.',
        );

        $gate = (int) strpos($routes, "if (config('site.legacy_pages')) {");

        foreach (['/about', '/solutions', '/products', '/impact', '/training', '/partners', '/contact'] as $path) {
            $at = strpos($routes, "Route::get('{$path}'");

            $this->assertIsInt(
                $at,
                "`{$path}` is no longer registered at all; this test needs updating with it.",
            );

            $this->assertGreaterThan(
                $gate,
                $at,
                "`{$path}` is registered before the flag, so turning it off would not retire it.",
            );
        }
    }

    /**
     * And the shipped default is off — a deployment that sets nothing gets
     * the retired site.
     *
     * Read from the config file's source, not by evaluating it: `.env.testing`
     * sets the variable to true for the rest of the suite, so evaluating it
     * here would report the suite's own configuration rather than the default
     * a fresh deployment receives.
     */
    #[Test]
    public function the_pages_are_retired_by_default(): void
    {
        $config = (string) file_get_contents(base_path('config/site.php'));

        $this->assertStringContainsString(
            "env('SITE_LEGACY_PAGES', false)",
            $config,
            'The default must be false, or a deployment silently keeps the eleven pages.',
        );
    }

    /**
     * A path with no route reaches its anchor, in one hop.
     *
     * `/{locale}/sectors/{slug}` has had no route since the segments moved,
     * so it 404s whatever the flag says — which makes it the honest way to
     * prove the mechanism the retirement depends on: a 404 is looked up in
     * the `redirects` table and answered 301. It also covers the chain the
     * retirement created, since these rows used to point at
     * `/solutions/{slug}` and now point straight at the anchor (§13).
     */
    #[Test]
    public function a_path_with_no_route_is_carried_to_its_anchor_in_one_hop(): void
    {
        $moves = [
            '/ar/sectors/artisans' => '/ar#artisans',
            '/ar/sectors/partners' => '/ar#partners',
            '/ar/sectors/government-entities' => '/ar#government',
            '/en/sectors/artisans' => '/en#artisans',
        ];

        foreach ($moves as $from => $to) {
            $this->get($from)->assertRedirect($to);
        }
    }

    /** Every retired path has somewhere to land once its route is gone. */
    #[Test]
    public function every_retired_path_has_a_redirect_waiting_for_it(): void
    {
        foreach (self::MOVES as $from => $to) {
            $this->assertDatabaseHas('redirects', [
                'from_path' => $from,
                'to_path' => $to,
                'status_code' => 301,
                'is_active' => true,
            ]);
        }
    }

    /**
     * The catch-all must not quietly serve them again.
     *
     * `about`, `impact` and the rest are `pages` rows, and `/{locale}/{slug}`
     * takes any single segment — so removing the dedicated routes on its own
     * left every retired page answering 200 at its old address through the
     * generic composition. That is the failure this guards.
     */
    #[Test]
    public function the_catch_all_does_not_resurrect_a_retired_page(): void
    {
        config(['site.legacy_pages' => false]);

        foreach (Page::RETIRED_SLUGS as $slug) {
            $this->assertContains(
                $slug,
                Page::retiredSlugs(),
                "`{$slug}` is not blocked from the catch-all while the flag is off.",
            );
        }

        config(['site.legacy_pages' => true]);

        $this->assertSame([], Page::retiredSlugs(), 'Turning the flag on must restore every page.');
    }

    /** What replaced them still answers, and so does everything not retired. */
    #[Test]
    public function the_landing_page_and_the_pages_that_stayed_still_answer(): void
    {
        $this->get('/ar')->assertOk();
        $this->get('/en')->assertOk();
        $this->get('/ar/legal/privacy')->assertOk();
    }

    /**
     * A sitemap full of 301s is worse than a short one.
     *
     * Every entry must be a URL the site serves at 200 — otherwise the file
     * tells a crawler to visit eleven addresses that all bounce, which is the
     * duplicate-content and redirect-chain defect §13 exists to prevent.
     */
    #[Test]
    public function the_sitemap_advertises_nothing_that_redirects(): void
    {
        $xml = $this->get('/sitemap-ar.xml')->assertOk()->getContent();

        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);

        $this->assertNotEmpty($matches[1], 'The sitemap is empty.');

        foreach ($matches[1] as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';

            $this->get($path)->assertOk("The sitemap advertises {$path}, which does not answer 200.");
        }
    }
}
