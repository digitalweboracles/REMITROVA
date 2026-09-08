<?php

namespace App\Services\Payments;

/**
 * The provider-agnostic shape a successful account creation returns.
 * Every provider adapter (Paga, HitchPay, ...) maps its own response
 * format into this one shape, so nothing above this layer needs to
 * know which provider actually answered.
 */
class ProviderAccountResult
{
    public function __construct(
        public readonly string $providerName,
        public readonly string $accountIdentifier,
        public readonly ?string $bankName,
        public readonly array $rawResponse,
    ) {
    }
}
