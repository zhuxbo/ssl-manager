<?php

namespace App\Providers;

use App\Models\Cert;
use App\Observers\CertObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 加载所有辅助函数文件
        $helperPath = app_path('Helpers');
        foreach (glob($helperPath.'/*.php') as $file) {
            require_once $file;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cert::observe(CertObserver::class);
    }
}
