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
 * Admin authentication and the tiered permissions in §9.1.
 *
 * §9.2 is explicit that authorisation must be enforced by policies, never by
 * hiding buttons — so these tests hit the routes directly rather than
 * checking what the navigation renders.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = User::query()->create([
            'name' => 'Test '.$role,
            'email' => $role.'@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => $active,
        ]);

        $user->assignRole($role);

        return $user;
    }

    #[Test]
    public function a_guest_is_sent_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_valid_account_can_sign_in(): void
    {
        $user = $this->userWithRole(User::ROLE_SUPER_ADMIN);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-1!',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $user = $this->userWithRole(User::ROLE_EDITOR);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in(): void
    {
        $user = $this->userWithRole(User::ROLE_EDITOR, active: false);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function super_admin_reaches_everything(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_SUPER_ADMIN));

        $this->get('/admin')->assertOk();
        $this->get('/admin/leads')->assertOk();
        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/content/solutions')->assertOk();
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/users')->assertOk();
    }

    #[Test]
    public function sales_reaches_leads_only(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_SALES));

        $this->get('/admin/leads')->assertOk();

        // §9.1: the sales role is leads only — read, export, change status.
        $this->get('/admin/pages')->assertForbidden();
        $this->get('/admin/content/solutions')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function sales_can_export_but_never_delete_a_lead(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_SALES));

        $this->get('/admin/leads/export')->assertOk();
    }

    #[Test]
    public function an_editor_reaches_content_but_not_leads_or_settings(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_EDITOR));

        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/content/solutions')->assertOk();

        $this->get('/admin/leads')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function a_campaign_manager_reaches_campaigns_and_leads_but_not_pages(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_CAMPAIGN_MANAGER));

        $this->get('/admin/campaigns')->assertOk();
        $this->get('/admin/leads')->assertOk();

        $this->get('/admin/pages')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }

    #[Test]
    public function an_editor_cannot_change_a_lead_status(): void
    {
        $lead = Lead::query()->create([
            'contact_value' => 'a@b.sa',
            'contact_type' => Lead::TYPE_EMAIL,
            'locale' => 'ar',
        ]);

        $this->actingAs($this->userWithRole(User::ROLE_EDITOR));

        $this->patch("/admin/leads/{$lead->id}", ['status' => 'won'])->assertForbidden();
        $this->assertSame('new', $lead->fresh()->status);
    }

    #[Test]
    public function a_lead_can_never_be_deleted_through_the_panel(): void
    {
        // There is deliberately no delete route: a lead is the only record of
        // a real prospect and its audit trail must survive.
        $this->assertFalse(
            collect(app('router')->getRoutes())
                ->contains(fn ($route) => str_contains($route->uri(), 'admin/leads')
                    && in_array('DELETE', $route->methods(), true))
        );
    }
}
