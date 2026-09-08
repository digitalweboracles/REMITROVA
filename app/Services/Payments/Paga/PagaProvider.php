<?php

namespace App\Services\Payments\Paga;

use App\Models\Customer;
use App\Services\Payments\PersistentAccountProviderInterface;
use App\Services\Payments\ProviderAccountResult;
use App\Services\Payments\ProviderRequestFailedException;
use Illuminate\Support\Str;

/**
 * Adapts the existing, Paga-specific PagaCollectClient to the
 * provider-agnostic PersistentAccountProviderInterface. This is the
 * ONLY class that should know PagaCollectClient exists — everything
 * above the provider layer talks to the interface, not to Paga
 * directly, which is what makes swapping/adding providers possible
 * without touching the rest of the app.
 */
class PagaProvider implements PersistentAccountProviderInterface
{
    public function __construct(private readonly PagaCollectClient $client)
    {
    }

    public function name(): string
    {
        return 'paga';
    }

    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult
    {
        try {
            $response = $this->client->createPersistentAccount([
                'accountReference' => $accountReference,
                'phoneNumber' => $customer->phone,
                'firstName' => Str::before($customer->name, ' '),
                'lastName' => Str::after($customer->name, ' ') ?: $customer->name,
                'accountName' => $customer->name,
                'callbackUrl' => $callbackUrl,
            ]);
        } catch (\Throwable $e) {
            throw new ProviderRequestFailedException('paga', $e->getMessage(), ['exception' => get_class($e)]);
        }

        $accountIdentifier = $response['accountNumber'] ?? $response['accountIdentifier'] ?? null;

        if (!$accountIdentifier) {
            throw new ProviderRequestFailedException('paga', 'Response did not include an account number.', ['raw_response' => $response]);
        }

        return new ProviderAccountResult(
            providerName: 'paga',
            accountIdentifier: $accountIdentifier,
            bankName: $response['bankName'] ?? 'Paga',
            rawResponse: $response,
        );
    }
}
