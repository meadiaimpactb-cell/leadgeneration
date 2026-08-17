<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §9.2 on the one screen that had no authorisation at all.
 *
 * Every admin role reaches `/admin`. Only some may see enquiries: `editor` is
 * content-only and carries no `leads.view` in RolesSeeder, and `sales` exists
 * precisely because leads are meant to be a separate grant.
 *
 * The dashboard checked none of it. Measured against an editor account before
 * the fix: status 200, and the props carried the eight most recent leads with
 * each buyer's contact value and the full text of their message. Institutional
 * buyers' contact details are the most sensitive thing this site holds, and a
 * content editor was never meant to be handed them by the home screen of the
 * panel.
 *
 * These tests assert on the props, not on the rendered markup, deliberately.
 * Hiding a panel in Vue is not protection — the data would still be in the
 * page's JSON, readable by anyone who opens devtools. §9.2 says authorisation
 * is enforced on the server and never by hiding a button, so what must be
 * proved is that the server did not send it.
 */
class TheDashboardShowsOnlyWhatTheRoleMaySeeTest extends TestCase
{
    use RefreshDatabase;

    private const CONTACT = 'secret-buyer@ministry.gov.sa';

    private const MESSAGE = 'ميزانية المشروع السرية';

    private function anEnquiryExists(): void
    {
        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'contact_value' => self::CONTACT,
            'contact_type' => Lead::TYPE_EMAIL,
            'message' => self::MESSAGE,
            'locale' => 'ar',
            'status' => 'new',
            'crm_status' => Lead::CRM_PENDING,
        ]);
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** @return array<string, mixed> */
    private function dashboardPropsFor(User $user): array
    {
        return $this->actingAs($user)->get('/admin')->assertOk()->viewData('page')['props'];
    }

    #[Test]
    public function an_editor_is_never_sent_a_buyers_contact_details(): void
    {
        $this->anEnquiryExists();

        $editor = $this->userWith(User::ROLE_EDITOR);

        $this->assertFalse($editor->can('leads.view'),
            'This test is meaningless if the editor role ever gains leads.view.');

        $json = json_encode($this->dashboardPropsFor($editor), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString(self::CONTACT, (string) $json,
            "The dashboard sent a buyer's contact details to a role denied leads.view.");
        $this->assertStringNotContainsString(self::MESSAGE, (string) $json,
            "The dashboard sent a buyer's message to a role denied leads.view.");
    }

    /**
     * Not only the list. A count of enquiries is still information about
     * enquiries, and the role was denied all of it.
     */
    #[Test]
    public function an_editor_is_not_given_enquiry_figures_either(): void
    {
        $this->anEnquiryExists();

        $props = $this->dashboardPropsFor($this->userWith(User::ROLE_EDITOR));

        $this->assertFalse($props['maySeeLeads']);
        $this->assertArrayNotHasKey('total', $props['stats']);
        $this->assertArrayNotHasKey('unanswered', $props['stats']);
        $this->assertSame([], $props['latest']);
        $this->assertSame([], $props['daily']);
        $this->assertSame([], $props['bySource']);
    }

    /**
     * The editor still gets a working screen. Draft pages and live campaigns
     * say nothing about a buyer, and an editor needs to know what is
     * unpublished — removing those would have fixed the leak by breaking the
     * screen.
     */
    #[Test]
    public function an_editor_still_sees_the_figures_that_are_theirs(): void
    {
        $props = $this->dashboardPropsFor($this->userWith(User::ROLE_EDITOR));

        $this->assertArrayHasKey('drafts', $props['stats']);
        $this->assertArrayHasKey('liveCampaigns', $props['stats']);
    }

    #[Test]
    public function a_sales_user_still_sees_the_enquiries_that_are_their_job(): void
    {
        $this->anEnquiryExists();

        $sales = $this->userWith(User::ROLE_SALES);
        $props = $this->dashboardPropsFor($sales);

        $this->assertTrue($props['maySeeLeads']);
        $this->assertSame(1, $props['stats']['total']);
        $this->assertStringContainsString(
            self::CONTACT,
            (string) json_encode($props['latest'], JSON_UNESCAPED_UNICODE),
            'Fixing the leak must not blind the role whose whole job is answering enquiries.',
        );
    }

    #[Test]
    public function a_super_admin_sees_everything(): void
    {
        $this->anEnquiryExists();

        $props = $this->dashboardPropsFor($this->userWith(User::ROLE_SUPER_ADMIN));

        $this->assertTrue($props['maySeeLeads']);
        $this->assertSame(1, $props['stats']['total']);
    }
}
