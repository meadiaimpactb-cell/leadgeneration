<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-queue every lead that never reached the CRM.
 *
 * A job, not a loop inside the request that pressed the button. The control
 * exists to clear a backlog, and a backlog big enough to need a button is big
 * enough to time the request out — sixty stuck leads is fine inline, six
 * hundred is not, and the difference is invisible until the day it isn't.
 *
 * It walks the backlog in chunks and dispatches one `PushLeadToCrm` per lead,
 * so each keeps its own retry policy and its own row in `crm_sync_logs`. This
 * job re-queues; it does not push.
 */
class ResyncFailedLeads implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Rows read per query. Small enough not to hold the table, big enough
     *  not to spend the run on round trips. */
    private const CHUNK = 200;

    public int $tries = 1;

    /** @param  int|null  $requestedBy  the admin who pressed the button. */
    public function __construct(public readonly ?int $requestedBy = null) {}

    public function handle(): void
    {
        $queued = 0;

        Lead::query()
            ->notSynced()
            ->select('id')
            ->chunkById(self::CHUNK, function ($leads) use (&$queued): void {
                foreach ($leads as $lead) {
                    PushLeadToCrm::dispatch($lead->id);
                    $queued++;
                }
            });

        /*
         * Reported, not silent. 2.3 will turn this into a notification to the
         * recipients the client configures; until that screen exists the log
         * is where the answer lives, and writing it now means the wiring in
         * 2.3 is a one-line change rather than a rediscovery.
         */
        Log::info('CRM backlog re-queued.', [
            'leads' => $queued,
            'requested_by' => $this->requestedBy,
        ]);
    }
}
