<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\NotificationRecipient;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LeadDailySummary;
use App\Notifications\NewLeadReceived;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Who the site tells when an enquiry arrives (§6.2 step 3, §20 decision 4).
 *
 * The behaviour worth protecting here is not "an email is sent" — it is that
 * the RIGHT inbox is picked, and that no arrangement of this screen can leave
 * an enquiry unannounced by accident. A lead nobody hears about is the one
 * failure this site cannot absorb (§1), so the fallback, the per-event split
 * and the empty-table case each get a test of their own.
 */
class NotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function recipient(string $email, array $events = [], bool $active = true): NotificationRecipient
    {
        return NotificationRecipient::query()->create([
            'email' => $email,
            'is_active' => $active,
            'on_new_lead' => $events['new_lead'] ?? false,
            'on_crm_failure' => $events['crm_failure'] ?? false,
            'on_daily_summary' => $events['daily_summary'] ?? false,
        ]);
    }

    private function lead(): Lead
    {
        return Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'contact_value' => 'buyer@ministry.test',
            'contact_type' => Lead::TYPE_EMAIL,
            'locale' => 'ar',
            'status' => 'new',
            'crm_status' => Lead::CRM_PENDING,
        ]);
    }

    // ---- the fallback -----------------------------------------------------

    /**
     * The window this exists for: the table is new and empty, the env variable
     * still names a real inbox, and an enquiry arriving now must not be the
     * one that goes unseen.
     */
    #[Test]
    public function an_empty_table_still_alerts_whoever_the_env_names(): void
    {
        config()->set('site.leads.notify_to', ['sales@amadcraft.test']);

        $this->assertSame(
            ['sales@amadcraft.test'],
            NotificationRecipient::emailsFor(NotificationRecipient::EVENT_NEW_LEAD),
        );
    }

    /**
     * And the moment anyone is configured, the table is the whole answer —
     * including for an event nobody subscribed to. Quietly re-adding the env
     * address there would override a decision made on the screen, invisibly.
     */
    #[Test]
    public function one_recipient_makes_the_table_the_only_answer(): void
    {
        config()->set('site.leads.notify_to', ['sales@amadcraft.test']);
        $this->recipient('ops@amadcraft.test', ['crm_failure' => true]);

        $this->assertSame(
            ['ops@amadcraft.test'],
            NotificationRecipient::emailsFor(NotificationRecipient::EVENT_CRM_FAILURE),
        );

        $this->assertSame([], NotificationRecipient::emailsFor(NotificationRecipient::EVENT_NEW_LEAD));
    }

    #[Test]
    public function an_inactive_recipient_is_told_nothing(): void
    {
        $this->recipient('away@amadcraft.test', ['new_lead' => true], active: false);

        $this->assertSame([], NotificationRecipient::emailsFor(NotificationRecipient::EVENT_NEW_LEAD));
    }

    // ---- the per-event split ---------------------------------------------

    /**
     * The whole point of per-recipient flags: the person answering buyers and
     * the person maintaining the integration are not the same person, and
     * neither should be trained to ignore the other's alerts.
     */
    #[Test]
    public function an_arriving_enquiry_reaches_only_those_who_asked_for_enquiries(): void
    {
        $this->recipient('sales@amadcraft.test', ['new_lead' => true]);
        $this->recipient('ops@amadcraft.test', ['crm_failure' => true]);

        Notification::fake();

        $this->from('/ar')->post('/leads', [
            'contact' => 'buyer@ministry.test',
            'organisation' => 'وزارة',
            'phone' => '+966512345678',
        ])->assertRedirect();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            NewLeadReceived::class,
            fn ($n, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['sales@amadcraft.test'],
        );
    }

    #[Test]
    public function a_crm_failure_reaches_only_those_who_asked_for_failures(): void
    {
        $this->recipient('sales@amadcraft.test', ['new_lead' => true]);
        $this->recipient('ops@amadcraft.test', ['crm_failure' => true]);

        $this->assertSame(
            ['ops@amadcraft.test'],
            NotificationRecipient::emailsFor(NotificationRecipient::EVENT_CRM_FAILURE),
        );
    }

    // ---- the daily digest -------------------------------------------------

    #[Test]
    public function the_daily_summary_goes_only_to_its_subscribers(): void
    {
        $this->recipient('sales@amadcraft.test', ['new_lead' => true]);
        $this->recipient('manager@amadcraft.test', ['daily_summary' => true]);
        $this->lead();

        Notification::fake();

        $this->artisan('amad:daily-summary')->assertSuccessful();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            LeadDailySummary::class,
            fn ($n, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['manager@amadcraft.test'],
        );
    }

    /**
     * Nobody subscribed is a valid morning, not a failed command. Exiting
     * non-zero would put a routine quiet day in the failure log, where a real
     * fault then becomes harder to see.
     */
    #[Test]
    public function the_daily_summary_is_quiet_and_successful_when_nobody_wants_it(): void
    {
        $this->recipient('sales@amadcraft.test', ['new_lead' => true]);

        Notification::fake();

        $this->artisan('amad:daily-summary')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ---- the editable template -------------------------------------------

    #[Test]
    public function the_alert_uses_the_shipped_wording_until_the_client_writes_their_own(): void
    {
        $mail = (new NewLeadReceived($this->lead()))->toMail(new AnonymousNotifiable);

        $this->assertSame(
            __('notifications.alert_subject', ['contact' => 'buyer@ministry.test']),
            $mail->subject,
        );
    }

    /**
     * `:contact` survives a rewritten subject. Without it a list of these in
     * an inbox is fifty identical rows, and triage is impossible.
     */
    #[Test]
    public function a_written_subject_still_carries_the_contact_value(): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'notifications', 'key' => 'alert_subject'],
            ['value' => 'طلب جديد — :contact'],
        );
        app(Settings::class)->forget();

        $mail = (new NewLeadReceived($this->lead()))->toMail(new AnonymousNotifiable);

        $this->assertSame('طلب جديد — buyer@ministry.test', $mail->subject);
    }

    #[Test]
    public function the_written_opening_line_appears_above_the_enquiry(): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'notifications', 'key' => 'alert_intro'],
            ['value' => 'الردّ خلال يوم عمل.'],
        );
        app(Settings::class)->forget();

        $mail = (new NewLeadReceived($this->lead()))->toMail(new AnonymousNotifiable);

        $this->assertSame('الردّ خلال يوم عمل.', $mail->introLines[0]);
    }

    // ---- the screen -------------------------------------------------------

    #[Test]
    public function the_screen_is_real_and_warns_while_the_env_is_still_answering(): void
    {
        config()->set('site.leads.notify_to', ['sales@amadcraft.test']);

        $props = $this->actingAs($this->admin())
            ->get('/admin/integrations/notifications')->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(0, $props['recipients']);
        $this->assertSame(['sales@amadcraft.test'], $props['envFallback']);
        $this->assertSame(NotificationRecipient::EVENTS, $props['events']);
    }

    #[Test]
    public function an_address_added_in_the_panel_is_subscribed_to_enquiries_only(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/integrations/notifications', ['email' => 'new@amadcraft.test'])
            ->assertRedirect();

        $added = NotificationRecipient::query()->where('email', 'new@amadcraft.test')->sole();

        $this->assertTrue($added->on_new_lead);
        $this->assertFalse($added->on_crm_failure);
        $this->assertFalse($added->on_daily_summary);
    }

    #[Test]
    public function the_same_address_cannot_be_added_twice(): void
    {
        $this->recipient('dup@amadcraft.test');

        $this->actingAs($this->admin())
            ->post('/admin/integrations/notifications', ['email' => 'dup@amadcraft.test'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function saving_the_screen_stores_subscriptions_and_the_template(): void
    {
        $row = $this->recipient('ops@amadcraft.test', ['new_lead' => true]);

        $this->actingAs($this->admin())->put('/admin/integrations/notifications', [
            'recipients' => [[
                'id' => $row->id,
                'name' => 'Ops',
                'isActive' => true,
                'events' => ['new_lead' => false, 'crm_failure' => true, 'daily_summary' => true],
            ]],
            'template' => ['notifications.alert_subject' => 'كتبناه'],
        ])->assertRedirect();

        $row->refresh();

        $this->assertFalse($row->on_new_lead);
        $this->assertTrue($row->on_crm_failure);
        $this->assertSame('Ops', $row->name);
        $this->assertSame('كتبناه', app(Settings::class)->get('notifications.alert_subject'));
    }

    /**
     * A key this screen does not own must not be writable through it, however
     * it is posted (§9.2 — policies, not hidden inputs).
     */
    #[Test]
    public function the_screen_cannot_be_used_to_write_a_setting_it_does_not_own(): void
    {
        $this->actingAs($this->admin())->put('/admin/integrations/notifications', [
            'recipients' => [],
            'template' => ['tracking.ga4_id' => 'G-INJECTED'],
        ])->assertRedirect();

        $this->assertNotSame('G-INJECTED', app(Settings::class)->get('tracking.ga4_id'));
    }

    #[Test]
    public function an_editor_without_settings_permission_cannot_open_it(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('editor');

        $this->actingAs($user)->get('/admin/integrations/notifications')->assertForbidden();
    }
}
