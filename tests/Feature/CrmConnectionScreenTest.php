<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PushLeadToCrm;
use App\Jobs\ResyncFailedLeads;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Support\CrmSettings;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 2.2 — the CRM connection screen (§6.3).
 */
class CrmConnectionScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([RolesSeeder::class, StructureSeeder::class]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    /**
     * Leads, created directly.
     *
     * There is no LeadFactory in this project — leads arrive through
     * StoreLead, never from a factory — so the fixture writes the columns the
     * table actually requires rather than inventing a factory that would then
     * have to be kept in step with the real write path.
     */
    private function leads(int $count, string $crmStatus): void
    {
        for ($i = 0; $i < $count; $i++) {
            Lead::query()->create([
                'uuid' => (string) Str::uuid(),
                'contact_value' => "probe{$crmStatus}{$i}@example.test",
                'contact_type' => Lead::TYPE_EMAIL,
                'locale' => 'ar',
                'status' => 'new',
                'crm_status' => $crmStatus,
            ]);
        }
    }

    private function props(User $admin): array
    {
        return $this->actingAs($admin)
            ->get('/admin/integrations/crm')
            ->assertOk()
            ->viewData('page')['props'];
    }

    /**
     * Which CRM to use is an administrative decision the client is still
     * making. The screen offers exactly the two systems in that decision —
     * the null, webhook and log drivers exist for development and tests and
     * must never appear as something an operator could pick.
     */
    #[Test]
    public function the_screen_offers_exactly_two_providers(): void
    {
        $props = $this->props($this->admin());

        $this->assertSame(['zid', 'odoo'], $props['providers']);
        $this->assertCount(2, $props['providers']);

        foreach (['null', 'webhook', 'log', 'test'] as $internal) {
            $this->assertNotContains($internal, $props['providers'],
                "The {$internal} driver is for code and tests, not for the operator to choose.");
        }
    }

    #[Test]
    public function credentials_save_to_settings_and_override_the_environment(): void
    {
        config()->set('crm.drivers.odoo.url', 'https://from-env.example');

        $this->actingAs($this->admin())
            ->put('/admin/integrations/crm', [
                'driver' => 'odoo',
                'credentials' => [
                    'odoo' => ['url' => 'https://from-panel.example', 'database' => 'db', 'username' => 'u', 'api_key' => 'k'],
                    'zid' => ['base_url' => '', 'store_id' => '', 'access_token' => ''],
                ],
            ])
            ->assertRedirect();

        app(CrmSettings::class)->apply();

        $this->assertSame('odoo', config('crm.driver'));
        $this->assertSame('https://from-panel.example', config('crm.drivers.odoo.url'));
    }

    /**
     * The screen shows a secret as bullets. Saving the form unchanged must
     * not write those bullets over the key.
     */
    #[Test]
    public function saving_an_unchanged_secret_leaves_it_alone(): void
    {
        $admin = $this->admin();

        Setting::query()->updateOrCreate(
            ['group' => 'crm', 'key' => 'odoo.api_key'],
            ['value' => 'the-real-key'],
        );

        $this->actingAs($admin)
            ->put('/admin/integrations/crm', [
                'driver' => 'odoo',
                'credentials' => [
                    'odoo' => ['url' => 'https://x.example', 'database' => 'db', 'username' => 'u', 'api_key' => '••••••••'],
                    'zid' => ['base_url' => '', 'store_id' => '', 'access_token' => ''],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            'the-real-key',
            Setting::query()->where('group', 'crm')->where('key', 'odoo.api_key')->value('value'),
        );
    }

    #[Test]
    public function a_secret_is_never_sent_back_to_the_browser(): void
    {
        $admin = $this->admin();

        Setting::query()->updateOrCreate(
            ['group' => 'crm', 'key' => 'odoo.api_key'],
            ['value' => 'the-real-key'],
        );

        $body = $this->actingAs($admin)->get('/admin/integrations/crm')->assertOk()->getContent();

        $this->assertStringNotContainsString('the-real-key', $body,
            'The API key was rendered into the page — it would reach every screenshot and screen share.');
    }

    /**
     * The button hands off to a job. A request that looped over the backlog
     * itself would be fine at sixty leads and time out at six hundred.
     */
    #[Test]
    public function the_bulk_resend_dispatches_a_job_rather_than_looping_in_the_request(): void
    {
        Queue::fake();

        $admin = $this->admin();

        $this->leads(3, Lead::CRM_FAILED);

        $this->actingAs($admin)
            ->post('/admin/integrations/crm/resync-all')
            ->assertRedirect();

        Queue::assertPushed(ResyncFailedLeads::class);
        Queue::assertNotPushed(PushLeadToCrm::class);
    }

    /** And the job itself re-queues one push per stuck lead, in chunks. */
    #[Test]
    public function the_job_requeues_every_stuck_lead(): void
    {
        Queue::fake();

        $this->admin();

        $this->leads(4, Lead::CRM_FAILED);
        $this->leads(2, Lead::CRM_PENDING);
        $this->leads(5, Lead::CRM_SYNCED);

        (new ResyncFailedLeads)->handle();

        Queue::assertPushed(PushLeadToCrm::class, 6);
    }

    #[Test]
    public function the_status_counts_what_is_actually_stuck(): void
    {
        $admin = $this->admin();

        $this->leads(4, Lead::CRM_FAILED);
        $this->leads(2, Lead::CRM_PENDING);
        $this->leads(5, Lead::CRM_SYNCED);

        $status = $this->props($admin)['status'];

        $this->assertSame(4, $status['failed']);
        $this->assertSame(2, $status['pending']);
        $this->assertSame(5, $status['synced']);
    }

    #[Test]
    public function an_editor_cannot_open_or_change_the_connection(): void
    {
        $this->seed([RolesSeeder::class, StructureSeeder::class]);

        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole(User::ROLE_EDITOR);

        $this->actingAs($editor)->get('/admin/integrations/crm')->assertForbidden();
        $this->actingAs($editor)->put('/admin/integrations/crm', ['driver' => 'zid'])->assertForbidden();
    }
}
