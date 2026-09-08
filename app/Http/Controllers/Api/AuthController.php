<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

        $customer = Customer::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'country' => $request->input('country'),
        ]);

        $customer->wallets()->create(['currency' => 'NGN', 'balance' => 0]);

        $token = $customer->createToken('demo-frontend')->plainTextToken;

        return response()->json(['token' => $token, 'customer' => $this->customerPayload($customer)], 201);
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

        return response()->json(['token' => $token, 'customer' => $this->customerPayload($customer)]);
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
