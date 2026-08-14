<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPersistentAccountDeposit;
use App\Models\WebhookEvent;
use App\Services\Payments\Paga\PagaHasher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Paga's Persistent Payment Account deposit callbacks.
 *
 * Verification uses the DYNAMIC x-paga-hash / x-paga-hash-parameters
 * header mechanism (Paga docs §12), which Paga's support team
 * explicitly confirmed is the current, correct approach — not the
 * older fixed-formula description in §11, which contradicted it.
 *
 * This endpoint is intentionally "dumb": verify -> log -> ACK fast ->
 * hand the real work to a queued job. Paga will retry a webhook that
 * doesn't get a fast 200, and doing slow work (DB writes touching
 * money, external calls) inline here risks either timing out or
 * processing the same event twice if a retry lands mid-processing.
 */
class PagaPersistentAccountWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $headers = $this->lowercaseHeaders($request->headers->all());
        $payload = $request->json()->all();

        $hashKey = config('paga.collect.hash_key');
        $verified = PagaHasher::verifyCallback($headers, $payload, $hashKey);

        if (!$verified) {
            Log::warning('Paga webhook failed hash verification', [
                'headers' => $headers,
                'payload' => $payload,
            ]);
            // Still 200 — returning an error code just makes Paga retry
            // a request that will never verify. Log it, don't act on it.
            // (If this fires in production, it means either the hash key
            // is wrong or someone is probing the endpoint — worth an
            // alert, not implemented here.)
            return response()->noContent();
        }

        $providerReference = $payload['transactionReference'] ?? $payload['referenceNumber'] ?? null;

        try {
            $event = WebhookEvent::create([
                'provider' => 'paga',
                'event_type' => 'persistent_account_deposit',
                'provider_reference' => $providerReference,
                'headers' => $headers,
                'payload' => $payload,
                'hash_verified' => true,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint on (provider, provider_reference) tripped
            // — we've already received and are handling/have handled this
            // exact event. This is the expected, correct outcome for a
            // Paga retry, not an error.
            Log::info('Duplicate Paga webhook received, already recorded', [
                'provider_reference' => $providerReference,
            ]);
            return response()->noContent();
        }

        ProcessPersistentAccountDeposit::dispatch($event->id);

        return response()->noContent();
    }

    private function lowercaseHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $out[strtolower($key)] = is_array($value) ? ($value[0] ?? null) : $value;
        }
        return $out;
    }
}
