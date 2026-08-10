<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Draft preview through a secret link (§9.1).
 *
 * Regression guard for two defects that hid each other:
 *
 *   1. The preview token was never checked on public pages. The panel showed a
 *      preview button; following it returned the same 404 any visitor got. The
 *      whole "save drafts and preview before publishing" requirement was
 *      decorative.
 *   2. The preview URL was built as /{locale}/{slug}, but the home page is
 *      served from the locale root — so the home page's preview link pointed
 *      at /ar/home, which is not a route.
 */
class DraftPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StructureSeeder::class, NavigationSeeder::class]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    private function draft(string $slug): Page
    {
        $page = Page::query()->where('slug', $slug)->sole();
        $page->forceFill(['status' => 'draft', 'published_at' => null])->save();

        return $page->fresh();
    }

    #[Test]
    public function the_home_page_preview_url_points_at_the_locale_root_not_at_slash_home(): void
    {
        $home = Page::query()->where('slug', 'home')->sole();

        $this->assertSame('/ar', parse_url($home->publicUrl('ar'), PHP_URL_PATH));
        $this->assertStringNotContainsString('/ar/home', $home->previewUrl('ar'));
    }

    #[Test]
    public function an_inner_page_preview_url_uses_its_slug(): void
    {
        $about = Page::query()->where('slug', 'about')->sole();

        $this->assertSame('/ar/about', parse_url($about->publicUrl('ar'), PHP_URL_PATH));
    }

    #[Test]
    public function a_draft_page_is_visible_through_its_own_token(): void
    {
        $page = $this->draft('about');

        $this->get('/ar/about')->assertNotFound();
        $this->get("/ar/about?preview={$page->preview_token}")->assertOk();
    }

    #[Test]
    public function a_draft_home_page_is_visible_through_its_own_token(): void
    {
        $page = $this->draft('home');

        // The home page is the one whose preview URL was broken.
        $this->get("/ar?preview={$page->preview_token}")->assertOk();
    }

    #[Test]
    public function a_wrong_token_does_not_open_a_draft(): void
    {
        $this->draft('about');

        $this->get('/ar/about?preview=not-the-token')->assertNotFound();
        $this->get('/ar/about?preview=')->assertNotFound();
    }

    #[Test]
    public function one_pages_token_does_not_open_another_pages_draft(): void
    {
        $this->draft('about');
        $products = Page::query()->where('slug', 'products')->sole();

        $this->get("/ar/about?preview={$products->preview_token}")->assertNotFound();
    }

    #[Test]
    public function a_preview_is_never_indexed(): void
    {
        $page = $this->draft('about');

        // A draft reachable by URL must not end up in search results.
        $this->get("/ar/about?preview={$page->preview_token}")
            ->assertSee('noindex, nofollow', false);
    }

    #[Test]
    public function a_published_page_needs_no_token(): void
    {
        $this->get('/ar/about')->assertOk();
        $this->get('/ar')->assertOk();
    }

    /**
     * @param  string  $slug  the managed page row
     * @param  string  $path  the route it governs
     */
    #[Test]
    #[DataProvider('listingPages')]
    public function unpublishing_a_listing_page_actually_hides_it(string $slug, string $path): void
    {
        // These routes used to render whether or not their page row was
        // published, so unpublishing one from the panel changed nothing on the
        // site. Publish must mean the same thing on every page.
        $this->get($path)->assertOk();

        $page = $this->draft($slug);

        $this->get($path)->assertNotFound();
        $this->get("{$path}?preview={$page->preview_token}")->assertOk();
    }

    /** @return array<string, array{string, string}> */
    public static function listingPages(): array
    {
        return [
            'products' => ['products', '/ar/products'],
            'impact' => ['impact', '/ar/impact'],
            'training' => ['training', '/ar/training'],
            'partners' => ['partners', '/ar/partners'],
            'contact' => ['contact', '/ar/contact'],
            'solutions' => ['solutions', '/ar/solutions'],
        ];
    }
}
