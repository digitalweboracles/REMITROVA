<?php

namespace App\Providers;

use App\Services\Payments\Paga\PagaCollectClient;
use Illuminate\Support\ServiceProvider;

class PagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PagaCollectClient::class, function () {
            return new PagaCollectClient(
                baseUrl: config('paga.collect.base_url'),
                principal: config('paga.collect.principal'),
                secretKey: config('paga.collect.secret_key'),
                hashKey: config('paga.collect.hash_key'),
            );
        });
    }
}
