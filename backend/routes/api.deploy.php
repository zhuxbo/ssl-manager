<?php

use App\Http\Controllers\Deploy\ApiController;
use Illuminate\Support\Facades\Route;

// Deploy API 路由
// 需要 Deploy Token 认证
Route::prefix('deploy')->middleware('api.deploy')->group(function () {
    Route::get('cert', [ApiController::class, 'cert']);      // 按域名查询证书
    Route::post('cert', [ApiController::class, 'update']);   // 更新/续费证书
    Route::post('callback', [ApiController::class, 'callback']); // 部署回调
});
