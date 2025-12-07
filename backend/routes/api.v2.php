<?php

use App\Http\Controllers\V2\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v2')->middleware('api.v2')->group(function () {
    Route::get('health', [ApiController::class, 'health']);
    Route::get('get-products', [ApiController::class, 'getProducts']);
    Route::get('get-orders', [ApiController::class, 'getOrders']);
    Route::post('new', [ApiController::class, 'new']);
    Route::post('renew', [ApiController::class, 'renew']);
    Route::post('reissue', [ApiController::class, 'reissue']);
    Route::get('get', [ApiController::class, 'get']);
    Route::get('get-order-id-by-refer-id', [ApiController::class, 'getOrderIdByReferId']);
    Route::post('cancel', [ApiController::class, 'cancel']);
    Route::post('revalidate', [ApiController::class, 'revalidate']);
    Route::post('update-dcv', [ApiController::class, 'updateDCV']);
    Route::get('download', [ApiController::class, 'download']);
});
