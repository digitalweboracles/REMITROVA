<?php

namespace App\Services\Payments\Paga;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PagaCollectClient — talks to Paga's Collect API (Static/Persistent
 * NUBAN creation, retrieval, update, deletion).
 *
 * Auth: Collect API uses HTTP Basic Auth (principal as username,
 * secret as password) PLUS the SHA-512 `hash` body field — confirmed
 * from the Postman collection's auth block and pre-request scripts.
 * This is a DIFFERENT auth transport than the Business API (which
 * uses `principal`/`credentials` headers instead — see PagaBusinessClient).
 */
class PagaCollectClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $principal,
        private readonly string $secretKey,
        private readonly string $hashKey,
    ) {
    }

    /**
     * Creates a persistent (static) payment account — a permanent NUBAN
     * for one customer. $accountReference should be unique per customer
     * on our side; Paga docs §6.
     */
    public function createPersistentAccount(array $data): array
    {
        $referenceNumber = $data['referenceNumber'] ?? (string) Str::uuid();

        $payload = array_filter([
            'referenceNumber' => $referenceNumber,
            'accountReference' => $data['accountReference'],
            'phoneNumber' => $data['phoneNumber'] ?? null,
            'firstName' => $data['firstName'] ?? null,
            'lastName' => $data['lastName'] ?? null,
            'accountName' => $data['accountName'] ?? null,
            'financialIdentificationNumber' => $data['financialIdentificationNumber'] ?? null, // BVN/NIN — optional
            'creditBankId' => $data['creditBankId'] ?? null,                 // optional: auto-sweep target bank
            'creditBankAccountNumber' => $data['creditBankAccountNumber'] ?? null, // optional: auto-sweep account
            'callbackUrl' => $data['callbackUrl'],
        ], fn ($v) => $v !== null);

        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::CREATE_PERSISTENT_ACCOUNT, $payload, $this->hashKey);

        return $this->post('/createPersistentPaymentAccount', $payload);
    }

    /**
     * Retrieves an existing persistent account's current state.
     * Confirmed correct path per Paga support: /getPersistentPaymentAccount
     * (their Postman collection currently points this at
     * /updatePersistentPaymentAccount by mistake — do not copy that).
     */
    public function getPersistentAccount(string $referenceNumber, string $accountIdentifier): array
    {
        $payload = [
            'referenceNumber' => $referenceNumber,
            'accountIdentifier' => $accountIdentifier,
        ];
        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::GET_PERSISTENT_ACCOUNT, $payload, $this->hashKey);

        return $this->post('/getPersistentPaymentAccount', $payload);
    }

    public function updatePersistentAccount(array $data): array
    {
        $payload = array_filter([
            'referenceNumber' => $data['referenceNumber'],
            'accountIdentifier' => $data['accountIdentifier'],
            'phoneNumber' => $data['phoneNumber'] ?? null,
            'firstName' => $data['firstName'] ?? null,
            'lastName' => $data['lastName'] ?? null,
            'accountName' => $data['accountName'] ?? null,
            'financialIdentificationNumber' => $data['financialIdentificationNumber'] ?? null,
            'callbackUrl' => $data['callbackUrl'] ?? null,
        ], fn ($v) => $v !== null);

        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::UPDATE_PERSISTENT_ACCOUNT, $payload, $this->hashKey);

        return $this->post('/updatePersistentPaymentAccount', $payload);
    }

    public function deletePersistentAccount(string $referenceNumber, string $accountIdentifier, string $reason): array
    {
        $payload = [
            'referenceNumber' => $referenceNumber,
            'accountIdentifier' => $accountIdentifier,
            'reason' => $reason,
        ];
        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::DELETE_PERSISTENT_ACCOUNT, $payload, $this->hashKey);

        return $this->post('/deletePersistentPaymentAccount', $payload);
    }

    private function post(string $path, array $payload): array
    {
        $response = Http::withBasicAuth($this->principal, $this->secretKey)
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim($this->baseUrl, '/') . $path, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Paga Collect API call to {$path} failed with HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
