<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The audit trail (§9.1, §15.3).
 *
 * Two things are being protected here and they pull in opposite directions.
 * The trail has to be COMPLETE — a change nobody recorded is a change nobody
 * can answer for — and it has to be SAFE, because the moment it records the
 * values it observes it becomes a second, permanent copy of every secret and
 * every enquirer's details, readable by more people than the records it came
 * from. Most of these tests are about the second half.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Start from an empty trail.
         *
         * The seeders write dozens of settings rows, and the audit trait
         * records every one of them — correctly, but it means a test asking
         * "what was logged" would be answering about the fixture rather than
         * about the action under test.
         */
        Activity::query()->delete();
    }

    /**
     * The rows the screen actually renders.
     *
     * The prop is a paginator, so it is decoded rather than indexed: what the
     * browser receives is the JSON shape, and asserting against that is
     * asserting against what the client sees.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $props): array
    {
        return json_decode(json_encode($props['entries']), true)['data'] ?? [];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    // ---- it records ------------------------------------------------------

    #[Test]
    public function editing_content_is_recorded_against_the_person_who_did_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $solution = Solution::query()->create(['slug' => 'audit-probe', 'is_active' => true]);
        $solution->forceFill(['is_active' => false])->save();

        $entry = Activity::query()
            ->where('subject_type', Solution::class)
            ->where('event', 'updated')
            ->sole();

        $this->assertSame($admin->id, $entry->causer_id);
        $this->assertContains(
            'is_active',
            array_keys((array) $entry->properties->get('attributes', [])),
        );
    }

    #[Test]
    public function signing_in_and_failing_to_sign_in_are_both_recorded(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-on-purpose']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertTrue(Activity::query()->where('event', 'login_failed')->exists());
        $this->assertTrue(Activity::query()->where('event', 'login')->exists());
    }

    /**
     * A failed attempt records WHO was targeted, never what was typed at the
     * password box. The email is the part that makes a run of failures
     * legible; the password is the part that must never be written down.
     */
    #[Test]
    public function a_failed_sign_in_records_the_email_and_never_the_password(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'hunter2-do-not-log']);

        $entry = Activity::query()->where('event', 'login_failed')->sole();

        $this->assertSame($admin->email, $entry->properties->get('email'));
        $this->assertStringNotContainsString('hunter2-do-not-log', json_encode($entry->properties));
    }

    #[Test]
    public function exporting_the_leads_list_is_recorded(): void
    {
        $this->actingAs($this->admin())->get('/admin/leads/export')->assertOk();

        $entry = Activity::query()->where('event', 'exported')->sole();

        $this->assertSame('leads', $entry->log_name);
        $this->assertSame(0, $entry->properties->get('rows'));
    }

    // ---- it does not leak -------------------------------------------------

    /**
     * The single most important test on this screen.
     *
     * `settings` holds the GA4 ID, the Meta CAPI token and the CRM
     * credentials. If a change to one of them recorded the new value, the
     * audit trail would become an un-rotatable store of every key on the
     * site — and one that more administrators can read than can read the
     * settings screen itself.
     */
    #[Test]
    public function a_settings_change_records_the_key_and_never_the_value(): void
    {
        $this->actingAs($this->admin());

        Setting::query()
            ->where('group', 'tracking')->where('key', 'ga4_id')->sole()
            ->forceFill(['value' => 'G-SECRET-VALUE'])->save();

        $entry = Activity::query()->where('subject_type', Setting::class)->sole();

        $this->assertStringNotContainsString('G-SECRET-VALUE', json_encode($entry->properties));
        $this->assertSame('tracking.ga4_id', $entry->properties->get('key'));
    }

    /** And the screen must not print one either, whatever a row happens to hold. */
    #[Test]
    public function the_screen_never_renders_a_recorded_value(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // A row deliberately written the wrong way, as a model added later
        // and given the default trait by mistake would write it.
        activity('probe')->causedBy($admin)->event('updated')
            ->withProperties(['attributes' => ['token' => 'LEAKED-SECRET']])
            ->log('updated');

        $props = $this->get('/admin/activity')->assertOk()->viewData('page')['props'];

        $this->assertStringNotContainsString('LEAKED-SECRET', json_encode($props));
        // The field NAME still reaches the screen — that is the audit answer.
        $this->assertStringContainsString('token', json_encode($props));
    }

    // ---- the screen -------------------------------------------------------

    #[Test]
    public function the_screen_is_real_and_filters_by_user_and_event(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['is_active' => true]);

        activity('probe')->causedBy($admin)->event('created')->log('created');
        activity('probe')->causedBy($other)->event('deleted')->log('deleted');

        $props = $this->actingAs($admin)
            ->get('/admin/activity?user='.$admin->id)->assertOk()
            ->viewData('page')['props'];

        $events = collect($this->rows($props))->pluck('event')->all();

        $this->assertContains('created', $events);
        $this->assertNotContains('deleted', $events);
    }

    /**
     * A row outlives its subject — deleting a page is the event most worth
     * auditing, and afterwards there is nothing left to load. The screen must
     * render it from what it stored rather than trying to resolve it.
     */
    #[Test]
    public function a_row_survives_the_deletion_of_what_it_describes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $solution = Solution::query()->create(['slug' => 'gone-tomorrow', 'is_active' => true]);
        $id = $solution->id;
        $solution->forceDelete();

        $props = $this->get('/admin/activity')->assertOk()->viewData('page')['props'];
        $row = collect($this->rows($props))->firstWhere('subjectId', $id);

        $this->assertNotNull($row);
        $this->assertSame('Solution', $row['subjectType']);
    }

    /**
     * Supervisory, not editorial. An editor being able to read the trail of
     * everyone above them — including failed sign-ins against their
     * colleagues' accounts — is not what §9.1's tiered permissions describe.
     */
    #[Test]
    public function an_editor_cannot_open_the_trail(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('editor');

        $this->actingAs($user)->get('/admin/activity')->assertForbidden();
    }

    /**
     * There is no route that can alter or remove a row. A log the person who
     * acted can edit is not a log, and this is the test that keeps a
     * well-meaning "clear old entries" button from ever being added quietly.
     */
    #[Test]
    public function nothing_in_the_panel_can_delete_a_row(): void
    {
        $admin = $this->admin();

        activity('probe')->causedBy($admin)->event('created')->log('created');
        $id = Activity::query()->latest('id')->firstOrFail()->id;

        $this->actingAs($admin)->delete('/admin/activity/'.$id)->assertNotFound();
        $this->actingAs($admin)->put('/admin/activity/'.$id)->assertNotFound();

        $this->assertTrue(Activity::query()->whereKey($id)->exists());
    }
}
