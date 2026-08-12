<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Support\ContactValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Persist a lead and start the chain in §6.2.
 *
 * The whole point of this action is that the visitor's part finishes fast:
 * the row is written, the queue takes the CRM push and the notifications, and
 * the browser gets its confirmation in place (§10.6).
 */
class StoreLead
{
    /**
     * @param  array<string, mixed>  $attribution
     */
    /**
     * @param  array<string, mixed>  $attribution
     * @param  array<string, mixed>  $extra  answers to any admin-enabled field
     *                                       beyond the two §6.1 defines
     */
    public function handle(
        ContactValue $contact,
        ?string $message,
        array $attribution,
        Request $request,
        array $extra = [],
    ): Lead {
        $campaignId = $this->resolveCampaignId($attribution['campaign'] ?? null);

        $lead = DB::transaction(fn (): Lead => Lead::create([
            'contact_value' => $contact->value,
            'contact_type' => $contact->type,
            'message' => $this->cleanMessage($message),
            'extra' => $extra === [] ? null : $extra,
            'locale' => app()->getLocale(),

            'page_url' => $attribution['page_url'] ?? null,
            'referrer' => $attribution['referrer'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'gclid' => $attribution['gclid'] ?? null,
            'fbclid' => $attribution['fbclid'] ?? null,
            'campaign_id' => $campaignId,
            'sector_hint' => $attribution['sector_hint'] ?? null,
            // Which audience's button produced this — see the column's own
            // migration. Qualification the visitor was never asked to do.
            'interest' => $attribution['interest'] ?? null,

            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'ip_hash' => self::hashIp($request->ip()),

            'status' => 'new',
            'crm_status' => Lead::CRM_PENDING,
        ]));

        return $lead;
    }

    /**
     * Salted SHA-256 of the address. §15.3 requires a hash and forbids
     * storing the raw IP; without the salt a hashed IPv4 would be trivially
     * reversible by brute force over the whole address space.
     */
    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash(
            'sha256',
            config('app.key').'|'.config('site.leads.ip_hash_salt').'|'.$ip
        );
    }

    private function resolveCampaignId(?string $slug): ?int
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return Campaign::query()->where('slug', $slug)->value('id');
    }

    /**
     * The message is one line by design (§6.1). Collapse anything the visitor
     * pasted so a wall of text cannot land in the sales team's inbox.
     */
    private function cleanMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $collapsed = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        return $collapsed === '' ? null : $collapsed;
    }
}
