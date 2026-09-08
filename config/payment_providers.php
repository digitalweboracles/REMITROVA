<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider priority order for persistent account (NUBAN) issuance
    |--------------------------------------------------------------------------
    | Tried in this order. If the first fails (or is currently circuit-
    | broken from repeated recent failures), the next one is tried
    | automatically — this is the active-active failover list.
    |
    | HitchPay first (2026-09-08): both providers are now genuinely
    | implemented (see App\Services\Payments\HitchPay and \Paga), but
    | Paga's sandbox is still returning an unresolved 401 hash error
    | despite extensive back-and-forth with their support — moved to
    | fallback position until that's resolved. Swap this back once
    | Paga's issue is fixed, or leave both running active-active.
    */
    'provider_order' => ['hitchpay', 'paga'],

];
