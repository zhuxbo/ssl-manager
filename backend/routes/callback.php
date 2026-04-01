<?php

use App\Http\Controllers\Callback\CallbackController;
use App\Http\Controllers\User\TopUpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Callback Routes
|--------------------------------------------------------------------------
*/

Route::prefix('callback')->group(function () {
    Route::post('alipay', [TopUpController::class, 'alipayNotify']);
    Route::post('wechat', [TopUpController::class, 'wechatNotify']);
    Route::post('{endpoint?}', [CallbackController::class, 'index']);
});
