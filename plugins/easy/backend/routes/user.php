<?php

use Illuminate\Support\Facades\Route;
use Plugins\Easy\Controllers\User\RechargeController;

Route::prefix('api')->middleware(['global', 'api.user'])->group(function () {
    Route::post('fund/platform-recharge', [RechargeController::class, 'handle']);
});
