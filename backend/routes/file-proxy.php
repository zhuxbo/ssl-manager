<?php

use App\Http\Controllers\FileProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| File Proxy Routes
|--------------------------------------------------------------------------
| 文件代理验证路由，供 CA 机构 http-01 和传统文件验证使用
| 用户需配置 Nginx 将域名的 /.well-known/ 请求代理到本系统
*/

Route::get('.well-known/acme-challenge/{token}', [FileProxyController::class, 'acmeChallenge']);
Route::get('.well-known/pki-validation/{filename}', [FileProxyController::class, 'pkiValidation']);
