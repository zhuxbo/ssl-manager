<?php

use App\Utils\RouteHelper;
use Illuminate\Support\Facades\Route;
use Plugins\Invoice\Controllers\User\InvoiceController;

Route::prefix('api')->middleware(['global', 'api.user'])->group(function () {
    RouteHelper::registerResourceRoutes('invoice', InvoiceController::class);
    Route::get('invoice/quota', [InvoiceController::class, 'quota']);
});
