<?php

use App\Http\Controllers\DcvController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

// 供管理端、用户端及匿名简易申请页回落使用；不经过业务 API token 限流。
Route::middleware([ThrottleRequests::class.':120,1', 'cache.headers:no_store;private'])->group(function () {
    Route::post('dcv/verify', [DcvController::class, 'verify']);
    Route::post('dns/query', [DcvController::class, 'query']);
});
