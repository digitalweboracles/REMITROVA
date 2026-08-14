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

/**
 * Turns a verified Paga persistent-account deposit webhook into an
 * actual wallet credit. This is the only place money enters a wallet
 * from an incoming NUBAN deposit — keeping that in one job (rather
 * than duplicating the crediting logic anywhere else) means there's
 * exactly one code path to audit for correctness.
 */
class ProcessPersistentAccountDeposit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900]; // seconds between retries

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
            return; // already handled — nothing to do (defensive; shouldn't normally happen)
        }

        $payload = $event->payload;

        // Paga's sample callback showed amount as a formatted string
        // with thousands separators (e.g. "200,000.00") rather than a
        // bare number — strip commas before treating it as a decimal.
        $accountNumber = $payload['accountNumber'] ?? null;
        $rawAmount = $payload['amount'] ?? null;
        $transactionReference = $payload['transactionReference'] ?? $payload['referenceNumber'] ?? null;

        if (!$accountNumber || $rawAmount === null || !$transactionReference) {
            $event->update([
                'processing_error' => 'Missing accountNumber, amount, or transactionReference in payload.',
            ]);
            Log::error('Paga deposit webhook missing required fields', ['payload' => $payload]);
            return; // don't retry — the payload itself is malformed, retrying won't fix that
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
            return; // don't retry — this won't resolve itself; needs manual investigation
        }

        // Deterministic idempotency key derived from Paga's own
        // transaction reference — NOT a freshly generated UUID. If this
        // job is ever re-run (queue retry after a crash between the
        // wallet credit and marking the event processed, for instance),
        // this key guarantees the ledger entry insert below fails on
        // the second attempt instead of double-crediting the wallet.
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
            // Unique constraint on idempotency_key tripped — this exact
            // deposit has already been credited. Treat as success, not
            // an error: the correct financial outcome (credited exactly
            // once) has already been achieved.
            Log::info('Duplicate deposit processing prevented by idempotency key', [
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        $event->update(['processed_at' => now()]);

        // TODO (Phase 1 follow-up, not yet implemented): trigger the
        // actual NGN->PLN / PLN->NGN conversion + credit to the
        // customer's OTHER wallet here, matching the product behavior
        // already built into the investor demo. That conversion step
        // needs a live FX rate source and its own ledger entries
        // (a debit from the receiving wallet, a credit to the spendable
        // wallet) — deliberately scoped out of this first webhook-handling
        // pass so this job stays focused on "did the deposit get
        // recorded correctly," which is the harder correctness problem.
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
