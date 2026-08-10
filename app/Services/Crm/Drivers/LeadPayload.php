<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;

/**
 * The canonical shape of a lead as sent to any CRM.
 *
 * Shared by the drivers so that what the sales team sees is identical
 * whichever provider is active — the point of the abstraction (§6.3).
 */
trait LeadPayload
{
    /** @return array<string, mixed> */
    protected function payload(Lead $lead): array
    {
        return [
            'reference' => $lead->uuid,
            'contact' => $lead->contact_value,
            'contact_type' => $lead->contact_type,
            'message' => $lead->message,
            'locale' => $lead->locale,
            'submitted_at' => $lead->created_at?->toIso8601String(),
            'source' => [
                'page_url' => $lead->page_url,
                'referrer' => $lead->referrer,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'utm_term' => $lead->utm_term,
                'utm_content' => $lead->utm_content,
                'gclid' => $lead->gclid,
                'fbclid' => $lead->fbclid,
                'campaign' => $lead->campaign?->slug,
                'sector_hint' => $lead->sector_hint,
            ],
        ];
    }

    /**
     * A one-line human summary for CRMs that show a subject/title field.
     */
    protected function subject(Lead $lead): string
    {
        $origin = $lead->campaign?->slug
            ?? $lead->sector_hint
            ?? $lead->utm_campaign
            ?? 'website';

        return "Amad Craft — {$origin} — {$lead->contact_value}";
    }
}
