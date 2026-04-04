<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Api\V1\PayWayController;

/*
|--------------------------------------------------------------------------
| API Routes - Payment Module
|--------------------------------------------------------------------------
*/

// Public route - PayWay callback webhook (no auth required)
Route::prefix('v1')->group(function () {
    Route::post('payments/payway/callback', [PayWayController::class, 'callback'])
        ->name('payment.payway.callback');
});

// Protected routes (auth required)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('payments/payway/create', [PayWayController::class, 'createPurchase'])
        ->name('payment.payway.create');
    Route::get('payments/payway/check/{tranId}', [PayWayController::class, 'checkStatus'])
        ->name('payment.payway.check');
});
