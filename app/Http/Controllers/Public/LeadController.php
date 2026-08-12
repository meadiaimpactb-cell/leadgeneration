<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Leads\StoreLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Jobs\PushLeadToCrm;
use App\Models\LeadField;
use App\Notifications\NewLeadReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

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

        // Immediately (§6.2 step 2) — queued so the visitor is not made to
        // wait on a third-party API before seeing their confirmation.
        PushLeadToCrm::dispatch($lead->id);

        $recipients = config('site.leads.notify_to', []);

        if ($recipients !== []) {
            Notification::route('mail', $recipients)
                ->notify(new NewLeadReceived($lead));
        }

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
}
