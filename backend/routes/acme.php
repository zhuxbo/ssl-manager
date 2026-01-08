<?php

use App\Http\Controllers\Acme\Rfc8555\AccountController;
use App\Http\Controllers\Acme\Rfc8555\AuthorizationController;
use App\Http\Controllers\Acme\Rfc8555\CertificateController;
use App\Http\Controllers\Acme\Rfc8555\ChallengeController;
use App\Http\Controllers\Acme\Rfc8555\DirectoryController;
use App\Http\Controllers\Acme\Rfc8555\NonceController;
use App\Http\Controllers\Acme\Rfc8555\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ACME RFC 8555 Routes
|--------------------------------------------------------------------------
| 这些路由提供 ACME 协议端点，供 certbot 等 ACME 客户端使用
*/

Route::prefix('acme')->group(function () {
    // 目录（无需认证）
    Route::get('directory', [DirectoryController::class, 'index']);

    // Nonce（无需认证）
    Route::match(['get', 'head'], 'new-nonce', [NonceController::class, 'newNonce']);

    // 需要 JWS 验证的端点
    Route::middleware('api.acme')->group(function () {
        // 账户
        Route::post('new-acct', [AccountController::class, 'newAccount']);
        Route::post('acct/{keyId}', [AccountController::class, 'updateAccount']);

        // 订单
        Route::post('new-order', [OrderController::class, 'newOrder']);
        Route::post('order/{token}', [OrderController::class, 'getOrder']);
        Route::post('order/{token}/finalize', [OrderController::class, 'finalizeOrder']);

        // 授权
        Route::post('authz/{token}', [AuthorizationController::class, 'getAuthorization']);

        // 验证
        Route::post('chall/{token}', [ChallengeController::class, 'respondToChallenge']);

        // 证书
        Route::post('cert/{token}', [CertificateController::class, 'getCertificate']);
        Route::post('revoke-cert', [CertificateController::class, 'revokeCertificate']);
    });
});
