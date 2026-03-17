<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            $routePath = base_path('routes');

            Route::prefix('api')->group(function () use ($routePath) {
                // 自动加载 routes 文件夹中的所有 API 路由文件
                foreach (glob($routePath.'/api.*.php') as $routeFile) {
                    Route::middleware('global')->group($routeFile);
                }
            });

            Route::middleware('global')->group($routePath.'/callback.php');
        });
    }
}
