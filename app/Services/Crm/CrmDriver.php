<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Lead;

/**
 * The contract every CRM integration implements (§6.3).
 *
 * Nothing outside app/Services/Crm/Drivers may know which CRM is active.
 * Swapping Odoo for Zid is a change to CRM_DRIVER, never a change to code.
 */
interface CrmDriver
{
    public function pushLead(Lead $lead): CrmResult;

    /** Machine name used in logs and on the lead record. */
    public function name(): string;

    /**
     * Whether the driver has everything it needs to run. A driver that is
     * not configured must say so rather than failing at request time.
     */
    public function isConfigured(): bool;
}
