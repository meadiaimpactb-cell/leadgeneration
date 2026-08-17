<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;
use App\Services\Crm\CrmDriver;
use App\Services\Crm\CrmResult;

/**
 * Local development driver (§6.3).
 *
 * Records the attempt and reports success without leaving the machine, so the
 * full lead chain — store, notify, sync, confirm — can be exercised offline.
 *
 * Never set CRM_DRIVER=null in production: §6.3 is explicit that leads
 * reaching only email, with no organised follow-up, is not acceptable.
 */
class NullCrmDriver implements CrmDriver
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * There is nothing to verify, and saying otherwise is the failure mode
     * this driver invites: a green "connected" on a site connected to nothing.
     * The screen already counts the leads it swallowed as waiting rather than
     * delivered — this keeps the connection test telling the same story.
     */
    public function verify(): CrmResult
    {
        return CrmResult::failure(
            error: 'No CRM is connected. Nothing was contacted, and no enquiry has left this site.',
            retryable: false,
        );
    }

    public function pushLead(Lead $lead): CrmResult
    {
        return CrmResult::success(
            externalId: 'local-'.$lead->uuid,
            request: ['note' => 'NullCrmDriver — nothing was sent anywhere.'],
        );
    }
}
