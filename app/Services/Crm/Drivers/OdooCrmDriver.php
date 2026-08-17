<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;
use App\Services\Crm\CrmDriver;
use App\Services\Crm\CrmResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Odoo CRM driver (§6.3 — "the site must be ready to integrate with Odoo at
 * launch").
 *
 * Speaks Odoo's JSON-RPC endpoint: authenticate to obtain a uid, then call
 * `crm.lead.create`. Authentication is per-push rather than cached because a
 * lead push is rare (a handful per day) and a stale uid would silently drop
 * leads — the wrong trade for the site's only success metric.
 *
 * NOTE FOR HANDOVER: this driver is written against Odoo's documented
 * external API but has NOT been tested against Amad Craft's live Odoo
 * instance — those credentials are not available yet (§20 decision 1). Field
 * mapping must be confirmed against the real instance before go-live.
 */
class OdooCrmDriver implements CrmDriver
{
    use LeadPayload;

    public function name(): string
    {
        return 'odoo';
    }

    public function isConfigured(): bool
    {
        $c = config('crm.drivers.odoo');

        return filled($c['url']) && filled($c['database'])
            && filled($c['username']) && filled($c['api_key']);
    }

    /**
     * Odoo's `common.login` is exactly the read-only credential probe this
     * needs: it returns a uid or `false` and writes nothing.
     */
    public function verify(): CrmResult
    {
        $config = config('crm.drivers.odoo');

        try {
            $uid = $this->authenticate($config);

            if ($uid === null) {
                return CrmResult::failure(
                    error: 'Odoo rejected the credentials — check the database name, username and API key.',
                    retryable: false,
                );
            }

            return CrmResult::success(
                externalId: (string) $config['username'],
                response: ['uid' => $uid, 'database' => $config['database']],
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e);
        }
    }

    public function pushLead(Lead $lead): CrmResult
    {
        $config = config('crm.drivers.odoo');
        $payload = $this->payload($lead);

        try {
            $uid = $this->authenticate($config);

            if ($uid === null) {
                return CrmResult::failure(
                    error: 'Odoo authentication failed — check ODOO_USERNAME / ODOO_API_KEY.',
                    retryable: false,
                    request: $payload,
                );
            }

            $fields = $this->toOdooLead($lead, $config['source']);

            $response = Http::timeout((int) $config['timeout'])
                ->asJson()
                ->post(rtrim((string) $config['url'], '/').'/jsonrpc', [
                    'jsonrpc' => '2.0',
                    'method' => 'call',
                    'params' => [
                        'service' => 'object',
                        'method' => 'execute_kw',
                        'args' => [
                            $config['database'],
                            $uid,
                            $config['api_key'],
                            $config['model'],
                            'create',
                            [$fields],
                        ],
                    ],
                    'id' => $lead->uuid,
                ]);

            $body = $response->json();

            if (isset($body['error'])) {
                return CrmResult::failure(
                    error: 'Odoo error: '.json_encode($body['error'], JSON_UNESCAPED_UNICODE),
                    httpStatus: $response->status(),
                    // A fault from Odoo's ORM is a payload problem, not a
                    // transient one — retrying would not change the outcome.
                    retryable: false,
                    request: $fields,
                    response: $body,
                );
            }

            if (! $response->successful()) {
                return CrmResult::failure(
                    error: "Odoo returned HTTP {$response->status()}.",
                    httpStatus: $response->status(),
                    retryable: CrmResult::retryableForStatus($response->status()),
                    request: $fields,
                    response: is_array($body) ? $body : null,
                );
            }

            return CrmResult::success(
                externalId: isset($body['result']) ? (string) $body['result'] : null,
                httpStatus: $response->status(),
                request: $fields,
                response: is_array($body) ? $body : null,
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e, $payload);
        }
    }

    /** @param array<string, mixed> $config */
    private function authenticate(array $config): ?int
    {
        $response = Http::timeout((int) $config['timeout'])
            ->asJson()
            ->post(rtrim((string) $config['url'], '/').'/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'login',
                    'args' => [$config['database'], $config['username'], $config['api_key']],
                ],
                'id' => 1,
            ]);

        $uid = $response->json('result');

        // Odoo returns `false` for a failed login, not an error object.
        return is_int($uid) && $uid > 0 ? $uid : null;
    }

    /**
     * Map the lead onto Odoo's crm.lead fields.
     *
     * @return array<string, mixed>
     */
    private function toOdooLead(Lead $lead, string $source): array
    {
        // Standard crm.lead fields only. Custom x_ fields are deliberately
        // avoided: they differ per Odoo instance and a missing one makes the
        // whole create() fault, losing the lead. Attribution goes in the
        // description instead, which always exists.
        $fields = [
            'name' => $this->subject($lead),
            'type' => 'lead',
            'description' => $this->description($lead),
            'lang' => $lead->locale === 'ar' ? 'ar_001' : 'en_US',
        ];

        if ($lead->isEmail()) {
            $fields['email_from'] = $lead->contact_value;
        } else {
            $fields['phone'] = $lead->contact_value;
        }

        $fields['source_id'] = false;
        $fields['referred'] = $source;

        return $fields;
    }

    /**
     * Everything the sales team needs in one readable block, because Odoo's
     * standard fields cannot hold the attribution set.
     */
    private function description(Lead $lead): string
    {
        $lines = [
            $lead->message !== null ? "Message: {$lead->message}" : 'Message: —',
            '',
            "Reference: {$lead->uuid}",
            "Locale: {$lead->locale}",
            'Page: '.($lead->page_url ?? '—'),
            'Referrer: '.($lead->referrer ?? '—'),
            'Campaign: '.($lead->campaign?->slug ?? '—'),
            'Sector: '.($lead->sector_hint ?? '—'),
            'Interest: '.($lead->interest ?? '—'),
            "UTM: source={$lead->utm_source} medium={$lead->utm_medium} campaign={$lead->utm_campaign} term={$lead->utm_term} content={$lead->utm_content}",
            'gclid: '.($lead->gclid ?? '—').' · fbclid: '.($lead->fbclid ?? '—'),
        ];

        return implode("\n", $lines);
    }
}
