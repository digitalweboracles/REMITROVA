<?php

namespace App\Services\Payments\Paga;

use App\Models\Customer;
use App\Models\PersistentAccount;
use App\Models\Wallet;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates provisioning a Paga Static NUBAN for a customer's NGN
 * wallet: creates the local pending record first (so we always have a
 * row to reconcile against even if Paga's call fails), calls Paga,
 * then updates the record with the result.
 */
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
            return $existing; // don't provision a second NUBAN for the same customer
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

        // Paga's response field names for the created account number can
        // vary by their doc revision (accountNumber has been the
        // confirmed field in samples reviewed) — kept as a single place
        // to adjust if their response shape changes.
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
