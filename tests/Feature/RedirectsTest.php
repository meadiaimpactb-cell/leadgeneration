<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ApplyRedirects;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Managed 301s (§13, §22.7).
 *
 * Regression guard for a gap that looked like a feature: the `redirects` table
 * shipped from the first migration, the admin could add rows to it — and
 * nothing ever read it. Every legacy URL an administrator thought they had
 * protected was still a 404.
 */
class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);

        ApplyRedirects::flush();
    }

    private function redirect(array $attributes = []): Redirect
    {
        ApplyRedirects::flush();

        return Redirect::query()->create(array_merge([
            'from_path' => '/old-page',
            'to_path' => '/ar/about',
            'status_code' => 301,
            'is_active' => true,
        ], $attributes));
    }

    #[Test]
    public function a_managed_redirect_is_actually_served(): void
    {
        $this->redirect();

        $this->get('/old-page')
            ->assertStatus(301)
            ->assertRedirect('/ar/about');
    }

    #[Test]
    public function it_matches_regardless_of_a_trailing_slash(): void
    {
        $this->redirect(['from_path' => 'legacy/offer']);

        $this->get('/legacy/offer')->assertRedirect('/ar/about');
    }

    #[Test]
    public function an_inactive_redirect_is_ignored(): void
    {
        $this->redirect(['is_active' => false]);

        $this->get('/old-page')->assertNotFound();
    }

    #[Test]
    public function a_live_page_is_never_shadowed_by_a_redirect(): void
    {
        // Redirects are consulted only when the request would 404, so a row
        // left behind by mistake cannot hijack a working page.
        $this->redirect(['from_path' => '/ar/about', 'to_path' => '/ar']);

        $this->get('/ar/about')->assertOk();
    }

    #[Test]
    public function the_status_code_is_the_one_the_administrator_chose(): void
    {
        $this->redirect(['status_code' => 302]);

        $this->get('/old-page')->assertStatus(302);
    }

    #[Test]
    public function hits_are_counted_so_stale_rows_can_be_retired_on_evidence(): void
    {
        $redirect = $this->redirect();

        $this->get('/old-page');
        $this->get('/old-page');

        $this->assertSame(2, $redirect->fresh()->hits);
    }

    #[Test]
    public function an_unmatched_path_is_still_a_404(): void
    {
        $this->redirect();

        $this->get('/nothing-here')->assertNotFound();
    }
}
