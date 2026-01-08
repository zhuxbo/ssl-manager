<?php

use App\Http\Controllers\Acme\Api\AcmeApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ACME REST API Routes
|--------------------------------------------------------------------------
| 这些路由提供 REST API 端点，供下级 Manager 调用
| 与 Gateway 的 API 接口保持一致
*/

Route::prefix('acme')->middleware('api.v2')->group(function () {
    // 账户管理
    Route::post('accounts', [AcmeApiController::class, 'createAccount']);
    Route::get('accounts/{id}', [AcmeApiController::class, 'getAccount']);

    // 订单管理
    Route::post('orders', [AcmeApiController::class, 'createOrder']);
    Route::get('orders/{id}', [AcmeApiController::class, 'getOrder']);
    Route::get('orders/{id}/authorizations', [AcmeApiController::class, 'getOrderAuthorizations']);
    Route::post('orders/{id}/finalize', [AcmeApiController::class, 'finalizeOrder']);
    Route::get('orders/{id}/certificate', [AcmeApiController::class, 'getCertificate']);

    // 验证管理
    Route::post('challenges/{id}/respond', [AcmeApiController::class, 'respondToChallenge']);

    // 证书管理
    Route::post('certificates/{id}/revoke', [AcmeApiController::class, 'revokeCertificate']);
});
