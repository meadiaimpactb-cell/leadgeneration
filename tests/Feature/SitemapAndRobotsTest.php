<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\ShowcaseProduct;
use App\Models\Solution;
use App\Support\Settings;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * sitemap.xml and robots.txt (§5, §13).
 *
 * The governing idea is that a sitemap must not lie. Every assertion here is
 * some form of that: no drafts, no deactivated records, no untranslated
 * records in a language they do not exist in, and — the one that bit during
 * the build — no URL that answers 404.
 */
class SitemapAndRobotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            StructureSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
            DemoExtrasSeeder::class,
        ]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /** @return list<string> */
    private function locations(string $locale = 'ar'): array
    {
        $body = $this->get("/sitemap-{$locale}.xml")->assertOk()->getContent();

        preg_match_all('#<loc>(.*?)</loc>#', $body, $matches);

        return array_map(html_entity_decode(...), $matches[1]);
    }

    #[Test]
    public function the_index_points_at_one_sitemap_per_enabled_language(): void
    {
        $body = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<sitemapindex', $body);
        $this->assertStringContainsString('/sitemap-ar.xml', $body);
        $this->assertStringContainsString('/sitemap-en.xml', $body);
    }

    #[Test]
    public function a_language_the_site_does_not_serve_has_no_sitemap(): void
    {
        $this->get('/sitemap-fr.xml')->assertNotFound();
    }

    #[Test]
    public function turning_english_off_removes_it_from_the_index_and_404s_its_sitemap(): void
    {
        // §12 gates the English site behind a settings switch. A sitemap that
        // outlived the switch would keep feeding a crawler URLs the site no
        // longer serves.
        Setting::query()->where('group', 'site')->where('key', 'english_enabled')
            ->update(['value' => json_encode(false)]);

        app(Settings::class)->forget();

        $this->assertStringNotContainsString('/sitemap-en.xml', $this->get('/sitemap.xml')->getContent());
        $this->get('/sitemap-en.xml')->assertNotFound();
    }

    #[Test]
    public function every_listed_url_actually_resolves(): void
    {
        // The defect this caught: a product category with no products was
        // listed, but ProductController answers 404 for one — so the sitemap
        // was advertising a dead URL. Walking the list is the only assertion
        // that would have found it.
        foreach ($this->locations() as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);

            $this->get($path.($query === null ? '' : '?'.$query))
                ->assertOk(); // "{$url} is in the sitemap but does not resolve"
        }
    }

    /**
     * The same rule, for the case the test above cannot reach.
     *
     * `every_listed_url_actually_resolves` walks what the seeders produced,
     * and all of those pages have literal routes — so it stayed green while
     * this was broken. Public pages are served by named routes, not by a
     * `/{locale}/{slug}` catch-all, so a page created in the panel with any
     * other slug was published, indexable, translated, listed in the sitemap,
     * and answered 404 at the address the sitemap gave.
     */
    #[Test]
    public function a_page_with_no_route_of_its_own_is_not_listed(): void
    {
        $page = Page::query()->create([
            'slug' => 'a-slug-with-no-route',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);

        $page->translations()->create(['locale' => 'ar', 'title' => 'صفحة بلا مسار']);

        $this->get('/ar/a-slug-with-no-route')->assertNotFound();

        $this->assertNotContains(url('ar/a-slug-with-no-route'), $this->locations(),
            'The sitemap is advertising a URL that answers 404.');
    }

    #[Test]
    public function a_draft_page_is_not_listed(): void
    {
        $this->assertContains(url('ar/about'), $this->locations());

        Page::query()->where('slug', 'about')
            ->update(['status' => 'draft', 'published_at' => null]);

        $this->assertNotContains(url('ar/about'), $this->locations());
    }

    #[Test]
    public function a_page_the_client_marked_non_indexable_is_not_listed(): void
    {
        // The legal pages ship this way, so they are the live example.
        $locations = $this->locations();

        $this->assertNotContains(url('ar/legal/privacy'), $locations);
        $this->assertNotContains(url('ar/legal/terms'), $locations);
    }

    #[Test]
    public function a_deactivated_record_is_not_listed(): void
    {
        $solution = Solution::query()->where('is_active', true)->firstOrFail();

        $this->assertContains(url("ar/solutions/{$solution->slug}"), $this->locations());

        $solution->forceFill(['is_active' => false])->save();

        $this->assertNotContains(url("ar/solutions/{$solution->slug}"), $this->locations());
    }

    #[Test]
    public function an_untranslated_record_is_absent_from_that_languages_sitemap(): void
    {
        $solution = Solution::query()->where('is_active', true)->firstOrFail();
        $solution->translations()->where('locale', 'en')->delete();

        $this->assertContains(url("ar/solutions/{$solution->slug}"), $this->locations('ar'));
        $this->assertNotContains(url("en/solutions/{$solution->slug}"), $this->locations('en'));
    }

    #[Test]
    public function an_empty_product_category_is_not_listed(): void
    {
        $category = ProductCategory::query()->create([
            'slug' => 'empty-cat', 'sort_order' => 99, 'is_active' => true,
        ]);
        $category->translations()->create(['locale' => 'ar', 'name' => 'فارغ']);

        $this->assertNotContains(
            url('ar/products').'?category=empty-cat',
            $this->locations(),
        );
    }

    #[Test]
    public function a_populated_product_category_is_listed(): void
    {
        $category = ProductCategory::query()->create([
            'slug' => 'full-cat', 'sort_order' => 98, 'is_active' => true,
        ]);
        $category->translations()->create(['locale' => 'ar', 'name' => 'ممتلئ']);

        $product = ShowcaseProduct::query()->create([
            'slug' => 'p-1', 'product_category_id' => $category->id,
            'sort_order' => 0, 'is_active' => true,
        ]);
        $product->translations()->create(['locale' => 'ar', 'name' => 'منتج']);

        $this->assertContains(
            url('ar/products').'?category=full-cat',
            $this->locations(),
        );
    }

    #[Test]
    public function every_entry_carries_reciprocal_hreflang(): void
    {
        $body = $this->get('/sitemap-ar.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $body);
        $this->assertStringContainsString('hreflang="ar"', $body);
        $this->assertStringContainsString('hreflang="x-default"', $body);
    }

    #[Test]
    public function robots_closes_the_site_outside_production(): void
    {
        // §16: staging must never be indexed. A staging copy competing with
        // the live site for the same Arabic keywords is the duplicate-content
        // failure §13 forbids, and no setting may switch it back on.
        Setting::query()->where('group', 'seo')->where('key', 'robots_txt')
            ->update(['value' => json_encode("User-agent: *\nAllow: /")]);

        app(Settings::class)->forget();

        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Allow: /', $body);
    }

    #[Test]
    public function robots_in_production_serves_the_clients_own_rules_plus_the_sitemap(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        Setting::query()->where('group', 'seo')->where('key', 'robots_txt')
            ->update(['value' => json_encode("User-agent: *\nDisallow: /nothing-here")]);

        app(Settings::class)->forget();

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /nothing-here', $body);

        // The Sitemap line is appended whatever the client wrote, so an SEO
        // consultant pasting a rules block cannot silently drop it.
        $this->assertStringContainsString('Sitemap: '.url('sitemap.xml'), $body);
    }

    #[Test]
    public function robots_falls_back_to_sane_defaults_when_the_setting_is_empty(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Sitemap: ', $body);
    }

    #[Test]
    public function no_static_robots_file_shadows_the_route(): void
    {
        // Laravel ships public/robots.txt, which the web server serves before
        // PHP ever runs — the dynamic route existed and was unreachable.
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }
}
