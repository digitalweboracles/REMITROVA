<?php

use App\Http\Controllers\Api\PersistentAccountController;
use App\Http\Controllers\Webhooks\PagaPersistentAccountWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/paga/persistent-account', PagaPersistentAccountWebhookController::class)
    ->name('webhooks.paga.persistent-account');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/accounts/nuban', [PersistentAccountController::class, 'store'])
        ->name('accounts.nuban.store');
    Route::get('/accounts/nuban', [PersistentAccountController::class, 'show'])
        ->name('accounts.nuban.show');
});
