<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Leads\StoreLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\PushLeadToCrm;
use App\Models\Lead;
use App\Models\LeadField;
use App\Models\NotificationRecipient;
use App\Notifications\LeadConfirmation;
use App\Notifications\NewLeadReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The one endpoint that matters (§1, §6).
 *
 * Returns a redirect-back rather than JSON so that Inertia settles the
 * confirmation in place with no page transition (§10.6), and so the form
 * still works if JavaScript fails.
 */
class LeadController extends Controller
{
    public function store(StoreLeadRequest $request, StoreLead $storeLead): RedirectResponse
    {
        // Silently accept obvious bots: a bot told it failed just retries.
        if ($this->looksAutomated($request)) {
            return back()->with('lead_submitted', true);
        }

        // Belt and braces. Validation already guarantees this, and a lead
        // without a contact value is worthless — it must never be stored.
        if ($request->contact === null) {
            return back()->withErrors([LeadField::KEY_CONTACT => __('leads.contact_invalid')]);
        }

        $lead = $storeLead->handle(
            contact: $request->contact,
            message: $request->input(LeadField::KEY_MESSAGE),
            attribution: $request->safe()->only([
                'page_url', 'referrer',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'gclid', 'fbclid', 'campaign', 'sector_hint', 'interest',
            ]),
            request: $request,
            // Answers to any field the client enabled beyond §6.1's two.
            extra: $request->extraAnswers(),
        );

        /*
         * ── Everything past this line is downstream of a lead that is already
         *    safe, and none of it may cost the visitor their confirmation. ──
         *
         * The row is committed. What follows — reaching the queue, reaching a
         * mail server — depends on infrastructure the visitor has no part in,
         * and each of these calls can throw: a queue that cannot be reached
         * throws on dispatch, a mail transport rejected at handoff throws on
         * notify.
         *
         * Before this boundary existed, that threw straight out of the action
         * and the visitor got a 500. Measured: with the queue unreachable, the
         * response was 500 and the lead was in the table — the enquiry
         * arrived, the buyer was told it had not, and §1's one metric records
         * a lead that the person who sent it believes was lost. Resending is
         * the best case; going elsewhere is the likely one.
         *
         * So each step is allowed to fail on its own without taking the
         * others, or the confirmation, with it. Nothing is silently swallowed:
         * every failure is logged against the lead's uuid, and a CRM push that
         * never reached the queue marks the lead failed so it surfaces in the
         * panel, in the morning digest's «not synced» count, and to «resync
         * all» — the same places a CRM rejection surfaces.
         */

        // Immediately (§6.2 step 2) — queued so the visitor is not made to
        // wait on a third-party API before seeing their confirmation.
        $this->downstream('crm push', $lead, function () use ($lead): void {
            PushLeadToCrm::dispatch($lead->id);
        }, onFailure: function () use ($lead): void {
            // Never queued means never pushed. Say so where it will be seen,
            // rather than leaving the lead marked pending for ever.
            $lead->markCrmFailed((string) config('crm.driver'));
        });

        // From the panel now, not the server's .env — see the recipients
        // table. Queued, so the visitor never waits on a mail server.
        $this->downstream('team alert', $lead, function () use ($lead): void {
            $recipients = NotificationRecipient::emailsFor(NotificationRecipient::EVENT_NEW_LEAD);

            if ($recipients !== []) {
                Notification::route('mail', $recipients)
                    ->notify(new NewLeadReceived($lead));
            }
        });

        /*
         * And the sender's own confirmation (§6.2 step 4).
         *
         * Only to an email address — a mobile number would need an SMS gateway
         * that is not contracted. The notification decides for itself whether
         * to send at all: with no wording written in the panel it sends
         * nothing rather than an empty message in the company's name.
         */
        $this->downstream('sender confirmation', $lead, function () use ($lead): void {
            if ($lead->contact_type === 'email') {
                Notification::route('mail', $lead->contact_value)
                    ->notify(new LeadConfirmation($lead));
            }
        });

        return back()->with([
            'lead_submitted' => true,
            'lead_uuid' => $lead->uuid,
        ]);
    }

    /**
     * Timing guard (§6.1). The honeypot itself is enforced by the
     * `prohibited` rule in StoreLeadRequest.
     */
    private function looksAutomated(StoreLeadRequest $request): bool
    {
        $startedAt = $request->integer('started_at');

        if ($startedAt <= 0) {
            return false;
        }

        $elapsed = now()->getTimestampMs() - $startedAt;

        return $elapsed < config('site.leads.min_fill_seconds') * 1000;
    }

    /**
     * Run one step that happens after the lead is already safe, and do not let
     * it reach the visitor.
     *
     * This is the whole of the boundary, and its narrowness is the point. It
     * wraps only work that is downstream of a committed row — never the
     * validation, never the write. A failure here means the enquiry was
     * captured and something afterwards did not happen, which is a problem for
     * the team to fix, not a reason to tell the buyer their message was lost.
     *
     * `Throwable`, not `Exception`: a TypeError inside a notification is
     * exactly as capable of costing the confirmation, and exactly as much
     * somebody else's problem to fix.
     *
     * The log line is the contract. Swallowing without it would trade a loud
     * failure for a silent one, which is the worse of the two.
     *
     * @param  callable():void  $step
     * @param  (callable():void)|null  $onFailure  recovery that must itself not throw
     */
    private function downstream(string $what, Lead $lead, callable $step, ?callable $onFailure = null): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::error("Lead stored, but the {$what} failed.", [
                'lead_uuid' => $lead->uuid,
                'step' => $what,
                'reason' => $e->getMessage(),
            ]);

            if ($onFailure === null) {
                return;
            }

            try {
                $onFailure();
            } catch (Throwable $recovery) {
                // The database is the thing that has gone, most likely. There
                // is nowhere left to record it but the log.
                Log::error("Recovery from a failed {$what} also failed.", [
                    'lead_uuid' => $lead->uuid,
                    'reason' => $recovery->getMessage(),
                ]);
            }
        }
    }
}
