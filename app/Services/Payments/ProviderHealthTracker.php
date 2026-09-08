<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks each provider's recent reliability so the manager can skip a
 * provider that's currently down, instead of waiting out a timeout
 * against it on every single request while it's failing.
 *
 * This is a simple circuit breaker: after too many consecutive
 * failures, a provider is marked "open" (unhealthy) for a cooldown
 * window. Once the cooldown expires, the next request is allowed
 * through as a trial — if it succeeds, the provider is trusted again
 * immediately; if it fails, the cooldown restarts.
 *
 * Backed by the app's existing Redis cache — no new infrastructure
 * needed, and state is shared correctly across all app instances/
 * queue workers rather than living in a single process's memory.
 */
class ProviderHealthTracker
{
    /** Consecutive failures before a provider is considered down. */
    private const FAILURE_THRESHOLD = 3;

    /** How long a provider stays skipped after tripping the breaker. */
    private const COOLDOWN_SECONDS = 60;

    public function isHealthy(string $providerName): bool
    {
        return !Cache::has($this->downKey($providerName));
    }

    public function recordSuccess(string $providerName): void
    {
        Cache::forget($this->failureCountKey($providerName));
        Cache::forget($this->downKey($providerName));
    }

    public function recordFailure(string $providerName): void
    {
        $key = $this->failureCountKey($providerName);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes(5));

        if ($count >= self::FAILURE_THRESHOLD) {
            Cache::put($this->downKey($providerName), true, now()->addSeconds(self::COOLDOWN_SECONDS));
        }
    }

    private function failureCountKey(string $providerName): string
    {
        return "provider_health:{$providerName}:failures";
    }

    private function downKey(string $providerName): string
    {
        return "provider_health:{$providerName}:down";
    }
}
