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

    /**
     * Prove the saved credentials actually reach the provider.
     *
     * `isConfigured()` answers "are the boxes filled in"; this answers "does
     * the key work", which is the only question the connection screen is
     * there to settle. A wrong token, an expired token and a token for the
     * wrong store all pass the first check and fail this one.
     *
     * Must be read-only. A verification that creates a record in the client's
     * live CRM is a verification nobody dares run twice, so it stops being a
     * check and becomes a thing people avoid.
     *
     * `externalId` carries whatever the provider said identifies the account
     * it just authenticated — a store name, a user — so the panel can show
     * *which* account answered, not merely that something did.
     */
    public function verify(): CrmResult;
}
