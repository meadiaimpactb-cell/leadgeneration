<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\CrmSyncLog;
use App\Models\Lead;
use App\Services\Crm\Drivers\NullCrmDriver;
use App\Services\Crm\Drivers\OdooCrmDriver;
use App\Services\Crm\Drivers\WebhookCrmDriver;
use App\Services\Crm\Drivers\ZidCrmDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the configured CRM driver and records every attempt (§6.3).
 *
 * The rest of the application talks to this class, never to a driver.
 */
class CrmManager
{
    /** @var array<string, class-string<CrmDriver>> */
    private const DRIVERS = [
        'null' => NullCrmDriver::class,
        'webhook' => WebhookCrmDriver::class,
        'odoo' => OdooCrmDriver::class,
        'zid' => ZidCrmDriver::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function driver(?string $name = null): CrmDriver
    {
        $name ??= config('crm.driver') ?: 'null';

        if (! isset(self::DRIVERS[$name])) {
            throw new InvalidArgumentException(
                "Unknown CRM driver [{$name}]. Configured drivers: ".implode(', ', array_keys(self::DRIVERS))
            );
        }

        return $this->container->make(self::DRIVERS[$name]);
    }

    /**
     * Push a lead and write the attempt to crm_sync_logs, whatever happens.
     *
     * @param  int  $attempt  1-based; supplied by the queued job so the log
     *                        reflects the real retry number.
     */
    public function push(Lead $lead, int $attempt = 1): CrmResult
    {
        $driver = $this->driver();

        $result = $driver->isConfigured()
            ? $driver->pushLead($lead)
            : CrmResult::failure(
                error: "CRM driver [{$driver->name()}] is not configured.",
                retryable: false,
            );

        $this->log($lead, $driver->name(), $attempt, $result);

        if ($result->success) {
            $lead->markSynced($driver->name(), $result->externalId);
        }

        return $result;
    }

    private function log(Lead $lead, string $provider, int $attempt, CrmResult $result): void
    {
        $limit = (int) config('crm.log_body_limit', 8000);

        CrmSyncLog::create([
            'lead_id' => $lead->id,
            'provider' => $provider,
            'attempt' => $attempt,
            'http_status' => $result->httpStatus,
            'request' => $this->truncate($result->request, $limit),
            'response' => $this->truncate($result->response, $limit),
            'error' => $result->error,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null
     */
    private function truncate(?array $body, int $limit): ?array
    {
        if ($body === null) {
            return null;
        }

        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);

        if ($encoded === false || strlen($encoded) <= $limit) {
            return $body;
        }

        return ['_truncated' => substr($encoded, 0, $limit)];
    }
}
