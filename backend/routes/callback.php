<?php

use App\Http\Controllers\Callback\AgisoController;
use App\Http\Controllers\Callback\SslController;
use App\Http\Controllers\User\TopUpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Callback Routes
|--------------------------------------------------------------------------
*/

Route::prefix('callback')->group(function () {
    Route::post('ssl', [SslController::class, 'index']);
    Route::post('agiso', [AgisoController::class, 'index']);
    Route::post('alipay', [TopUpController::class, 'alipayNotify']);
    Route::post('wechat', [TopUpController::class, 'wechatNotify']);
});
