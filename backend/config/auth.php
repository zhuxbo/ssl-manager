<?php

return [
    'guards' => [
        'admin' => [
            'driver' => 'jwt',
            'provider' => 'admins',
        ],
        'user' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'api-token',
            'provider' => 'api_tokens',
        ],
        'admin-refresh-token' => [
            'driver' => 'refresh-token',
            'provider' => 'admin_refresh_tokens',
        ],
        'user-refresh-token' => [
            'driver' => 'refresh-token',
            'provider' => 'user_refresh_tokens',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'api_tokens' => [
            'driver' => 'eloquent',
            'model' => App\Models\ApiToken::class,
        ],
        'admin_refresh_tokens' => [
            'driver' => 'eloquent',
            'model' => App\Models\AdminRefreshToken::class,
        ],
        'user_refresh_tokens' => [
            'driver' => 'eloquent',
            'model' => App\Models\UserRefreshToken::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 刷新令牌有效时间
    |--------------------------------------------------------------------------
    |
    | 由于每次刷新都会重新生成刷新令牌
    | 因此 这个时间相当于在多少分钟内 没有访问到接口 就会失效
    | 默认值是 1440 分钟，即 24 小时
    |
    */

    'refresh_token_ttl' => [
        'admin' => env('ADMIN_REFRESH_TOKEN_TTL', 1440),
        'user' => env('USER_REFRESH_TOKEN_TTL', 1440),
    ],
];
