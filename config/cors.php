<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // The demo frontend is hosted on a completely different domain
    // than this API, so it must be explicitly allowed here or every
    // browser request will be blocked by CORS before it even reaches
    // Laravel's routing.
    'allowed_origins' => [
        'https://remitrova-app.edgeone.dev',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
