<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Sector;
use App\Models\Solution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The public routes from §5.
 *
 * The sector links on the home page were dead until these existed, so these
 * tests exist mainly to make sure a link the site renders always resolves.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishPages();
    }

    /** The structure seeder creates pages as drafts; publish them to view. */
    private function publishPages(): void
    {
        Page::query()->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    #[Test]
    #[DataProvider('sectorSlugs')]
    public function every_sector_page_resolves(string $slug): void
    {
        $this->get("/ar/solutions/{$slug}")->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function sectorSlugs(): array
    {
        return [
            'government' => ['government'],
            'private' => ['companies'],
            'partners' => ['partners'],
            'artisans' => ['artisans'],
        ];
    }

    #[Test]
    public function every_sector_the_home_page_links_to_has_a_page(): void
    {
        // The home page renders one card per active sector. If a sector is
        // active but its route 404s, the site is linking to nothing.
        foreach (Sector::query()->visible()->get() as $sector) {
            $this->get("/ar/solutions/{$sector->slug}")
                ->assertOk("Sector [{$sector->slug}] is linked from the home page but does not resolve.");
        }
    }

    #[Test]
    #[DataProvider('staticPaths')]
    public function the_main_pages_resolve(string $path): void
    {
        $this->get("/ar/{$path}")->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function staticPaths(): array
    {
        return [
            'about' => ['about'],
            'solutions' => ['solutions'],
            'products' => ['products'],
            'impact' => ['impact'],
            'training' => ['training'],
            'partners' => ['partners'],
            'contact' => ['contact'],
        ];
    }

    #[Test]
    public function a_solution_page_resolves(): void
    {
        $solution = Solution::query()->create(['slug' => 'test-solution', 'is_active' => true]);
        $solution->translations()->create(['locale' => 'ar', 'name' => 'حل تجريبي']);

        $this->get('/ar/solutions/test-solution')->assertOk();
    }

    #[Test]
    public function an_unknown_sector_is_a_404(): void
    {
        $this->get('/ar/solutions/does-not-exist')->assertNotFound();
    }

    #[Test]
    public function an_inactive_solution_is_a_404(): void
    {
        $solution = Solution::query()->create(['slug' => 'hidden', 'is_active' => false]);
        $solution->translations()->create(['locale' => 'ar', 'name' => 'مخفي']);

        $this->get('/ar/solutions/hidden')->assertNotFound();
    }

    #[Test]
    public function a_record_untranslated_in_a_locale_is_hidden_there_not_shown_in_arabic(): void
    {
        $solution = Solution::query()->create(['slug' => 'arabic-only', 'is_active' => true]);
        $solution->translations()->create(['locale' => 'ar', 'name' => 'بالعربية فقط']);

        // §12: never fall back to Arabic for an English visitor.
        $this->get('/ar/solutions/arabic-only')->assertOk();
        $this->get('/en/solutions/arabic-only')->assertNotFound();
    }

    #[Test]
    public function an_unpublished_page_is_not_public(): void
    {
        Page::query()->where('slug', 'about')->update(['status' => 'draft']);

        $this->get('/ar/about')->assertNotFound();
    }

    #[Test]
    public function legal_pages_are_not_indexed(): void
    {
        // The structural seeders already ship this page, so the test adopts it
        // rather than insisting on creating it — what it asserts is the
        // noindex, not who made the row.
        $page = Page::query()->firstOrCreate(
            ['slug' => 'legal/privacy'],
            ['status' => 'published', 'published_at' => now()],
        );
        $page->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $page->translations()->updateOrCreate(
            ['locale' => 'ar'],
            ['title' => 'الخصوصية'],
        );

        // Legal pages dilute the crawl budget and carry no ranking value.
        $this->get('/ar/legal/privacy')
            ->assertOk()
            ->assertSee('noindex', false);
    }
}
