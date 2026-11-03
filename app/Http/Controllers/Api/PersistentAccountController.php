<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersistentAccount;
use App\Services\Payments\KycNotApprovedException;
use App\Services\Payments\ProvisionsPersistentAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersistentAccountController extends Controller
{
    public function __construct(private readonly ProvisionsPersistentAccounts $provisioner)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        try {
            $account = $this->provisioner->createForCustomer($customer, route('webhooks.paga.persistent-account'));
        } catch (KycNotApprovedException $e) {
            return response()->json(['message' => 'Identity verification is required first. Use /api/kyc.', 'stage' => $e->rejected ? 'rejected' : 'needs_kyc'], 409);
        }

        return response()->json([
            'status' => $account->status,
            'account_number' => $account->account_identifier,
            'bank_name' => $account->bank_name,
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $account = PersistentAccount::where('customer_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$account) {
            return response()->json(['message' => 'No active NUBAN provisioned yet.'], 404);
        }

        return response()->json([
            'status' => $account->status,
            'account_number' => $account->account_identifier,
            'bank_name' => $account->bank_name,
        ]);
    }
}
