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
use Illuminate\Support\Facades\Http;
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
    private function leads(int $count, string $crmStatus, ?string $provider = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            Lead::query()->create([
                'uuid' => (string) Str::uuid(),
                'contact_value' => "probe{$crmStatus}{$i}@example.test",
                'contact_type' => Lead::TYPE_EMAIL,
                'locale' => 'ar',
                'status' => 'new',
                'crm_status' => $crmStatus,
                'crm_provider' => $provider,
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

    /**
     * The `null` driver reports success without sending anything anywhere.
     *
     * Counting its rows as delivered put a green figure on the one screen
     * whose entire job is to say whether the connection works — five
     * enquiries read as "sent" on a site with no CRM attached. They are
     * waiting, not delivered, and the screen must say so.
     */
    #[Test]
    public function a_lead_the_null_stub_swallowed_is_not_counted_as_delivered(): void
    {
        $this->leads(5, Lead::CRM_SYNCED, 'null');
        $this->leads(2, Lead::CRM_SYNCED, 'odoo');
        $this->leads(1, Lead::CRM_FAILED, 'odoo');

        $status = $this->props($this->admin())['status'];

        $this->assertSame(2, $status['synced'], 'Only the two Odoo actually left the building.');
        $this->assertSame(5, $status['pending'], 'The five the stub swallowed are still waiting.');
        $this->assertSame(1, $status['failed']);
    }

    /**
     * …and the day a real provider is connected, the resend button must pick
     * those five up. Before this they were stamped `synced` forever and every
     * resend stepped straight over them.
     */
    #[Test]
    public function the_backlog_includes_what_the_stub_swallowed(): void
    {
        $this->leads(5, Lead::CRM_SYNCED, 'null');
        $this->leads(3, Lead::CRM_SYNCED, 'zid');

        $this->assertSame(5, Lead::query()->notSynced()->count());
    }

    /**
     * Zid's access token is a JWT — the real one runs well past a thousand
     * characters. A flat max:512 over every credential meant pasting it failed
     * validation, so the Zid connection could not be saved at all.
     */
    #[Test]
    public function a_zid_access_token_the_length_of_a_real_jwt_saves(): void
    {
        $token = 'eyJ'.str_repeat('a', 1400);

        $this->actingAs($this->admin())
            ->put('/admin/integrations/crm', [
                'driver' => 'zid',
                'credentials' => [
                    'zid' => ['base_url' => 'https://api.zid.sa', 'store_id' => '1200977', 'access_token' => $token],
                    'odoo' => ['url' => '', 'database' => '', 'username' => '', 'api_key' => ''],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            $token,
            Setting::query()->where('group', 'crm')->where('key', 'zid.access_token')->value('value'),
        );
    }

    /**
     * A token copied out of a dashboard arrives with a newline on it more often
     * than not, and Zid answers that with the same 401 as a wrong token.
     */
    #[Test]
    public function whitespace_around_a_pasted_credential_is_stripped(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/integrations/crm', [
                'driver' => 'zid',
                'credentials' => [
                    'zid' => ['base_url' => " https://api.zid.sa \n", 'store_id' => " 1200977\n", 'access_token' => "  a-token\n"],
                    'odoo' => ['url' => '', 'database' => '', 'username' => '', 'api_key' => ''],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('1200977', Setting::query()->where('group', 'crm')->where('key', 'zid.store_id')->value('value'));
        $this->assertSame('a-token', Setting::query()->where('group', 'crm')->where('key', 'zid.access_token')->value('value'));
    }

    /**
     * Zid wants the store token under BOTH `Authorization` and
     * `X-Manager-Token`. Sending only the first — which is what this driver did
     * — is a 401 whatever the token says, and reads on the screen as a bad
     * credential rather than a missing header.
     */
    #[Test]
    public function the_zid_call_carries_the_token_under_both_header_names(): void
    {
        Http::fake([
            'api.zid.sa/*' => Http::response(['user' => ['store' => ['id' => 1200977, 'title' => 'Amad Craft']]], 200),
        ]);

        $this->connectZid('the-token');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/crm/test')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer the-token')
            && $request->hasHeader('X-Manager-Token', 'the-token')
            && str_contains($request->url(), '/v1/managers/account/profile'));
    }

    /**
     * The test button used to check that four boxes were non-empty and then
     * report "fully configured" — which a client reads as "it works". A dead
     * token passed it.
     */
    #[Test]
    public function a_rejected_token_fails_the_connection_test(): void
    {
        Http::fake(['api.zid.sa/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->connectZid('a-stale-token');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/crm/test')
            ->assertSessionMissing('success')
            ->assertSessionHas('error');
    }

    /**
     * Zid puts `message` back as a string on some endpoints and as a bag of
     * per-field arrays on others. Reading it as a string threw a TypeError, and
     * the screen then showed the PHP error instead of what Zid actually said —
     * hiding the one sentence that explains the refusal.
     */
    #[Test]
    public function a_structured_error_body_reaches_the_screen_as_words(): void
    {
        Http::fake([
            'api.zid.sa/*' => Http::response([
                'status' => 'error',
                'message' => ['access_token' => ['The token has expired.']],
            ], 401),
        ]);

        $this->connectZid('an-expired-token');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/crm/test')
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'The token has expired.')
                && ! str_contains($error, 'TypeError'));
    }

    /** A non-JSON body — an HTML error page — must not crash it either. */
    #[Test]
    public function an_html_error_page_does_not_crash_the_connection_test(): void
    {
        Http::fake(['api.zid.sa/*' => Http::response('<html><body>Bad gateway</body></html>', 502)]);

        $this->connectZid('the-token');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/crm/test')
            ->assertSessionHas('error');
    }

    /**
     * And a token that works but belongs to a different Zid store is not a
     * working connection — that is the mix-up this screen exists to catch.
     */
    #[Test]
    public function a_token_for_another_store_fails_the_connection_test(): void
    {
        Http::fake([
            'api.zid.sa/*' => Http::response(['user' => ['store' => ['id' => 999, 'title' => 'Someone Else']]], 200),
        ]);

        $this->connectZid('a-valid-token');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/crm/test')
            ->assertSessionMissing('success')
            ->assertSessionHas('error');
    }

    /** The connection test must never write anything into the client's CRM. */
    #[Test]
    public function the_connection_test_only_reads(): void
    {
        Http::fake([
            'api.zid.sa/*' => Http::response(['user' => ['store' => ['id' => 1200977, 'title' => 'Amad Craft']]], 200),
        ]);

        $this->connectZid('the-token');

        $this->actingAs($this->admin())->post('/admin/integrations/crm/test');

        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    /** Saves a working-looking Zid connection straight into settings. */
    private function connectZid(string $token): void
    {
        foreach (['driver' => 'zid', 'zid.base_url' => 'https://api.zid.sa', 'zid.store_id' => '1200977', 'zid.access_token' => $token] as $key => $value) {
            Setting::query()->updateOrCreate(['group' => 'crm', 'key' => $key], ['value' => $value]);
        }
    }

    /**
     * The list screen cannot tell the truth about a lead without knowing which
     * provider "synced" it, so the row carries the provider.
     */
    #[Test]
    public function the_leads_row_carries_the_provider_that_synced_it(): void
    {
        $this->leads(1, Lead::CRM_SYNCED, 'null');

        $row = $this->actingAs($this->admin())
            ->get('/admin/leads')
            ->assertOk()
            ->viewData('page')['props']['leads']['data'][0];

        $this->assertSame('null', $row['crmProvider']);
        $this->assertSame(Lead::CRM_SYNCED, $row['crmStatus']);
    }
}
