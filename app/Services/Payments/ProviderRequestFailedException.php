<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Thrown by any provider adapter when a request fails, for any reason
 * (network error, non-2xx response, provider-side rejection). The
 * failover manager catches exactly this exception type to decide
 * whether to try the next provider — a provider adapter that throws
 * something else, or doesn't throw at all on failure, will silently
 * break failover.
 */
class ProviderRequestFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $providerName,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct("[{$providerName}] {$message}");
    }
}
