<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Notifications\CrmSyncFailed;
use App\Services\Crm\CrmManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Sends a lead to the CRM immediately after it is stored (§6.2 step 2).
 *
 * Retries with the exponential backoff in config/crm.php. A permanent failure
 * — bad credentials, rejected payload — is not retried; it raises the alert
 * straight away, because retrying it five times only delays the human who
 * needs to fix it (§6.3).
 */
class PushLeadToCrm implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $leadId) {}

    public function tries(): int
    {
        return (int) config('crm.retry.attempts', 5);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return config('crm.retry.backoff', [30, 120, 600, 1800, 7200]);
    }

    public function handle(CrmManager $crm): void
    {
        $lead = Lead::query()->with('campaign')->find($this->leadId);

        if ($lead === null || $lead->crm_status === Lead::CRM_SYNCED) {
            return;
        }

        $result = $crm->push($lead, $this->attempts());

        if ($result->success) {
            return;
        }

        if (! $result->retryable) {
            // Bad credentials or a rejected payload will fail identically on
            // every retry. Stop now and alert a human. fail() marks the job
            // failed without burning the remaining attempts.
            $this->giveUp($lead, $result->error ?? 'Permanent CRM failure.');
            $this->fail($result->error ?? 'Permanent CRM failure.');

            return;
        }

        // Throwing hands the job back to the queue, which re-runs it after
        // backoff()[attempt]. The final attempt lands in failed().
        throw new RuntimeException(
            "CRM push failed for lead {$lead->uuid}: ".($result->error ?? 'unknown error')
        );
    }

    public function failed(?Throwable $e): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead !== null && $lead->crm_status !== Lead::CRM_FAILED) {
            $this->giveUp($lead, $e?->getMessage() ?? 'CRM push exhausted its retries.');
        }
    }

    private function giveUp(Lead $lead, string $reason): void
    {
        $lead->markCrmFailed((string) config('crm.driver'));

        Log::error('Lead never reached the CRM.', [
            'lead_uuid' => $lead->uuid,
            'provider' => config('crm.driver'),
            'reason' => $reason,
        ]);

        $recipients = config('site.leads.notify_to', []);

        if ($recipients !== []) {
            Notification::route('mail', $recipients)
                ->notify(new CrmSyncFailed($lead, $reason));
        }
    }
}
