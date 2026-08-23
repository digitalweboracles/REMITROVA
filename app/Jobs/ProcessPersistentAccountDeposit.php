<?php

namespace App\Jobs;

use App\Models\LedgerEntry;
use App\Models\PersistentAccount;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPersistentAccountDeposit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public int $webhookEventId)
    {
    }

    public function handle(): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if (!$event) {
            Log::error('ProcessPersistentAccountDeposit: webhook event not found', ['id' => $this->webhookEventId]);
            return;
        }

        if ($event->processed_at !== null) {
            return;
        }

        $payload = $event->payload;

        $accountNumber = $payload['accountNumber'] ?? null;
        $rawAmount = $payload['amount'] ?? null;
        $transactionReference = $payload['transactionReference'] ?? $payload['referenceNumber'] ?? null;

        if (!$accountNumber || $rawAmount === null || !$transactionReference) {
            $event->update([
                'processing_error' => 'Missing accountNumber, amount, or transactionReference in payload.',
            ]);
            Log::error('Paga deposit webhook missing required fields', ['payload' => $payload]);
            return;
        }

        $amount = str_replace(',', '', (string) $rawAmount);

        if (!is_numeric($amount) || bccomp($amount, '0', 4) <= 0) {
            $event->update(['processing_error' => "Invalid or non-positive amount: {$rawAmount}"]);
            Log::error('Paga deposit webhook had an invalid amount', ['payload' => $payload]);
            return;
        }

        $account = PersistentAccount::where('account_identifier', $accountNumber)
            ->where('status', 'active')
            ->first();

        if (!$account) {
            $event->update([
                'processing_error' => "No active persistent_account found for account_identifier {$accountNumber}.",
            ]);
            Log::error('Paga deposit webhook referenced an unknown account', ['account_number' => $accountNumber]);
            return;
        }

        $idempotencyKey = "paga_deposit:{$transactionReference}";

        try {
            DB::transaction(function () use ($account, $amount, $idempotencyKey, $transactionReference, $payload) {
                $wallet = $account->wallet()->lockForUpdate()->first();

                LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'currency' => $wallet->currency,
                    'status' => 'completed',
                    'idempotency_key' => $idempotencyKey,
                    'provider' => 'paga',
                    'provider_reference' => $transactionReference,
                    'type' => 'nuban_deposit',
                    'description' => "Incoming deposit to {$wallet->currency} NUBAN",
                    'metadata' => $payload,
                    'completed_at' => now(),
                ]);

                $wallet->creditAtomically($amount);
            });
        } catch (QueryException $e) {
            Log::info('Duplicate deposit processing prevented by idempotency key', [
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        $event->update(['processed_at' => now()]);

        // TODO (not yet implemented): trigger the NGN<->PLN conversion +
        // credit to the customer's OTHER wallet, matching the investor
        // demo's behavior. Needs a live FX rate source and its own
        // ledger entries — deliberately scoped out of this webhook pass.
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPersistentAccountDeposit permanently failed', [
            'webhook_event_id' => $this->webhookEventId,
            'error' => $exception->getMessage(),
        ]);

        WebhookEvent::where('id', $this->webhookEventId)->update([
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
