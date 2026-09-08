<?php

namespace App\Services\Payments\HitchPay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HitchPayClient — talks to HitchPay's actual API
 * (hitchpay.gitbook.io/hitchpay-api-doc), confirmed directly against
 * their published documentation (not secondhand/support-relayed, the
 * way we had to work with Paga).
 *
 * Auth: OAuth 2.0 Client Credentials Grant — POST client_id/secret to
 * /v1/oauth/token, get back a Bearer token (1 hour), pass it as
 * Authorization: Bearer on every subsequent call. This is a
 * completely different auth model from Paga's HTTP Basic Auth +
 * SHA-512 hash — no request signing at all here.
 */
class HitchPayClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    /**
     * Returns a valid Bearer token, reusing a cached one if it hasn't
     * expired yet rather than fetching a fresh token on every single
     * API call (which would work, but wastes a round-trip and risks
     * hitting a rate limit needlessly). Cached with a safety margin
     * (expires 60s before HitchPay's own expiry) so we never send a
     * request with a token that expires mid-flight.
     */
    private function getAccessToken(): string
    {
        return Cache::remember('hitchpay_access_token', 3300, function () {
            $response = Http::asForm()
                ->timeout(20)
                ->post(rtrim($this->baseUrl, '/') . '/v1/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

            Log::info('HitchPay token request', ['status' => $response->status()]);

            if ($response->failed()) {
                throw new RuntimeException("HitchPay token request failed with HTTP {$response->status()}: {$response->body()}");
            }

            $data = $response->json();
            $token = $data['data']['access_token'] ?? null;

            if (!$token) {
                throw new RuntimeException('HitchPay token response did not include an access_token: ' . $response->body());
            }

            return $token;
        });
    }

    /**
     * Enrolls a customer with HitchPay. Their docs confirm enrolling
     * the same customerid/email twice returns the existing record
     * rather than erroring — so this is safe to call again for a
     * customer who's already enrolled.
     */
    public function enrollCustomer(array $data): array
    {
        return $this->post('/v1/customer/enroll', [
            'customerid' => $data['customerid'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'dob' => $data['dob'],
            'nationality' => $data['nationality'],
            'image' => $data['image'],
            'address' => $data['address'],
        ]);
    }

    /**
     * Generates a virtual account (NGN by default) for an already-
     * enrolled customer. $customerUuid is HitchPay's own uuid from
     * the enrollCustomer() response, NOT our internal customer ID.
     */
    public function generateBankAccount(string $customerUuid, string $currency = 'NGN', ?string $bankType = null): array
    {
        $payload = ['customer_id' => $customerUuid, 'currency' => $currency];

        if ($currency === 'NGN') {
            // Confirmed required specifically for NGN by their docs.
            $payload['bank_type'] = $bankType ?? '9psb';
        }

        return $this->post('/v1/customer/generate-account', $payload);
    }

    private function post(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        Log::info('HitchPay API request', ['url' => $url, 'payload' => $payload]);

        $response = Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(20)
            ->post($url, $payload);

        Log::info('HitchPay API response', ['url' => $url, 'status' => $response->status(), 'body' => $response->body()]);

        if ($response->failed()) {
            throw new RuntimeException("HitchPay API call to {$path} failed with HTTP {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
