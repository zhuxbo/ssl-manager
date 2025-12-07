<?php

use App\Http\Controllers\Easy\IndexController;
use Illuminate\Support\Facades\Route;

Route::prefix('easy')->group(function () {
    Route::post('check', [IndexController::class, 'check']);
    Route::post('apply', [IndexController::class, 'apply']);
    Route::post('revalidate', [IndexController::class, 'revalidate']);
    Route::post('sync', [IndexController::class, 'sync']);
    Route::post('update-validation-method', [IndexController::class, 'updateValidationMethod']);
    Route::post('validate-file', [IndexController::class, 'validateFile']);
    Route::post('download', [IndexController::class, 'download']);
});
