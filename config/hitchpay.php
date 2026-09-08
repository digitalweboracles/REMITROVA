<?php

return [

    // Confirmed base URLs from HitchPay's published docs
    // (hitchpay.gitbook.io/hitchpay-api-doc) — sandbox and production
    // are genuinely different hosts, not just a path difference.
    'base_url' => env('HITCHPAY_BASE_URL', 'https://sandbox.hitchpay.ng'),

    'client_id' => env('HITCHPAY_CLIENT_ID'),
    'client_secret' => env('HITCHPAY_CLIENT_SECRET'),

];
