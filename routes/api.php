<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersistentAccountController;
use App\Http\Controllers\Webhooks\PagaPersistentAccountWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/paga/persistent-account', PagaPersistentAccountWebhookController::class)
    ->name('webhooks.paga.persistent-account');

Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::post('/accounts/nuban', [PersistentAccountController::class, 'store'])
        ->name('accounts.nuban.store');
    Route::get('/accounts/nuban', [PersistentAccountController::class, 'show'])
        ->name('accounts.nuban.show');
});
