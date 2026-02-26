<?php

use Illuminate\Support\Facades\Route;
use Plugins\Easy\Controllers\CallbackController;

Route::prefix('callback')->middleware('global')->group(function () {
    Route::post('agiso', [CallbackController::class, 'index']);
});
