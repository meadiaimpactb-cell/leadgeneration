<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The pages screen lists the pages.
 *
 * This exists because it did not, and the screen shipped empty.
 *
 * The retired pages were put behind a disclosure so an editor opening the
 * screen meets the one page the site actually serves rather than thirteen
 * rows of equals. The button was inserted between the empty-state `v-if` and
 * the table's `v-else`, which silently re-paired that `v-else` with the
 * BUTTON's condition — so the table rendered only when there were no retired
 * pages, which is never. Every row disappeared.
 *
 * Nothing caught it: the existing admin tests assert the screen returns 200
 * and carries the right props, and it did both. What was missing was anyone
 * checking that the rows reached the HTML — the difference between a screen
 * that answers and a screen that works.
 */
class ThePagesScreenShowsTheSitePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    #[Test]
    public function the_landing_page_is_listed_with_a_way_into_its_sections(): void
    {
        $landing = Page::query()->where('slug', 'landing')->firstOrFail();

        $html = $this->actingAs($this->admin())
            ->get('/admin/pages')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            "/admin/pages/{$landing->id}/edit",
            $html,
            'The site page is missing from the pages screen.',
        );

        $this->assertStringContainsString(
            "/admin/sections/page/{$landing->id}",
            $html,
            'There is no way into the landing page sections from the pages list.',
        );
    }

    /**
     * And it is the first row, not the last.
     *
     * It is the only page with a body since the landing-page decision, and it
     * was sorting to the bottom of thirteen — the one thing the editor opens
     * every day, under eleven that no longer answer.
     */
    #[Test]
    public function the_site_page_comes_first(): void
    {
        $pages = $this->actingAs($this->admin())
            ->get('/admin/pages')
            ->assertOk()
            ->viewData('page')['props']['pages'];

        $this->assertNotEmpty($pages, 'The pages screen received no pages at all.');
        $this->assertSame('landing', $pages[0]['slug'], 'The site page is not the first row.');
        $this->assertTrue($pages[0]['isSite']);
    }

    /**
     * Every page the screen received reaches the markup.
     *
     * The assertion the bug needed: props are not rows. A screen can be
     * handed thirteen pages and draw none of them.
     */
    #[Test]
    public function every_page_the_screen_receives_is_drawn(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/pages')->assertOk();

        $pages = collect($response->viewData('page')['props']['pages']);
        $html = $response->getContent();

        // The retired ones sit behind a closed disclosure, so only the live
        // rows are in the initial markup — but every live one must be.
        $live = $pages->reject(fn (array $page): bool => $page['retired']);

        $this->assertGreaterThan(0, $live->count(), 'No live pages to draw.');

        foreach ($live as $page) {
            $this->assertStringContainsString(
                "/admin/pages/{$page['id']}/edit",
                $html,
                "The page `{$page['slug']}` was passed to the screen and never drawn.",
            );
        }
    }
}
