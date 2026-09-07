<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Sector;
use App\Models\Solution;
use App\Support\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nothing the site links to may 404.
 *
 * Regression guard for a real defect: the footer's legal menu pointed at
 * /{locale}/legal/privacy and /{locale}/legal/terms, and neither page existed.
 * Two dead links sat on every page of the live site.
 *
 * §13 treats a broken internal link as an indexing defect, not a cosmetic one,
 * so this walks the menus the site actually renders rather than a hand-kept
 * list that would drift.
 */
class NoBrokenInternalLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        NavigationBuilder::flush();
    }

    #[Test]
    public function every_link_in_every_menu_resolves(): void
    {
        $broken = [];

        foreach (['ar', 'en'] as $locale) {
            foreach (app(NavigationBuilder::class)->all($locale) as $menu => $items) {
                foreach ($items as $item) {
                    $path = parse_url($item['url'], PHP_URL_PATH) ?: '/';
                    $status = $this->get($path)->getStatusCode();

                    if ($status >= 400) {
                        $broken[] = "{$menu}: {$path} → {$status}";
                    }
                }
            }
        }

        $this->assertSame([], $broken, 'The site renders menu links that do not resolve.');
    }

    #[Test]
    public function the_legal_pages_the_footer_links_to_exist(): void
    {
        // These two were the actual 404s.
        $this->get('/ar/legal/privacy')->assertOk();
        $this->get('/ar/legal/terms')->assertOk();
    }

    #[Test]
    public function every_published_page_resolves_in_the_locales_it_is_translated_into(): void
    {
        $broken = [];

        foreach (Page::query()->published()->with('translations')->get() as $page) {
            foreach ($page->translatedLocales() as $locale) {
                /*
                 * Asked of the page, not rebuilt from its slug.
                 *
                 * This used to compose "/{locale}/{slug}" with one special
                 * case for `home`, which made it a third copy of a rule that
                 * already lives in Page::ROOT_SLUGS and PageController. When
                 * the landing page joined that list the copy here went stale
                 * and the test reported /ar/landing → 404 as a broken page —
                 * a URL the site never advertises. `publicUrl()` is what the
                 * sitemap, the menus and the preview button all ask, so it is
                 * what this should check.
                 */
                $path = parse_url($page->publicUrl($locale), PHP_URL_PATH) ?: '/';
                $status = $this->get($path)->getStatusCode();

                if ($status >= 400) {
                    $broken[] = "{$path} → {$status}";
                }
            }
        }

        $this->assertSame([], $broken);
    }

    #[Test]
    public function a_live_campaign_page_resolves(): void
    {
        $this->get('/ar/c/riyadh-season')->assertOk();
        $this->get('/en/c/riyadh-season')->assertOk();
    }

    #[Test]
    public function every_solution_and_sector_the_home_page_lists_resolves(): void
    {
        $broken = [];

        foreach (Solution::query()->visible()->get() as $solution) {
            foreach ($solution->translatedLocales() as $locale) {
                $path = "/{$locale}/solutions/{$solution->slug}";

                if ($this->get($path)->getStatusCode() >= 400) {
                    $broken[] = $path;
                }
            }
        }

        foreach (Sector::query()->visible()->get() as $sector) {
            foreach ($sector->translatedLocales() as $locale) {
                $path = "/{$locale}/solutions/{$sector->slug}";

                if ($this->get($path)->getStatusCode() >= 400) {
                    $broken[] = $path;
                }
            }
        }

        $this->assertSame([], $broken);
    }
}
