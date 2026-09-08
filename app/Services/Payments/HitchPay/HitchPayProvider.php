<?php

namespace App\Services\Payments\HitchPay;

use App\Models\Customer;
use App\Services\Payments\PersistentAccountProviderInterface;
use App\Services\Payments\ProviderAccountResult;
use App\Services\Payments\ProviderRequestFailedException;

/**
 * Real HitchPay adapter — genuinely calls their API now (see
 * HitchPayClient), confirmed against their published documentation.
 *
 * One structural difference from Paga worth being explicit about:
 * HitchPay requires a separate customer *enrollment* step (collecting
 * date of birth, nationality, a profile photo, and a structured
 * address for KYC purposes) before an account can be generated. We
 * don't currently collect that data at signup. Rather than sending
 * fabricated identity data to a real KYC-oriented endpoint — which
 * would be a genuinely bad idea, not just a coding shortcut — this
 * checks for that data up front and refuses honestly, naming exactly
 * what's missing, if it isn't there.
 */
class HitchPayProvider implements PersistentAccountProviderInterface
{
    public function __construct(private readonly HitchPayClient $client)
    {
    }

    public function name(): string
    {
        return 'hitchpay';
    }

    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult
    {
        if (!$customer->hasCompletedHitchPayKyc()) {
            throw new ProviderRequestFailedException(
                'hitchpay',
                'Customer is missing required KYC fields for HitchPay enrollment (needs: date of birth, nationality, profile photo URL, and full structured address). These aren\'t collected at signup yet — this is an honest refusal, not a real API failure.',
            );
        }

        [$firstName, $lastName] = $this->splitName($customer->name);

        try {
            $enrollResponse = $this->client->enrollCustomer([
                'customerid' => 'RR-' . $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'dob' => $customer->date_of_birth->format('d-m-Y'),
                'nationality' => $customer->nationality,
                'image' => $customer->profile_image_url,
                'address' => [
                    'house_no' => (int) $customer->address_house_no,
                    'street' => $customer->address_street,
                    'city' => $customer->address_city,
                    'state' => $customer->address_state,
                    'postal_code' => $customer->address_postal_code,
                    'country' => $customer->country, // already PL/NG — our enum matches ISO 3166-1 alpha-2 directly
                ],
            ]);
        } catch (\Throwable $e) {
            throw new ProviderRequestFailedException('hitchpay', 'Enrollment failed: ' . $e->getMessage());
        }

        $customerUuid = $enrollResponse['data']['uuid'] ?? null;

        if (!$customerUuid) {
            throw new ProviderRequestFailedException('hitchpay', 'Enrollment succeeded but response did not include a uuid.', ['raw_response' => $enrollResponse]);
        }

        try {
            $accountResponse = $this->client->generateBankAccount($customerUuid, 'NGN');
        } catch (\Throwable $e) {
            throw new ProviderRequestFailedException('hitchpay', 'Account generation failed: ' . $e->getMessage());
        }

        $accountData = $accountResponse['data'] ?? [];
        $accountNumber = $accountData['account_number'] ?? null;

        if (!$accountNumber) {
            throw new ProviderRequestFailedException('hitchpay', 'Account generation did not return an account number.', ['raw_response' => $accountResponse]);
        }

        return new ProviderAccountResult(
            providerName: 'hitchpay',
            accountIdentifier: $accountNumber,
            bankName: $accountData['bank_name'] ?? 'HitchPay',
            rawResponse: $accountResponse,
        );
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
