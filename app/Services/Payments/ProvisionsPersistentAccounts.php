<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\PersistentAccount;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates provisioning a persistent account (NUBAN) for a
 * customer's NGN wallet. Now provider-agnostic: this class talks only
 * to PersistentAccountProviderManager, which handles trying providers
 * in priority order and automatic failover — this class doesn't know
 * or care whether Paga, HitchPay, or a future third provider actually
 * fulfilled the request.
 */
class ProvisionsPersistentAccounts
{
    public function __construct(private readonly PersistentAccountProviderManager $providerManager)
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

        $accountReference = 'RR' . strtoupper(Str::random(14)); // 16 chars — within Paga's confirmed 11-30 char requirement

        $record = PersistentAccount::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => 'pending',
            'account_reference' => $accountReference,
            'status' => 'pending',
        ]);

        try {
            $result = $this->providerManager->createPersistentAccount($customer, $accountReference, $callbackUrl);
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            throw $e;
        }

        $record->update([
            'status' => 'active',
            'provider' => $result->providerName,
            'account_identifier' => $result->accountIdentifier,
            'bank_name' => $result->bankName,
            'raw_create_response' => $result->rawResponse,
        ]);

        return $record->fresh();
    }
}
