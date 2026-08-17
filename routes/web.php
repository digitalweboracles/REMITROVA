<?php

use App\Models\Customer;
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
| TEMPORARY dev-only route — sandbox testing convenience
|--------------------------------------------------------------------------
| Creates (or returns, if it already exists) a single test customer +
| NGN wallet, so we have something real to provision a sandbox NUBAN
| against without needing CLI/Tinker access.
|
| Protected by a shared-secret query param, NOT real auth — this is
| fine for sandbox testing but must be DELETED (or the DEV_SEED_KEY
| rotated to something unguessable and then deleted anyway) before
| this codebase is ever pointed at production/DigitalOcean. Don't
| carry this route forward past Phase 1 testing.
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
            'country' => 'NG',
            'sender_formal_name' => 'Test Customer',
            'sender_gender' => 'M',
            'sender_occupation' => 'Engineer',
            'sender_age' => 30,
            'sender_address' => '1 Test Street, Lagos, Nigeria',
        ]
    );

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
