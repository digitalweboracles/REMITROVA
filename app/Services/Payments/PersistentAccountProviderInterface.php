<?php

namespace App\Services\Payments;

use App\Models\Customer;

/**
 * PersistentAccountProviderInterface — the contract any NUBAN-issuing
 * provider must implement to be plugged into RemitRova.
 *
 * This is the abstraction that makes multi-provider failover possible:
 * ProvisionsPersistentAccounts (and everything above it) never calls
 * Paga or HitchPay directly — it only ever talks to this interface.
 * Adding a third provider later means writing one new class that
 * implements this interface; nothing else in the app needs to change.
 */
interface PersistentAccountProviderInterface
{
    /**
     * A short, stable identifier for this provider — used as the key
     * for health tracking, logging, and the `provider` column on
     * persistent_accounts/ledger_entries. Must never change once a
     * provider has real accounts recorded under it.
     */
    public function name(): string;

    /**
     * Requests a new persistent account (NUBAN or equivalent) for a
     * customer. Implementations MUST throw
     * ProviderRequestFailedException on any failure — that's what the
     * failover manager listens for to decide whether to try the next
     * provider, so a provider that returns a partial/error result
     * instead of throwing will break failover silently.
     *
     * @throws ProviderRequestFailedException
     */
    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult;
}
