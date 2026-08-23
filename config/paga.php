<?php

return [

    'collect' => [
        'base_url' => env('PAGA_COLLECT_BASE_URL', 'https://beta-collect.paga.com'),
        'principal' => env('PAGA_PRINCIPAL'),
        'secret_key' => env('PAGA_SECRET_KEY'),
        'hash_key' => env('PAGA_HASH_KEY'),
    ],

    'business' => [
        'base_url' => env(
            'PAGA_BUSINESS_BASE_URL',
            'https://beta.mypaga.com/paga-webservices/business-rest/secured'
        ),
        'principal' => env('PAGA_PRINCIPAL'),
        'credentials' => env('PAGA_SECRET_KEY'),
        'hash_key' => env('PAGA_HASH_KEY'),
    ],

    // Confirmed in Paga's docs: on all environments, Paga's own "bank"
    // for wallet-style (non-external-bank) validation is this fixed UUID.
    'paga_own_bank_uuid' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',

    'imto' => [
        'string_placeholder' => 'N/A',
        'numeric_placeholder' => 0,
    ],

];
