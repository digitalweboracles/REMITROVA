<?php

namespace App\Services\Payments\Paga;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PagaCollectClient — talks to Paga's Collect API (Static/Persistent
 * NUBAN creation, retrieval, update, deletion).
 *
 * Auth: HTTP Basic Auth (principal as username, secret as password)
 * PLUS the SHA-512 `hash` body field.
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
            'financialIdentificationNumber' => $data['financialIdentificationNumber'] ?? null,
            'creditBankId' => $data['creditBankId'] ?? null,
            'creditBankAccountNumber' => $data['creditBankAccountNumber'] ?? null,
            'callbackUrl' => $data['callbackUrl'],
        ], fn ($v) => $v !== null);

        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::REGISTER_PERSISTENT_ACCOUNT, $payload, $this->hashKey);

        // Confirmed by Paga support (2026-08-24): the correct path is
        // /registerPersistentPaymentAccount, not /createPersistentPaymentAccount
        // as their own docs/Postman collection previously showed.
        return $this->post('/registerPersistentPaymentAccount', $payload);
    }

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
        $url = rtrim($this->baseUrl, '/') . $path;

        // Log the exact outgoing request, principal included (it's not
        // secret, it identifies the merchant account) but with the
        // secret key/password never logged, and the hash itself IS
        // logged since seeing it lets us manually recompute and compare
        // if Paga's side disputes what we sent.
        Log::info('Paga Collect API request', [
            'url' => $url,
            'principal' => $this->principal,
            'payload' => $payload,
        ]);

        // Diagnostic only — never logs the actual hash key. Logs its
        // length and a one-way SHA-256 fingerprint of it, so we can
        // directly confirm (by comparing against a known-good
        // fingerprint computed separately) whether the value Railway
        // actually loaded matches what was set — catching the common,
        // easy-to-miss case of a trailing newline/space or truncation
        // silently corrupting every hash without changing its length
        // enough to be obvious.
        Log::info('Paga hash key diagnostic', [
            'length' => strlen($this->hashKey),
            'fingerprint' => substr(hash('sha256', $this->hashKey), 0, 16),
        ]);

        // Paga support (2026-09-02) confirmed our hash formula and
        // concatenation are correct, and specifically asked us to check
        // whether the hash is being sent as a header, and whether it
        // has a trailing space there. Previously this was ONLY in the
        // JSON body — never as a header at all. Sending it both places
        // now, with the header value explicitly trimmed, covers
        // whichever one their endpoint actually reads.
        $trimmedHash = trim($payload['hash']);

        Log::info('Paga hash header diagnostic', [
            'header_value_length' => strlen($trimmedHash),
            'header_value_preview' => substr($trimmedHash, 0, 12) . '...' . substr($trimmedHash, -12),
        ]);

        $response = Http::withBasicAuth($this->principal, $this->secretKey)
            ->acceptJson()
            ->withHeaders(['hash' => $trimmedHash])
            ->timeout(20)
            ->post($url, $payload);

        Log::info('Paga Collect API response', [
            'url' => $url,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Paga Collect API call to {$path} failed with HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
