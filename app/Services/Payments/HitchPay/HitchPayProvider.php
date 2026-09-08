<?php

namespace App\Services\Payments\HitchPay;

use App\Models\Customer;
use App\Services\Payments\PersistentAccountProviderInterface;
use App\Services\Payments\ProviderAccountResult;
use App\Services\Payments\ProviderRequestFailedException;

/**
 * HitchPayProvider — STUB.
 *
 * This class exists to prove the provider abstraction genuinely
 * supports a second provider (it implements the same interface
 * PagaProvider does, and can be registered in the same manager), but
 * it does NOT talk to a real HitchPay API yet. We don't have their
 * API credentials, endpoint names, or request-signing requirements —
 * inventing plausible-looking details for those would be actively
 * wrong, not just incomplete, since it would look like a working
 * integration without being one.
 *
 * To make this real once HitchPay's API details are available:
 *   1. Build a HitchPayClient (mirroring PagaCollectClient's shape —
 *      HTTP client, whatever auth/signing HitchPay requires).
 *   2. Replace the body of createPersistentAccount() below to call it
 *      and map their response into a ProviderAccountResult, the same
 *      way PagaProvider does.
 *   3. Register this provider in config/payment_providers.php.
 * Nothing else in the app needs to change — that's the point of the
 * interface.
 */
class HitchPayProvider implements PersistentAccountProviderInterface
{
    public function name(): string
    {
        return 'hitchpay';
    }

    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult
    {
        throw new ProviderRequestFailedException(
            'hitchpay',
            'HitchPay integration is not yet implemented — this is a stub proving the abstraction works, pending real API credentials and documentation from HitchPay.',
        );
    }
}
