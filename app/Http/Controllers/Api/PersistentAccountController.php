<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersistentAccount;
use App\Services\Payments\Paga\ProvisionsPersistentAccounts;
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

        $account = $this->provisioner->createForCustomer(
            $customer,
            route('webhooks.paga.persistent-account')
        );

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
