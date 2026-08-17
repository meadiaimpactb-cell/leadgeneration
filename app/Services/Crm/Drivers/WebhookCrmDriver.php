<?php

declare(strict_types=1);

namespace App\Services\Crm\Drivers;

use App\Models\Lead;
use App\Services\Crm\CrmDriver;
use App\Services\Crm\CrmResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic signed-webhook fallback (§6.3).
 *
 * Lets leads reach Zapier, Make, n8n or a bespoke endpoint while the real CRM
 * decision is pending, without anyone having to touch code.
 */
class WebhookCrmDriver implements CrmDriver
{
    use LeadPayload;

    public function name(): string
    {
        return 'webhook';
    }

    public function isConfigured(): bool
    {
        return filled(config('crm.drivers.webhook.url'));
    }

    /**
     * A webhook has one verb and it writes. There is no read to probe with,
     * and probing the write would post a fabricated lead into whatever
     * Zapier scenario is on the other end — which is worse than not knowing.
     *
     * So this reports honestly that it cannot be proven from here. The way to
     * verify a webhook is to send a real enquiry through the form and read the
     * sync log, which the screen below already shows.
     */
    public function verify(): CrmResult
    {
        return CrmResult::failure(
            error: 'A webhook cannot be tested without posting a lead to it. Send one real enquiry through the form and read the sync log instead.',
            retryable: false,
        );
    }

    public function pushLead(Lead $lead): CrmResult
    {
        $payload = $this->payload($lead);
        $secret = config('crm.drivers.webhook.secret');

        $headers = ['Accept' => 'application/json'];

        // Lets the receiver verify the call really came from this site.
        if (filled($secret)) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
            $headers['X-Amad-Signature'] = hash_hmac('sha256', $body, (string) $secret);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('crm.drivers.webhook.timeout', 10))
                ->asJson()
                ->post((string) config('crm.drivers.webhook.url'), $payload);

            if ($response->successful()) {
                return CrmResult::success(
                    externalId: $response->json('id') ?? $lead->uuid,
                    httpStatus: $response->status(),
                    request: $payload,
                    response: $this->decode($response->body()),
                );
            }

            return CrmResult::failure(
                error: "Webhook returned HTTP {$response->status()}.",
                httpStatus: $response->status(),
                retryable: CrmResult::retryableForStatus($response->status()),
                request: $payload,
                response: $this->decode($response->body()),
            );
        } catch (Throwable $e) {
            return CrmResult::fromException($e, $payload);
        }
    }

    /** @return array<string, mixed> */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }
}
