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

        $customerUuid = $customer->hitchpay_customer_uuid;

        if (!$customerUuid) {
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
                        'country' => $customer->country,
                    ],
                ]);
            } catch (\Throwable $e) {
                throw new ProviderRequestFailedException('hitchpay', 'Enrollment failed: ' . $e->getMessage());
            }

            $customerUuid = $enrollResponse['data']['uuid'] ?? null;

            if (!$customerUuid) {
                throw new ProviderRequestFailedException('hitchpay', 'Enrollment succeeded but response did not include a uuid.', ['raw_response' => $enrollResponse]);
            }

            // Remember it so we never enroll (and hit DUPLICATE_REQUEST) again.
            $customer->update(['hitchpay_customer_uuid' => $customerUuid]);
        }

        $this->ensureKycApproved($customer, $customerUuid);

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

    /**
     * HitchPay refuses to issue an account until the customer's KYC is
     * approved. For Nigerian customers that means a BVN lookup, which is
     * immediate. We only ever send a BVN the customer actually provided.
     */
    private function ensureKycApproved(Customer $customer, string $customerUuid): void
    {
        try {
            $status = $this->client->getCustomer($customerUuid)['data']['kyc_status'] ?? null;
        } catch (\Throwable $e) {
            throw new ProviderRequestFailedException('hitchpay', 'Could not read KYC status: ' . $e->getMessage());
        }

        if ($status === 'approved') {
            return;
        }

        if (!filled($customer->bvn)) {
            throw new ProviderRequestFailedException('hitchpay', "Customer KYC status is '{$status}' and no BVN is on file. A BVN must be collected from the customer before HitchPay will issue an account.");
        }

        try {
            $this->client->submitKyc($customerUuid, 'bvn', $customer->bvn);
        } catch (\Throwable $e) {
            // Already pending/approved is fine; anything else is a real failure.
            if (!str_contains($e->getMessage(), 'DUPLICATE_REQUEST') && !str_contains($e->getMessage(), 'CONFLICT')) {
                throw new ProviderRequestFailedException('hitchpay', 'BVN submission failed: ' . $e->getMessage());
            }
        }

        try {
            $status = $this->client->getCustomer($customerUuid)['data']['kyc_status'] ?? null;
        } catch (\Throwable $e) {
            throw new ProviderRequestFailedException('hitchpay', 'Could not re-read KYC status: ' . $e->getMessage());
        }

        if ($status !== 'approved') {
            throw new ProviderRequestFailedException('hitchpay', "KYC is not approved yet (status: '{$status}'). Try again shortly, or check the BVN details match.");
        }
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
