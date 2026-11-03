<?php

namespace App\Providers;

use App\Services\Payments\HitchPay\HitchPayClient;
use App\Services\Payments\HitchPay\HitchPayProvider;
use App\Services\Payments\Paga\PagaCollectClient;
use App\Services\Payments\Paga\PagaProvider;
use App\Services\Payments\PersistentAccountProviderManager;
use App\Services\Payments\ProviderHealthTracker;
use Illuminate\Support\ServiceProvider;

class PagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PagaCollectClient::class, function () {
            return new PagaCollectClient(
                baseUrl: (string) config('paga.collect.base_url'),
                principal: (string) config('paga.collect.principal'),
                secretKey: (string) config('paga.collect.secret_key'),
                hashKey: (string) config('paga.collect.hash_key'),
            );
        });

        $this->app->singleton(HitchPayClient::class, function () {
            return new HitchPayClient(
                baseUrl: (string) config('hitchpay.base_url'),
                clientId: (string) config('hitchpay.client_id'),
                clientSecret: (string) config('hitchpay.client_secret'),
            );
        });

        $this->app->singleton(ProviderHealthTracker::class);

        // Builds the ordered provider list from config/payment_providers.php,
        // so adding/reordering providers is a config change, not a code change.
        $this->app->singleton(PersistentAccountProviderManager::class, function ($app) {
            $available = [
                'paga' => fn () => new PagaProvider($app->make(PagaCollectClient::class)),
                'hitchpay' => fn () => new HitchPayProvider($app->make(HitchPayClient::class)),
            ];

            $providers = [];
            // A provider with no credentials set is skipped rather than crashing
            // the whole app, so one unconfigured provider never blocks the others.
            $configured = [
                'paga' => config('paga.collect.principal') && config('paga.collect.secret_key') && config('paga.collect.hash_key'),
                'hitchpay' => config('hitchpay.client_id') && config('hitchpay.client_secret'),
            ];

            foreach (config('payment_providers.provider_order', ['paga']) as $key) {
                if (isset($available[$key]) && !empty($configured[$key])) {
                    $providers[] = $available[$key]();
                }
            }

            return new PersistentAccountProviderManager($providers, $app->make(ProviderHealthTracker::class));
        });
    }
}
