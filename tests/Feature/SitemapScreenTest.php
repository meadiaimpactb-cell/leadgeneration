<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The panel's view of what a crawler is offered (§13).
 *
 * The file has always worked; this screen exists so an operator can answer
 * "is my new page in there" without reading XML. Which means the one thing
 * that must be true is that the screen and the file never disagree.
 */
class SitemapScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The demo content is seeded because without it nothing is published,
         * and a sitemap of zero URLs makes the assertions below pass while
         * proving nothing. The count assertion in the first test is there to
         * keep it that way.
         */
        $this->seed([
            RolesSeeder::class,
            StructureSeeder::class,
            NavigationSeeder::class,
            LeadFieldsSeeder::class,
            DemoContentSeeder::class,
        ]);

        $this->admin = User::query()->create([
            'name' => 'SEO admin',
            'email' => 'sitemap@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        return $this->actingAs($this->admin)
            ->get('/admin/seo/sitemap')->assertOk()
            ->viewData('page')['props'];
    }

    #[Test]
    public function the_screen_names_the_file_and_counts_what_is_in_it(): void
    {
        $props = $this->props();

        $this->assertSame(url('sitemap.xml'), $props['indexUrl']);
        $this->assertNotEmpty($props['locales']);

        foreach ($props['locales'] as $file) {
            $this->assertSame(url("sitemap-{$file['locale']}.xml"), $file['url']);
            $this->assertSame(count($file['urls']), $file['count']);

            // Without this every assertion in this file passes on an empty
            // list, which is how a broken screen would look identical to a
            // working one.
            $this->assertGreaterThan(0, $file['count'],
                "The {$file['locale']} sitemap is empty, so nothing here is being tested.");
        }
    }

    /** The whole point: one source, so the panel cannot describe a different file. */
    #[Test]
    public function every_url_the_screen_lists_is_in_the_file_itself(): void
    {
        $props = $this->props();

        foreach ($props['locales'] as $file) {
            $xml = $this->get("/sitemap-{$file['locale']}.xml")->assertOk()->getContent();

            $this->assertSame(
                substr_count($xml, '<url>'),
                $file['count'],
                "The screen and the {$file['locale']} sitemap report different counts.",
            );

            foreach ($file['urls'] as $url) {
                $this->assertStringContainsString(
                    htmlspecialchars($url['loc'], ENT_QUOTES),
                    $xml,
                    'The screen lists a URL the sitemap does not.',
                );
            }
        }
    }

    #[Test]
    public function unpublishing_a_page_removes_it_from_the_screen_with_no_regeneration(): void
    {
        $page = Page::query()->published()->where('is_indexable', true)->firstOrFail();

        $before = collect($this->props()['locales'])->firstWhere('locale', 'ar')['count'];

        $page->forceFill(['status' => 'draft'])->save();

        $after = collect($this->props()['locales'])->firstWhere('locale', 'ar')['count'];

        $this->assertSame($before - 1, $after,
            'A page left the site and the sitemap screen did not notice.');
    }

    /**
     * §16: outside production the site is closed to indexing, and handing this
     * URL to Search Console from staging would wait forever for a crawl that
     * was never coming.
     */
    #[Test]
    public function the_screen_says_when_nothing_will_be_crawled(): void
    {
        $this->assertFalse($this->props()['indexingOpen']);
    }

    #[Test]
    public function a_visitor_cannot_reach_the_screen(): void
    {
        $this->get('/admin/seo/sitemap')->assertRedirect('/admin/login');
    }

    /** It replaced a placeholder; the placeholder must not still answer. */
    #[Test]
    public function the_screen_is_no_longer_a_coming_soon_placeholder(): void
    {
        $component = $this->actingAs($this->admin)
            ->get('/admin/seo/sitemap')->assertOk()
            ->viewData('page')['component'];

        $this->assertSame('Admin/Seo/Sitemap', $component);
    }
}
