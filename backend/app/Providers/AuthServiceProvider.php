<?php

namespace App\Providers;

use App\Auth\ExtendedTokenGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::extend('api-token', function ($app, $name, array $config) {
            return new ExtendedTokenGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $config['inputKey'] ?? 'token',
                $config['storageKey'] ?? 'token',
                $config['hash'] ?? true,
            );
        });

        Auth::extend('refresh-token', function ($app, $name, array $config) {
            return new ExtendedTokenGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $config['inputKey'] ?? 'refresh_token',
                $config['storageKey'] ?? 'refresh_token',
                $config['hash'] ?? true,
            );
        });
    }
}
