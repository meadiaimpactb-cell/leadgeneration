<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PushLeadToCrm;
use App\Models\Lead;
use App\Models\LeadField;
use App\Models\NotificationRecipient;
use App\Notifications\NewLeadReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The one endpoint that matters, when the things behind it are broken (§1, §6.2).
 *
 * A lead is committed first and everything else follows: the queued CRM push,
 * the team's alert, the sender's own confirmation. All three depend on
 * infrastructure the visitor has no part in, and all three can throw — a queue
 * that cannot be reached throws on dispatch, a mail transport rejected at
 * handoff throws on notify.
 *
 * Before the boundary in LeadController existed, that threw out of the action
 * and the visitor got a 500. Measured, with the queue unreachable: the response
 * was 500 and the row was in the table. The enquiry had arrived and the buyer
 * had been told it had not — which is the worst outcome available to a site
 * whose single metric is enquiries received, because the person who sent it
 * either sends it again or goes somewhere else.
 *
 * These tests hold the line: the enquiry is kept, the visitor is told so, and
 * the failure surfaces where the team will find it rather than in front of a
 * customer.
 */
class ALeadSurvivesWhatComesAfterItTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The demo form asks for an organisation and a phone number; §6.1's own
     * form asks for a contact and nothing else, and that is what this endpoint
     * is being tested against. See LeadCaptureTest for the same note.
     */
    protected function setUp(): void
    {
        parent::setUp();

        LeadField::query()->delete();
        Lead::query()->delete();
    }

    /** @return TestResponse */
    private function submit()
    {
        return $this->from('/ar')->post('/leads', [
            'contact' => 'buyer@ministry.gov.sa',
            'message' => 'استفسار',
        ]);
    }

    #[Test]
    public function an_unreachable_queue_does_not_cost_the_visitor_their_confirmation(): void
    {
        Notification::fake();
        Queue::shouldReceive('push')->andThrow(new RuntimeException('Queue unreachable'));
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('Queue unreachable'));

        $this->submit()
            ->assertRedirect('/ar')
            ->assertSessionHas('lead_submitted', true);

        $this->assertSame(1, Lead::query()->count(),
            'The enquiry itself must still be kept.');
    }

    /**
     * A push that never reached the queue never happened, and the lead must
     * not sit marked «pending» for ever waiting on a job that does not exist.
     * Marking it failed is what puts it in front of somebody: the panel's CRM
     * column, the morning digest's «not synced» count, and «resync all».
     */
    #[Test]
    public function a_push_that_never_reached_the_queue_is_recorded_as_failed(): void
    {
        Notification::fake();
        Queue::shouldReceive('push')->andThrow(new RuntimeException('Queue unreachable'));
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('Queue unreachable'));

        $this->submit();

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::CRM_FAILED, $lead->crm_status,
            'A lead whose push never reached the queue would otherwise stay pending, unseen, for ever.');
        $this->assertSame(1, Lead::query()->notSynced()->count(),
            'The morning digest must be able to see it.');
    }

    /**
     * A mail server refusing at handoff must not undo the enquiry either — and
     * must not stop the CRM push, which is a different system entirely.
     */
    #[Test]
    public function a_failing_mail_server_costs_neither_the_lead_nor_the_crm_push(): void
    {
        NotificationRecipient::query()->create([
            'email' => 'sales@amadcraft.test',
            'is_active' => true,
            'on_new_lead' => true,
        ]);

        Queue::fake();
        Notification::shouldReceive('route')->andThrow(new RuntimeException('Mail transport refused'));

        $this->submit()
            ->assertRedirect('/ar')
            ->assertSessionHas('lead_submitted', true);

        $this->assertSame(1, Lead::query()->count());

        // The alert and the confirmation are separate steps from the push, so
        // a broken mail server must leave the CRM path untouched.
        Queue::assertPushed(PushLeadToCrm::class);
    }

    /**
     * The boundary must not become a blanket. With everything working the
     * endpoint still does all of its work — otherwise a try/catch that quietly
     * ate a real failure would pass every test above.
     */
    #[Test]
    public function nothing_is_swallowed_when_the_infrastructure_is_healthy(): void
    {
        NotificationRecipient::query()->create([
            'email' => 'sales@amadcraft.test',
            'is_active' => true,
            'on_new_lead' => true,
        ]);

        Queue::fake();
        Notification::fake();

        $this->submit()->assertSessionHas('lead_submitted', true);

        $lead = Lead::query()->sole();

        $this->assertSame(Lead::CRM_PENDING, $lead->crm_status,
            'A healthy dispatch must leave the lead pending for the job, not marked failed.');

        Queue::assertPushed(PushLeadToCrm::class);
        Notification::assertSentTo(
            new AnonymousNotifiable,
            NewLeadReceived::class,
        );
    }
}
