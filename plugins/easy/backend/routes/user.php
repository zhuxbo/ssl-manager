<?php

use Illuminate\Support\Facades\Route;
use Plugins\Easy\Controllers\User\RechargeController;

Route::prefix('api/user')->middleware(['global', 'api.user'])->group(function () {
    Route::post('platform-recharge', [RechargeController::class, 'handle']);
});
