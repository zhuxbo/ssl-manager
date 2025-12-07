<?php

return [
    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => env('ALLOWED_ORIGINS', 'localhost'),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Content-Length',
        'Authorization',
        'X-Requested-With',
        'Api-Token',
        'Token',
        'X-Timezone',
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Headers',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Credentials',
    ],

    'exposed_headers' => ['Content-Disposition', 'Content-Length', 'Content-Type'],

    'max_age' => 7200,

    'supports_credentials' => 'true',
];
