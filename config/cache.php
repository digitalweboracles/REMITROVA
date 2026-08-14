<?php

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'lock_connection' => 'default',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'remitrova_cache_'),
];
