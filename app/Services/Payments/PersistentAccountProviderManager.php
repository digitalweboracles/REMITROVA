<?php

namespace App\Services\Payments;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PersistentAccountProviderManager — this is the "active-active
 * failover" piece. It holds an ordered list of providers and, on each
 * request, tries them in order: skip anything the health tracker
 * currently considers down, attempt the rest in priority order, and
 * on the first success, return immediately. A single customer-facing
 * request never knows or cares which provider actually answered.
 *
 * If a provider fails, that failure is recorded so repeated failures
 * trip its circuit breaker and it gets skipped entirely on subsequent
 * requests until its cooldown passes — instead of every future
 * request paying the cost of trying (and timing out against) a
 * provider that's currently down.
 */
class PersistentAccountProviderManager
{
    /** @param PersistentAccountProviderInterface[] $providers In priority order — first is tried first. */
    public function __construct(
        private readonly array $providers,
        private readonly ProviderHealthTracker $health,
    ) {
    }

    public function createPersistentAccount(Customer $customer, string $accountReference, string $callbackUrl): ProviderAccountResult
    {
        $attempted = [];
        $lastException = null;

        foreach ($this->providers as $provider) {
            $name = $provider->name();

            if (!$this->health->isHealthy($name)) {
                Log::info("Skipping provider '{$name}' — currently marked down by circuit breaker.");
                continue;
            }

            $attempted[] = $name;

            try {
                $result = $provider->createPersistentAccount($customer, $accountReference, $callbackUrl);
                $this->health->recordSuccess($name);

                if ($name !== $this->providers[0]->name()) {
                    Log::warning("Persistent account created via fallback provider '{$name}' — primary provider was unavailable or failed.");
                }

                return $result;
            } catch (ProviderRequestFailedException $e) {
                Log::warning("Provider '{$name}' failed, trying next available provider if any.", [
                    'error' => $e->getMessage(),
                ]);
                $this->health->recordFailure($name);
                $lastException = $e;
            }
        }

        $triedList = $attempted ? implode(', ', $attempted) : 'none (all providers currently marked down)';
        throw new RuntimeException(
            "All available payment providers failed or were unavailable. Tried: {$triedList}.",
            previous: $lastException,
        );
    }
}
