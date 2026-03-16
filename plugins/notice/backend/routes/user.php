<?php

use Illuminate\Support\Facades\Route;
use Plugins\Notice\Controllers\User\NoticeController;

Route::prefix('api/user/notice')->middleware(['global', 'api.user'])->group(function () {
    Route::get('active', [NoticeController::class, 'active']);
});
