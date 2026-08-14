<?php

use App\Http\Controllers\Api\PersistentAccountController;
use App\Http\Controllers\Webhooks\PagaPersistentAccountWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks (public, verified by hash — not by auth middleware)
|--------------------------------------------------------------------------
| IMPORTANT: exclude this route from CSRF verification in
| bootstrap/app.php or App\Http\Middleware\VerifyCsrfToken, since Paga's
| server (not a browser with our session) is the caller.
*/
Route::post('/webhooks/paga/persistent-account', PagaPersistentAccountWebhookController::class)
    ->name('webhooks.paga.persistent-account');

/*
|--------------------------------------------------------------------------
| Authenticated customer API (Phase 1 scope: NUBAN provisioning only)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/accounts/nuban', [PersistentAccountController::class, 'store'])
        ->name('accounts.nuban.store');
    Route::get('/accounts/nuban', [PersistentAccountController::class, 'show'])
        ->name('accounts.nuban.show');
});
