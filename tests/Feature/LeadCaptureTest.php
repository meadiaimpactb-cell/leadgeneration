<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PushLeadToCrm;
use App\Models\Campaign;
use App\Models\CrmSyncLog;
use App\Models\Lead;
use App\Models\LeadField;
use App\Notifications\NewLeadReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The full lead path, which §7.4 makes mandatory:
 * submit → store → CRM → notify → confirm.
 */
class LeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests exercise the endpoint against the brief's own form —
     * contact, and nothing else compulsory (§6.1).
     *
     * TestSeeder ships the demo lead form, which switches organisation and
     * phone on and marks them required, so a submission carrying only a
     * contact is rejected before it reaches any of the behaviour under test.
     * That configuration is the client's to make and is covered by
     * LeadFormBuilderTest; here it is noise. Clearing the rows puts the
     * endpoint back on its documented floor — StoreLeadRequest still requires
     * a contact with no field rows at all — and the delete rolls back with the
     * test's transaction.
     */
    protected function setUp(): void
    {
        parent::setUp();

        LeadField::query()->delete();
    }

    #[Test]
    public function it_stores_a_lead_from_an_email(): void
    {
        Queue::fake();
        Notification::fake();

        $response = $this->from('/ar')->post('/leads', [
            'contact' => 'buyer@ministry.gov.sa',
            'message' => 'عندي مؤتمر، تواصلوا معي',
        ]);

        $response->assertRedirect('/ar');
        $response->assertSessionHas('lead_submitted', true);

        $lead = Lead::sole();

        $this->assertSame('buyer@ministry.gov.sa', $lead->contact_value);
        $this->assertSame(Lead::TYPE_EMAIL, $lead->contact_type);
        $this->assertSame('عندي مؤتمر، تواصلوا معي', $lead->message);
        $this->assertSame('new', $lead->status);
        $this->assertSame(Lead::CRM_PENDING, $lead->crm_status);
        $this->assertNotNull($lead->uuid);
    }

    #[Test]
    public function it_normalises_a_phone_number(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', ['contact' => '05 1234 5678']);

        $lead = Lead::sole();

        $this->assertSame('+966512345678', $lead->contact_value);
        $this->assertSame(Lead::TYPE_PHONE, $lead->contact_type);
    }

    #[Test]
    public function it_never_stores_a_raw_ip_address(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', ['contact' => 'a@b.sa'], ['REMOTE_ADDR' => '203.0.113.42']);

        $lead = Lead::sole();

        // §15.3: a hash, never the address itself.
        $this->assertNotNull($lead->ip_hash);
        $this->assertSame(64, strlen($lead->ip_hash));
        $this->assertStringNotContainsString('203.0.113.42', $lead->ip_hash);
    }

    #[Test]
    public function it_captures_the_marketing_source(): void
    {
        Queue::fake();
        Notification::fake();

        // firstOrCreate, not create: the demo seeder already ships a campaign
        // on this slug, and the test only needs one to exist.
        $campaign = Campaign::query()->firstOrCreate(
            ['slug' => 'riyadh-season'],
            ['is_active' => true],
        );

        $this->post('/leads', [
            'contact' => 'a@b.sa',
            'page_url' => 'https://amadcraft.sa/ar/c/riyadh-season',
            'referrer' => 'https://www.google.com/',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'riyadh-season',
            'gclid' => 'abc123',
            'campaign' => 'riyadh-season',
            'sector_hint' => 'government',
        ]);

        $lead = Lead::sole();

        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('abc123', $lead->gclid);
        $this->assertSame($campaign->id, $lead->campaign_id);
        $this->assertSame('government', $lead->sector_hint);
    }

    #[Test]
    public function it_queues_the_crm_push_and_notifies_the_team(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', ['contact' => 'a@b.sa']);

        Queue::assertPushed(PushLeadToCrm::class);
        Notification::assertSentOnDemand(NewLeadReceived::class);
    }

    #[Test]
    public function the_queued_job_syncs_the_lead_and_logs_the_attempt(): void
    {
        Notification::fake();

        // QUEUE_CONNECTION=sync in phpunit.xml, so the job runs inline here —
        // this exercises the real chain rather than only the dispatch.
        $this->post('/leads', ['contact' => 'a@b.sa']);

        $lead = Lead::sole();

        $this->assertSame(Lead::CRM_SYNCED, $lead->crm_status);
        $this->assertSame('null', $lead->crm_provider);
        $this->assertNotNull($lead->crm_synced_at);

        // Every attempt is recorded, so "it reached the CRM" is verifiable.
        $this->assertSame(1, CrmSyncLog::query()->where('lead_id', $lead->id)->count());
    }

    #[Test]
    public function it_rejects_a_contact_value_it_cannot_read(): void
    {
        Queue::fake();

        $this->from('/ar')
            ->post('/leads', ['contact' => 'اتصلوا بي'])
            ->assertSessionHasErrors('contact');

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function the_contact_field_is_required(): void
    {
        $this->from('/ar')
            ->post('/leads', ['contact' => ''])
            ->assertSessionHasErrors('contact');

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function it_silently_drops_a_honeypot_submission(): void
    {
        Queue::fake();
        Notification::fake();

        // The bot is told nothing went wrong; telling it would invite a retry.
        $this->post('/leads', [
            'contact' => 'bot@spam.example',
            'company_website' => 'http://spam.example',
        ])->assertSessionHasErrors(config('site.leads.honeypot_field'));

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function it_drops_a_submission_completed_impossibly_fast(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', [
            'contact' => 'bot@spam.example',
            'started_at' => now()->getTimestampMs(),
        ])->assertSessionHas('lead_submitted', true);

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function it_accepts_a_submission_completed_at_human_speed(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', [
            'contact' => 'person@company.sa',
            'started_at' => now()->subSeconds(9)->getTimestampMs(),
        ]);

        $this->assertSame(1, Lead::query()->count());
    }

    #[Test]
    public function it_collapses_a_pasted_multiline_message_to_one_line(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', [
            'contact' => 'a@b.sa',
            'message' => "  سطر أول \n\n  سطر ثانٍ  ",
        ]);

        $this->assertSame('سطر أول سطر ثانٍ', Lead::sole()->message);
    }

    #[Test]
    public function it_throttles_repeated_submissions_from_one_address(): void
    {
        Queue::fake();
        Notification::fake();

        $limit = (int) config('site.leads.rate_limit');

        for ($i = 0; $i <= $limit; $i++) {
            $response = $this->from('/ar')->post('/leads', ['contact' => "person{$i}@company.sa"]);
        }

        // The one past the limit is turned away rather than stored.
        $this->assertSame($limit, Lead::query()->count());
        $response->assertSessionHasErrors('contact');
    }
}
