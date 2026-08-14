<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paga Collect API (Static/Persistent NUBAN)
    |--------------------------------------------------------------------------
    | Sandbox base URL confirmed by Paga: https://beta-collect.paga.com
    | Production base URL: https://collect.paga.com
    */
    'collect' => [
        'base_url' => env('PAGA_COLLECT_BASE_URL', 'https://beta-collect.paga.com'),
        'principal' => env('PAGA_PRINCIPAL'),
        'secret_key' => env('PAGA_SECRET_KEY'),
        'hash_key' => env('PAGA_HASH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paga Business API (Disbursement / Deposit To Bank)
    |--------------------------------------------------------------------------
    | Sandbox base URL confirmed by Paga (2026 support thread):
    |   https://beta.mypaga.com/paga-webservices/business-rest/secured
    | The production-looking URL shown in some of Paga's own doc code
    | samples is illustrative only and must NOT be used for sandbox
    | testing — Paga confirmed this explicitly.
    */
    'business' => [
        'base_url' => env(
            'PAGA_BUSINESS_BASE_URL',
            'https://beta.mypaga.com/paga-webservices/business-rest/secured'
        ),
        'principal' => env('PAGA_PRINCIPAL'),
        'credentials' => env('PAGA_SECRET_KEY'),
        'hash_key' => env('PAGA_HASH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paga's own NUBAN sentinel bank UUID
    |--------------------------------------------------------------------------
    | Confirmed in Paga's docs: on all environments, Paga's own "bank" for
    | wallet-style (non-external-bank) validation is this fixed UUID.
    */
    'paga_own_bank_uuid' => 'AAAAAAAA-AAAA-AAAA-AAAA-AAAAAAAAAAAA',

    /*
    |--------------------------------------------------------------------------
    | IMTO compliance fields
    |--------------------------------------------------------------------------
    | Paga confirmed: since RemitRova is classified as an IMTO, the
    | sender/recipient/FX fields below are MANDATORY on every Deposit To
    | Bank / Money Transfer call, in both sandbox and production. Where a
    | field genuinely doesn't apply to a given transaction, Paga's
    | guidance is to send a static placeholder rather than omit it:
    | numeric fields -> 0, string fields -> "N/A".
    */
    'imto' => [
        'string_placeholder' => 'N/A',
        'numeric_placeholder' => 0,
    ],

];
