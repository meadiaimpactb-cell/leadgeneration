<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A page created in the panel answers at its own address (§9.1).
 *
 * Every other public URL is a literal route bound to a controller that carries
 * datasets a composition cannot — /about has the impact figures, /impact has
 * the stories. A page the client adds has none of that, and had no route
 * either: it was published, translated and indexable while its address
 * answered 404. `SitemapGenerator::reachable()` already guards the sitemap
 * against advertising it; this holds the other half — that the URL resolves,
 * so the guard has nothing to catch.
 *
 * The risk in a catch-all is not that it fails to match. It is that it matches
 * too much: shadowing a literal route, or answering at a second address for a
 * page that already has one. Most of what follows tests that.
 */
class PanelCreatedPagesResolveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            StructureSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
        ]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /** A page as the panel writes one: a slug, a template, two translations. */
    private function panelPage(string $slug, string $template = 'default'): Page
    {
        $page = Page::query()->create([
            'slug' => $slug,
            'template' => $template,
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);

        foreach (['ar', 'en'] as $locale) {
            $page->translations()->create([
                'locale' => $locale,
                'title' => "{$slug}-{$locale}",
            ]);
        }

        return $page->fresh();
    }

    #[Test]
    public function a_page_created_in_the_panel_answers_at_its_own_address(): void
    {
        $this->panelPage('services');

        foreach (['ar', 'en'] as $locale) {
            $this->get("/{$locale}/services")
                ->assertOk()
                ->assertSee("services-{$locale}");
        }
    }

    #[Test]
    public function an_unknown_template_composes_rather_than_erroring(): void
    {
        // `template` is a label the panel stores, not a file it resolves. A
        // page is its ordered sections whatever the label says, so a name no
        // component knows must render, not 500.
        $this->panelPage('advisory', template: 'a-template-no-component-knows');

        $this->get('/ar/advisory')->assertOk()->assertSee('advisory-ar');
    }

    #[Test]
    public function every_literal_route_still_wins_over_the_catch_all(): void
    {
        /*
         * The failure this guards against is silent: a page row whose slug
         * collides with a real path would be served by whichever route matched
         * first, and the wrong one loses the controller's datasets without
         * raising anything. Registration order is what keeps the literal
         * route ahead — assert it rather than trust it.
         */
        foreach (['about', 'solutions', 'products', 'impact', 'training', 'partners', 'contact'] as $slug) {
            $handler = app('router')->getRoutes()
                ->match(request()->create("/ar/{$slug}", 'GET'))
                ->getActionName();

            $this->assertStringNotContainsString('@show', $handler,
                "/ar/{$slug} is being served by the catch-all rather than its own controller.");
        }
    }

    #[Test]
    public function the_home_page_does_not_gain_a_second_address(): void
    {
        // `home` is a pages row like any other, and its public URL is
        // `/{locale}`. Left unreserved, /ar/home would render the same
        // sections at a second address and split the ranking of the page that
        // matters most (§13).
        $this->get('/ar')->assertOk();
        $this->get('/ar/home')->assertNotFound();
    }

    #[Test]
    public function an_unknown_slug_still_answers_404(): void
    {
        $this->get('/ar/no-such-page-exists')->assertNotFound();
    }

    #[Test]
    public function a_draft_is_reachable_only_by_its_preview_token(): void
    {
        $page = $this->panelPage('unannounced');
        $page->forceFill(['status' => 'draft', 'published_at' => null])->save();

        $this->get('/ar/unannounced')->assertNotFound();
        $this->get("/ar/unannounced?preview={$page->preview_token}")->assertOk();
    }

    #[Test]
    public function a_page_untranslated_in_a_locale_is_absent_from_that_site(): void
    {
        // §12: a missing translation hides the page rather than falling back.
        // Serving Arabic to an English-speaking buyer is worse than a 404.
        $page = $this->panelPage('arabic-only');
        $page->translations()->where('locale', 'en')->delete();

        $this->get('/ar/arabic-only')->assertOk();
        $this->get('/en/arabic-only')->assertNotFound();
    }

    #[Test]
    public function the_new_page_now_enters_the_sitemap_it_was_kept_out_of(): void
    {
        $this->panelPage('services');

        $body = $this->get('/sitemap-ar.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('ar/services'), html_entity_decode($body));
    }

    #[Test]
    public function the_admin_panel_is_not_reachable_through_a_locale_prefix(): void
    {
        $this->get('/ar/admin')->assertNotFound();
    }
}
