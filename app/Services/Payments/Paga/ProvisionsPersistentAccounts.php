<?php

namespace App\Services\Payments\Paga;

use App\Models\Customer;
use App\Models\PersistentAccount;
use Illuminate\Support\Str;
use RuntimeException;

class ProvisionsPersistentAccounts
{
    public function __construct(private readonly PagaCollectClient $client)
    {
    }

    public function createForCustomer(Customer $customer, string $callbackUrl): PersistentAccount
    {
        $wallet = $customer->wallet('NGN');

        if (!$wallet) {
            throw new RuntimeException("Customer {$customer->id} has no NGN wallet to attach a NUBAN to.");
        }

        $existing = PersistentAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        $accountReference = (string) Str::uuid();

        $record = PersistentAccount::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => 'paga',
            'account_reference' => $accountReference,
            'status' => 'pending',
        ]);

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
            $record->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            throw $e;
        }

        $accountIdentifier = $response['accountNumber'] ?? $response['accountIdentifier'] ?? null;

        if (!$accountIdentifier) {
            $record->update([
                'status' => 'failed',
                'failure_reason' => 'Paga response did not include an account number.',
                'raw_create_response' => $response,
            ]);
            throw new RuntimeException('Paga createPersistentAccount succeeded but returned no account number — check raw_create_response.');
        }

        $record->update([
            'status' => 'active',
            'account_identifier' => $accountIdentifier,
            'raw_create_response' => $response,
        ]);

        return $record->fresh();
    }
}
