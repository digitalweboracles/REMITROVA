<?php

namespace App\Services\Payments\Paga;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

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
        $referenceNumber = $data['referenceNumber'] ?? ('RR' . strtoupper(Str::random(20))); // shortened per Paga support, 2026-09-09

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
        $payload = ['referenceNumber' => $referenceNumber, 'accountIdentifier' => $accountIdentifier];
        $payload['hash'] = PagaHasher::buildHash(PagaHashFields::GET_PERSISTENT_ACCOUNT, $payload, $this->hashKey);
        return $this->post('/getPersistentPaymentAccount', $payload);
    }

    private function post(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        Log::info('Paga Collect API request', ['url' => $url, 'principal' => $this->principal, 'payload' => $payload]);

        Log::info('Paga hash key diagnostic', [
            'length' => strlen($this->hashKey),
            'fingerprint' => substr(hash('sha256', $this->hashKey), 0, 16),
        ]);

        $trimmedHash = trim($payload['hash']);

        $response = Http::withBasicAuth($this->principal, $this->secretKey)
            ->acceptJson()
            ->withHeaders(['hash' => $trimmedHash])
            ->timeout(20)
            ->post($url, $payload);

        Log::info('Paga Collect API response', [
            'url' => $url, 'status' => $response->status(),
            'headers' => $response->headers(), 'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Paga Collect API call to {$path} failed with HTTP {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
