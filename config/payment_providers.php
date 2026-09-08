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
    | HitchPay is registered here as a stub (see
    | App\Services\Payments\HitchPay\HitchPayProvider) — it will always
    | fail until a real HitchPay integration replaces the stub, but its
    | presence here proves the failover chain has a second link.
    */
    'provider_order' => ['paga', 'hitchpay'],

];
