<?php

namespace App\Services\Payments;

/**
 * The provider is healthy, but the customer's identity check hasn't passed
 * (still verifying, or rejected). That is NOT a provider outage, so the
 * failover manager must not count it against the provider's circuit breaker.
 */
class KycNotApprovedException extends ProviderRequestFailedException
{
    public function __construct(
        string $providerName,
        string $message,
        public readonly bool $rejected = false,
    ) {
        parent::__construct($providerName, $message);
    }
}
