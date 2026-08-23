<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPersistentAccountDeposit;
use App\Models\WebhookEvent;
use App\Services\Payments\Paga\PagaHasher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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
