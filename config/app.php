<?php

return [
    'name' => env('APP_NAME', 'RemitRova'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],

    // TEMPORARY — see routes/web.php's /dev/* routes.
    // Remove this along with those routes before going near production.
    'dev_seed_key' => env('DEV_SEED_KEY'),
];
