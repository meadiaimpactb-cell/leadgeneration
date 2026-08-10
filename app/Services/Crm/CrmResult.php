<?php

declare(strict_types=1);

namespace App\Services\Crm;

use Throwable;

/**
 * The outcome of one CRM push attempt.
 *
 * `retryable` is the important field: a 422 from the CRM means the payload is
 * wrong and retrying will never help, whereas a timeout should be retried.
 * Retrying a permanent failure five times just delays the alert (§6.3).
 */
final readonly class CrmResult
{
    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    private function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?int $httpStatus = null,
        public ?string $error = null,
        public bool $retryable = false,
        public ?array $request = null,
        public ?array $response = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public static function success(
        ?string $externalId = null,
        ?int $httpStatus = 200,
        ?array $request = null,
        ?array $response = null,
    ): self {
        return new self(
            success: true,
            externalId: $externalId,
            httpStatus: $httpStatus,
            request: $request,
            response: $response,
        );
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public static function failure(
        string $error,
        ?int $httpStatus = null,
        bool $retryable = true,
        ?array $request = null,
        ?array $response = null,
    ): self {
        return new self(
            success: false,
            httpStatus: $httpStatus,
            error: $error,
            retryable: $retryable,
            request: $request,
            response: $response,
        );
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    public static function fromException(Throwable $e, ?array $request = null): self
    {
        return self::failure(
            error: $e::class.': '.$e->getMessage(),
            retryable: true,
            request: $request,
        );
    }

    /**
     * 4xx other than 408/429 means the request itself is wrong — do not retry.
     */
    public static function retryableForStatus(int $status): bool
    {
        if ($status === 408 || $status === 429) {
            return true;
        }

        return $status < 400 || $status >= 500;
    }
}
