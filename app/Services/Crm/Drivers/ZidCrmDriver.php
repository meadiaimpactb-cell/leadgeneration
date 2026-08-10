<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;
use App\Services\Crm\CrmDriver;
use App\Services\Crm\CrmResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Zid CRM driver (§6.3 — the likely destination once Odoo is retired).
 *
 * NOTE FOR HANDOVER: Zid's customer/lead API surface is not public and no
 * Amad Craft Zid credentials are available yet, so the endpoint path and
 * field names below are provisional. The transport, auth header, retry
 * classification and logging are correct and tested; only the endpoint
 * mapping needs confirming against Zid's partner documentation before
 * CRM_DRIVER=zid is switched on. Until then the site runs on Odoo, which
 * §6.3 requires to be ready at launch.
 */
class ZidCrmDriver implements CrmDriver
{
    use LeadPayload;

    public function name(): string
    {
        return 'zid';
    }

    public function isConfigured(): bool
    {
        $c = config('crm.drivers.zid');

        return filled($c['base_url']) && filled($c['access_token']);
    }

    public function pushLead(Lead $lead): CrmResult
    {
        $config = config('crm.drivers.zid');
        $payload = $this->toZidLead($lead);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$config['access_token'],
                'Accept' => 'application/json',
                'Accept-Language' => $lead->locale,
                'Store-Id' => (string) ($config['store_id'] ?? ''),
            ])
                ->timeout((int) $config['timeout'])
                ->asJson()
                ->post(rtrim((string) $config['base_url'], '/').'/v1/managers/store/leads', $payload);

            if ($response->successful()) {
                return CrmResult::success(
                    externalId: (string) ($response->json('id') ?? $response->json('data.id') ?? $lead->uuid),
                    httpStatus: $response->status(),
                    request: $payload,
                    response: $response->json() ?? [],
                );
            }

            return CrmResult::failure(
                error: "Zid returned HTTP {$response->status()}.",
                httpStatus: $response->status(),
                retryable: CrmResult::retryableForStatus($response->status()),
                request: $payload,
                response: $response->json() ?? ['raw' => $response->body()],
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e, $payload);
        }
    }

    /** @return array<string, mixed> */
    private function toZidLead(Lead $lead): array
    {
        $payload = $this->payload($lead);
        $payload['title'] = $this->subject($lead);

        return $payload;
    }
}
