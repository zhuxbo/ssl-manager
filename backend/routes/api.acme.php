<?php

use App\Http\Controllers\Acme\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ACME REST API Routes
|--------------------------------------------------------------------------
| 这些路由提供 REST API 端点，供下级 Manager 调用
| 与 Gateway 的 API 接口保持一致
*/

Route::prefix('acme')->middleware('api.v2')->group(function () {
    // 订单管理（具名子路径必须在通配路由之前）
    Route::post('orders', [ApiController::class, 'createOrder']);
    Route::post('orders/reissue/{id}', [ApiController::class, 'reissueOrder'])->where('id', '[0-9]+');
    Route::get('orders/authorizations/{id}', [ApiController::class, 'getOrderAuthorizations'])->where('id', '[0-9]+');
    Route::post('orders/finalize/{id}', [ApiController::class, 'finalizeOrder'])->where('id', '[0-9]+');
    Route::get('orders/certificate/{id}', [ApiController::class, 'getCertificate'])->where('id', '[0-9]+');
    Route::get('orders/{id}', [ApiController::class, 'getOrder'])->where('id', '[0-9]+');
    Route::delete('orders/{id}', [ApiController::class, 'cancelOrder'])->where('id', '[0-9]+');

    // 验证管理
    Route::post('challenges/respond/{id}', [ApiController::class, 'respondToChallenge'])->where('id', '[0-9]+');

    // 证书管理
    Route::post('certificates/revoke', [ApiController::class, 'revokeCertificate']);
});
