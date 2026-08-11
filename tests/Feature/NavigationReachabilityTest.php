<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Support\NavigationBuilder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menus have to point at the site the visitor is actually on, and every page
 * has to be reachable from one.
 *
 * Two defects found together, both invisible from the Arabic site:
 *
 *   1. A menu item's raw URL was returned exactly as stored, and every item
 *      is stored as "/ar/…". So on /en the whole header and footer linked
 *      back into the Arabic site — switch to English, click Solutions, land
 *      on Arabic. §12 forbids serving Arabic to an English visitor, and the
 *      hreflang tags in the same <head> declared the two reciprocal.
 *   2. The products showcase — 332 pieces, the largest thing on the site —
 *      appeared in no header menu. A visitor could only find it by scrolling
 *      to the footer.
 */
class NavigationReachabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StructureSeeder::class, NavigationSeeder::class, DemoContentSeeder::class]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);

        NavigationBuilder::flush();
    }

    #[Test]
    public function a_menu_link_targets_the_locale_being_rendered(): void
    {
        $item = NavigationItem::query()->where('url', '/ar/solutions')->firstOrFail();

        $this->assertSame('/ar/solutions', $item->resolvedUrl('ar'));
        $this->assertSame('/en/solutions', $item->resolvedUrl('en'));
    }

    #[Test]
    public function a_path_that_is_not_locale_prefixed_is_left_alone(): void
    {
        $header = Navigation::query()->where('key', Navigation::HEADER)->firstOrFail();

        $item = NavigationItem::query()->create([
            'navigation_id' => $header->id,
            'url' => '/sitemap.xml',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->assertSame('/sitemap.xml', $item->resolvedUrl('en'));
    }

    #[Test]
    public function an_external_menu_link_is_never_rewritten(): void
    {
        $header = Navigation::query()->where('key', Navigation::HEADER)->firstOrFail();

        $item = NavigationItem::query()->create([
            'navigation_id' => $header->id,
            'url' => 'https://amadcraft.sa/ar/products',
            'sort_order' => 98,
            'is_active' => true,
        ]);

        $this->assertSame('https://amadcraft.sa/ar/products', $item->resolvedUrl('en'));
    }

    #[Test]
    public function the_english_site_never_links_into_the_arabic_one(): void
    {
        $body = $this->get('/en')->assertOk()->getContent();

        preg_match_all('#href="(/ar[/"][^"]*)"#', $body, $matches);

        $this->assertSame([], $matches[1],
            'The English page links into the Arabic site: '.implode(', ', $matches[1]));
    }

    /**
     * The showcase left every menu when the header was cut to five entries,
     * so the home page's showroom section is now the ONLY route to 332
     * pieces. That link is load-bearing, and this is what says so: remove it
     * and the largest thing on the site becomes an orphan reachable only from
     * the sitemap.
     */
    /*
     * One render per test, deliberately. Two SSR renders inside a single test
     * return the FIRST render's markup, so a loop over both locales here
     * asserted the Arabic page twice and reported it as an English failure.
     */
    #[Test]
    public function the_arabic_home_page_reaches_the_products_showcase(): void
    {
        $this->get('/ar')->assertOk()->assertSee('/ar/products', false);
    }

    #[Test]
    public function the_english_home_page_reaches_the_products_showcase(): void
    {
        $this->get('/en')->assertOk()->assertSee('/en/products', false);
    }

    /**
     * The real guard: every managed page a visitor is meant to browse must be
     * reachable from a menu or from the home page, in both locales. A page
     * nobody can navigate to is a page that produces no leads (§1).
     *
     * `products` is checked separately above rather than listed here, because
     * it is deliberately in no menu — the client cut the header to five
     * entries — and asserting it into this list would have meant either a
     * failing suite or quietly dropping the reachability rule for it.
     */
    #[Test]
    public function every_browsable_page_is_reachable_from_some_menu(): void
    {
        $builder = app(NavigationBuilder::class);

        foreach (['ar', 'en'] as $locale) {
            $urls = [];

            foreach ($builder->all($locale) as $menu) {
                foreach ($menu as $item) {
                    $urls[] = $item['url'];

                    foreach ($item['children'] ?? [] as $child) {
                        $urls[] = $child['url'];
                    }
                }
            }

            foreach (['solutions', 'impact', 'training', 'partners', 'about', 'contact'] as $slug) {
                $this->assertContains("/{$locale}/{$slug}", $urls,
                    "/{$locale}/{$slug} is in no menu.");
            }

            // The four audience segments, which is the whole point of the
            // Solutions dropdown: each must be one click from the header.
            foreach (['government', 'companies', 'partners', 'artisans'] as $segment) {
                $this->assertContains("/{$locale}/solutions/{$segment}", $urls,
                    "/{$locale}/solutions/{$segment} is in no menu.");
            }
        }
    }

    /**
     * The header is five entries and nothing else. It grew to seven once
     * already, one reasonable-looking addition at a time.
     */
    #[Test]
    public function the_header_carries_exactly_five_entries(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $header = app(NavigationBuilder::class)->all($locale)[Navigation::HEADER] ?? [];

            $this->assertCount(5, $header,
                "The {$locale} header is not five entries: "
                .implode(', ', array_column($header, 'label')));

            $labels = array_column($header, 'url');
            $this->assertNotContains("/{$locale}/products", $labels);
            $this->assertNotContains("/{$locale}/sectors", $labels);
        }
    }
}
