<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Archiving is not deleting, and this file exists to keep it that way.
 *
 * §9.1 and LeadPolicy both say a lead is never deleted: it is the site's only
 * record of a real prospect and its trail must survive a change of mind. But
 * an editor still needs to get demo rows and automated noise out of the list,
 * and a list that cannot be tidied stops being read.
 *
 * Archiving answers that need by hiding a row from exactly one query. The
 * danger is drift: the obvious "improvements" from here are a global scope, a
 * `SoftDeletes` trait, or a real DELETE route — each of which would satisfy
 * the same user request while quietly destroying the thing the rule protects.
 * Every assertion below is aimed at one of those.
 */
class LeadArchivingKeepsTheRecordTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $attributes = []): Lead
    {
        return Lead::query()->create(array_merge([
            'uuid' => (string) str()->uuid(),
            'contact_value' => 'buyer@example.sa',
            'contact_type' => 'email',
            'message' => 'A real enquiry.',
            'locale' => 'ar',
            'status' => 'new',
        ], $attributes));
    }

    /**
     * Built by hand rather than by factory: `is_active` has no factory default
     * and `User::isActive()` returns a strict bool, so a factory user fails
     * inside the auth middleware with an error about a return type rather than
     * about the account. Matches AdminAccessTest's helper.
     */
    private function userWithRole(string $role): User
    {
        $user = User::query()->create([
            'name' => 'Test '.$role,
            'email' => $role.'@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole(User::ROLE_SUPER_ADMIN);
    }

    #[Test]
    public function archiving_hides_the_row_without_removing_it(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->admin())
            ->post("/admin/leads/{$lead->id}/archive")
            ->assertRedirect();

        // Still there, with every column it arrived with.
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'contact_value' => 'buyer@example.sa',
            'message' => 'A real enquiry.',
        ]);

        $this->assertNotNull($lead->fresh()->archived_at);
    }

    #[Test]
    public function the_same_control_puts_it_back(): void
    {
        $lead = $this->lead(['archived_at' => now()]);

        $this->actingAs($this->admin())->post("/admin/leads/{$lead->id}/archive");

        $this->assertNull(
            $lead->fresh()->archived_at,
            'Archiving must be its own undo — an editor who cannot reverse it stops using it.',
        );
    }

    /**
     * The list hides archived rows; nothing else does.
     *
     * A global scope would have been the shorter way to write this and would
     * have silently changed what §1 measures the site by: the dashboard's
     * counts, and the CSV export the sales team works from.
     */
    #[Test]
    public function archived_leads_still_count_everywhere_else(): void
    {
        $this->lead();
        $this->lead(['archived_at' => now()]);

        $this->assertSame(2, Lead::query()->count(), 'A global scope has crept in.');
        $this->assertSame(1, Lead::query()->notArchived()->count());
        $this->assertSame(1, Lead::query()->archived()->count());
    }

    /**
     * Asserted on the Inertia props rather than on the rendered HTML: the
     * panel is client-rendered, so every lead in the payload appears in the
     * page source whether the table draws it or not. Matching strings there
     * would pass while showing the editor the opposite.
     */
    #[Test]
    public function the_leads_list_hides_them_and_the_archive_view_shows_them(): void
    {
        $visible = $this->lead(['contact_value' => 'visible@example.sa']);
        $hidden = $this->lead(['contact_value' => 'hidden@example.sa', 'archived_at' => now()]);

        $this->actingAs($this->admin());

        $this->get('/admin/leads')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('leads.data.0.id', $visible->id)
                ->count('leads.data', 1));

        $this->get('/admin/leads?archived=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('leads.data.0.id', $hidden->id)
                ->count('leads.data', 1));
    }

    /**
     * The rule this feature was built around, restated from the other side.
     *
     * Asserted against the policy object directly, not through the Gate: a
     * super-admin passes `Gate::before` in AppServiceProvider and would be
     * allowed anything, which would make a Gate-based assertion here test the
     * bypass rather than the rule. The point is that the rule itself never
     * softens — so relaxing `delete()` to "make archiving simpler" fails here
     * rather than shipping.
     */
    #[Test]
    public function the_delete_rule_itself_never_softens(): void
    {
        $lead = $this->lead();

        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_SALES, User::ROLE_EDITOR] as $role) {
            $this->assertFalse(
                (new LeadPolicy)->delete($this->userWithRole($role), $lead),
                "LeadPolicy::delete() returned true for {$role} — archiving exists so that it never does.",
            );
        }
    }

    /** And no role reaches a delete through the Gate either, bypass aside. */
    #[Test]
    public function sales_cannot_delete_a_lead(): void
    {
        $lead = $this->lead();

        $this->assertFalse($this->userWithRole(User::ROLE_SALES)->can('delete', $lead));
    }

    #[Test]
    public function an_editor_may_not_archive_what_they_may_not_judge(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->userWithRole(User::ROLE_EDITOR))
            ->post("/admin/leads/{$lead->id}/archive")
            ->assertForbidden();

        $this->assertNull($lead->fresh()->archived_at);
    }
}
