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

            // ACME RFC 8555 路由
            Route::middleware('global')->group($routePath.'/acme.php');

            // 文件代理验证路由（无 global 中间件，公开端点无需路由绑定）
            require $routePath.'/file-proxy.php';
        });
    }
}
