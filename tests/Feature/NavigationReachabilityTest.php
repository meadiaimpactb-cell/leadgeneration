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

    #[Test]
    public function the_products_showcase_is_reachable_from_the_header(): void
    {
        $header = app(NavigationBuilder::class)->all('ar')[Navigation::HEADER] ?? [];

        $this->assertContains('/ar/products', array_column($header, 'url'),
            'The product showcase is in no header menu — a visitor can only reach it from the footer.');
    }

    /**
     * The real guard: every managed page a visitor is meant to browse must be
     * reachable from a menu, in both locales. A page nobody can navigate to
     * is a page that produces no leads (§1).
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

            foreach (['solutions', 'products', 'impact', 'training', 'partners', 'about', 'contact'] as $slug) {
                $this->assertContains("/{$locale}/{$slug}", $urls,
                    "/{$locale}/{$slug} is in no menu.");
            }
        }
    }
}
