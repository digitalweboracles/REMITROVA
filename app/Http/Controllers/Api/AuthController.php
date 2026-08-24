<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Real registration/login for the demo frontend to connect to.
 *
 * Uses Sanctum's token auth (not session/cookie auth), since the
 * frontend is hosted on a completely different domain (edge.one) than
 * this API (railway.app) — token-in-header is the correct fit for
 * that, not Sanctum's SPA cookie mode, which assumes same top-level
 * domain or a much more involved CORS/cookie setup.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:6'],
            'country' => ['required', 'in:PL,NG'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // NGN wallet only — Static NUBAN (the only receiving rail built
        // so far) attaches to this. A PLN wallet isn't created here
        // since there's no working rail behind it yet; creating one
        // would just be a number with nothing real able to move it.
        //
        // Wrapped in a transaction so a failure partway through (e.g. a
        // missing wallets table) can't leave an orphaned customer row —
        // that would make every retry fail with "email already taken".
        $customer = DB::transaction(function () use ($request) {
            $customer = Customer::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'country' => $request->input('country'),
            ]);

            $customer->wallets()->create(['currency' => 'NGN', 'balance' => 0]);

            return $customer;
        });

        $token = $customer->createToken('demo-frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => $this->customerPayload($customer),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('email', $request->input('email'))->first();

        if (!$customer || !Hash::check($request->input('password'), $customer->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $token = $customer->createToken('demo-frontend')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => $this->customerPayload($customer),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->customerPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function customerPayload(Customer $customer): array
    {
        $customer->loadMissing('wallets', 'persistentAccounts');
        $ngnWallet = $customer->wallet('NGN');
        $activeNuban = $customer->persistentAccounts->firstWhere('status', 'active');

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'country' => $customer->country,
            'ngn_balance' => $ngnWallet?->balance ?? '0.0000',
            'nuban' => $activeNuban?->account_identifier,
        ];
    }
}
