<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The leads screen must actually draw — not merely return 200.
 *
 * This exists because of a bug that shipped past every other test on this
 * screen. A template expression built a URL with `new URLSearchParams(…)`
 * inline; template expressions compile against the component's render context,
 * so that resolved to `_ctx.URLSearchParams`, which is undefined, and threw
 * `is not a constructor` while rendering. The whole page went blank.
 *
 * Nothing caught it. The route still answered 200, the Inertia props were
 * still correct, and the assertions on this screen all looked at props. The
 * markup was never inspected, so "the page renders" was never actually tested.
 *
 * Worse, it only appeared for some accounts: the link carrying the broken
 * expression is drawn only when something is archived, so a fixture with an
 * empty archive rendered perfectly. The archived lead below is the point of
 * this file — remove it and the test passes against the broken code.
 */
class LeadsScreenRendersWithArchivedTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesSeeder::class);

        $user = User::query()->create([
            'name' => 'Screen probe',
            'email' => 'screen@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user;
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::query()->create(array_merge([
            'uuid' => (string) str()->uuid(),
            'contact_value' => 'buyer@example.sa',
            'contact_type' => 'email',
            'message' => 'An enquiry.',
            'locale' => 'ar',
            'status' => 'new',
        ], $attributes));
    }

    /**
     * Asserted on the server-rendered markup, not on the props.
     *
     * `data-server-rendered` proves SSR ran to completion; a render that threw
     * would have fallen back to an empty shell, which is exactly what the
     * browser showed.
     */
    #[Test]
    public function the_screen_draws_when_the_archive_link_is_present(): void
    {
        $this->lead(['contact_value' => 'live@example.sa']);
        $this->lead(['contact_value' => 'putaway@example.sa', 'archived_at' => now()]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/leads')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'data-server-rendered',
            $html,
            'SSR did not complete — the component threw while rendering.',
        );

        // The screen's own furniture, and the row it is meant to be showing.
        $this->assertStringContainsString('leadscreen', $html);
        $this->assertStringContainsString('live@example.sa', $html);

        // And the link whose inline expression caused the blank page.
        $this->assertStringContainsString('archived=1', $html);
    }

    #[Test]
    public function the_archive_view_draws_too(): void
    {
        $this->lead(['contact_value' => 'putaway@example.sa', 'archived_at' => now()]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/leads?archived=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-server-rendered', $html);
        $this->assertStringContainsString('putaway@example.sa', $html);
    }

    /**
     * The filters travel with the archive link, which is the reason it builds a
     * query string at all rather than pointing at a bare path.
     */
    #[Test]
    public function the_archive_link_carries_the_current_filters(): void
    {
        $this->lead(['archived_at' => now()]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/leads?status=new')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('status=new', $html);
        $this->assertStringContainsString('archived=1', $html);
    }
}
