<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PersistentAccount;
use App\Services\Payments\HitchPay\HitchPayClient;
use App\Services\Payments\KycNotApprovedException;
use App\Services\Payments\ProvisionsPersistentAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Identity (KYC) step for getting a NUBAN. HitchPay will not issue an account
 * until the customer's BVN is approved, and approval happens in the
 * background, so the app polls status() until it reads "active".
 *
 * Stages returned to the app:
 *   needs_kyc  - we don't have the details yet, show the form
 *   verifying  - submitted, waiting on the identity check
 *   rejected   - identity check failed, show the form again
 *   active     - done, account_number present
 *   error      - something else went wrong (retry later)
 */
class KycController extends Controller
{
    public function __construct(
        private readonly ProvisionsPersistentAccounts $provisioner,
        private readonly HitchPayClient $hitchPay,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'bvn' => ['required', 'digits:11'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:-18 years', 'after:1900-01-01'],
            'phone' => ['required', 'regex:/^(\+?234|0)[0-9]{10}$/'],
            'address_house_no' => ['required', 'string', 'max:20'],
            'address_street' => ['required', 'string', 'max:120'],
            'address_city' => ['required', 'string', 'max:80'],
            'address_state' => ['required', 'string', 'max:80'],
            'address_postal_code' => ['required', 'string', 'max:12'],
            'photo' => [$customer->profile_image_url ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'consent' => ['required', 'accepted'],
        ], [
            'bvn.digits' => 'Your BVN must be exactly 11 digits.',
            'date_of_birth.before' => 'You must be at least 18 years old.',
            'phone.regex' => 'Enter a Nigerian phone number, e.g. 08012345678.',
            'photo.required' => 'Please add a clear photo of your face.',
            'consent.accepted' => 'Please tick the consent box to continue.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $photoUrl = $customer->profile_image_url;

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->storeAs(
                'kyc',
                $customer->id . '-' . Str::random(24) . '.' . $request->file('photo')->extension(),
                'public'
            );
            $photoUrl = Storage::disk('public')->url($path);
        }

        $phone = preg_replace('/^\+?234/', '0', $request->input('phone'));

        $customer->update([
            'kyc_first_name' => trim($request->input('first_name')),
            'kyc_last_name' => trim($request->input('last_name')),
            'bvn' => $request->input('bvn'),
            'date_of_birth' => $request->input('date_of_birth'),
            'phone' => $phone,
            'nationality' => 'NG',
            'profile_image_url' => $photoUrl,
            'address_house_no' => $request->input('address_house_no'),
            'address_street' => trim($request->input('address_street')),
            'address_city' => trim($request->input('address_city')),
            'address_state' => trim($request->input('address_state')),
            'address_postal_code' => trim($request->input('address_postal_code')),
        ]);

        // Already enrolled (e.g. a rejected attempt being corrected): push the
        // corrected details to HitchPay, which resets their status so the
        // identity check can run again.
        if ($customer->hitchpay_customer_uuid) {
            try {
                $this->hitchPay->updateCustomer($customer->hitchpay_customer_uuid, [
                    'firstname' => $customer->kyc_first_name,
                    'lastname' => $customer->kyc_last_name,
                    'dob' => $customer->date_of_birth->format('d-m-Y'),
                    'phone' => $customer->phone,
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
                // Locked after approval, or a transient error: the status check below reports the truth.
                Log::warning('HitchPay profile update skipped', ['customer' => $customer->id, 'error' => $e->getMessage()]);
            }
        }

        Cache::forget($this->lockKey($customer));

        return response()->json($this->resolveStatus($customer->fresh(), true));
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->resolveStatus($request->user()));
    }

    private function resolveStatus(Customer $customer, bool $force = false): array
    {
        $active = PersistentAccount::where('customer_id', $customer->id)->where('status', 'active')->first();

        if ($active) {
            return ['stage' => 'active', 'account_number' => $active->account_identifier, 'bank_name' => $active->bank_name];
        }

        if (!$customer->hasCompletedHitchPayKyc() || !filled($customer->bvn) || !filled($customer->kyc_first_name)) {
            return ['stage' => 'needs_kyc'];
        }

        // Don't hammer HitchPay when the app polls: at most one real check every 8s per customer.
        if (!$force && !Cache::add($this->lockKey($customer), 1, 8)) {
            return ['stage' => 'verifying', 'message' => 'Still verifying your details.'];
        }

        try {
            $account = $this->provisioner->createForCustomer($customer, route('webhooks.paga.persistent-account'));

            return ['stage' => 'active', 'account_number' => $account->account_identifier, 'bank_name' => $account->bank_name];
        } catch (KycNotApprovedException $e) {
            if ($e->rejected) {
                return ['stage' => 'rejected', 'message' => 'We could not verify your BVN with the details you gave. Please check the name, date of birth and BVN, then try again.'];
            }

            return ['stage' => 'verifying', 'message' => 'Verifying your identity. This can take a minute or two.'];
        } catch (\Throwable $e) {
            Log::error('NUBAN provisioning failed', ['customer' => $customer->id, 'error' => $e->getMessage()]);

            return ['stage' => 'error', 'message' => 'We could not create your account number right now. Please try again in a few minutes.'];
        }
    }

    private function lockKey(Customer $customer): string
    {
        return 'kyc-status-lock:' . $customer->id;
    }
}
