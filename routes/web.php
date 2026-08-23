<?php

use App\Models\Customer;
use App\Services\Payments\Paga\ProvisionsPersistentAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'RemitRova backend',
        'status' => 'ok',
    ]);
});

/*
|--------------------------------------------------------------------------
| TEMPORARY dev-only routes — sandbox testing convenience
|--------------------------------------------------------------------------
| Both protected by a shared-secret query param, NOT real auth. Fine for
| sandbox testing, but MUST be deleted (not just have the key rotated)
| before this codebase is ever pointed at production/DigitalOcean.
*/

Route::get('/dev/seed-test-customer', function (Request $request) {
    if (!config('app.dev_seed_key') || $request->query('key') !== config('app.dev_seed_key')) {
        abort(403, 'Invalid or missing key.');
    }

    $customer = Customer::firstOrCreate(
        ['email' => 'test@remitrova.com'],
        [
            'name' => 'Test Customer',
            'password' => bcrypt('password123'),
            'phone' => '08012345678',
            'country' => 'NG',
            'sender_formal_name' => 'Test Customer',
            'sender_gender' => 'M',
            'sender_occupation' => 'Engineer',
            'sender_age' => 30,
            'sender_address' => '1 Test Street, Lagos, Nigeria',
        ]
    );

    // If this customer already existed from before this fix (created
    // without a phone number), fill it in now rather than requiring a
    // manual DB edit — Paga's Create Persistent Account expects phoneNumber.
    if (!$customer->phone) {
        $customer->update(['phone' => '08012345678']);
    }

    $wallet = $customer->wallets()->firstOrCreate(
        ['currency' => 'NGN'],
        ['balance' => 0]
    );

    return response()->json([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'email' => $customer->email,
    ]);
});

/**
 * Provisions a real Paga sandbox NUBAN for the given customer_id.
 * This is the first live call to Paga's Collect API from this app —
 * the actual test of the hash logic, auth, and endpoint working
 * end-to-end against their sandbox.
 */
Route::get('/dev/provision-nuban/{customerId}', function (Request $request, int $customerId, ProvisionsPersistentAccounts $provisioner) {
    if (!config('app.dev_seed_key') || $request->query('key') !== config('app.dev_seed_key')) {
        abort(403, 'Invalid or missing key.');
    }

    $customer = Customer::findOrFail($customerId);

    try {
        $account = $provisioner->createForCustomer(
            $customer,
            route('webhooks.paga.persistent-account')
        );
    } catch (\Throwable $e) {
        return response()->json([
            'error' => true,
            'message' => $e->getMessage(),
        ], 500);
    }

    return response()->json([
        'status' => $account->status,
        'account_reference' => $account->account_reference,
        'account_number' => $account->account_identifier,
        'bank_name' => $account->bank_name,
        'failure_reason' => $account->failure_reason,
        'raw_paga_response' => $account->raw_create_response,
    ]);
});
