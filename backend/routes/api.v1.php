<?php

use App\Http\Controllers\V1\ApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes (Deprecated)
|--------------------------------------------------------------------------
*/
Route::prefix('V1')->middleware('api.v1')->group(function () {
    Route::get('health', [ApiController::class, 'health']);
    Route::post('product', [ApiController::class, 'getProducts']);
    Route::post('new', [ApiController::class, 'new']);
    Route::post('renew', [ApiController::class, 'renew']);
    Route::post('reissue', [ApiController::class, 'reissue']);
    Route::post('get', [ApiController::class, 'get']);
    Route::post('getOidByReferId', [ApiController::class, 'getOrderIdByReferId']);
    Route::post('cancel', [ApiController::class, 'cancel']);
    Route::post('revalidate', [ApiController::class, 'revalidate']);
    Route::post('updateDCV', [ApiController::class, 'updateDCV']);
    Route::post('download', [ApiController::class, 'download']);
});
