<?php

namespace App\Utils;

use Illuminate\Support\Facades\Route;

class RouteHelper
{
    /**
     * 注册标准资源路由
     */
    public static function registerResourceRoutes(string $prefix, string $controller): void
    {
        Route::prefix($prefix)->group(function () use ($controller) {
            Route::get('/', [$controller, 'index']);
            Route::post('/', [$controller, 'store']);
            Route::get('{id}', [$controller, 'show'])->where('id', '[0-9]+');
            Route::get('batch', [$controller, 'batchShow']);
            Route::put('{id}', [$controller, 'update'])->where('id', '[0-9]+');
            Route::delete('{id}', [$controller, 'destroy'])->where('id', '[0-9]+');
            Route::delete('batch', [$controller, 'batchDestroy']);
        });
    }
}
