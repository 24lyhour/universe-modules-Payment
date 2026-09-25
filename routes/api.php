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

// Test routes - only available in local/testing environments
if (app()->environment(['local', 'testing'])) {
    Route::prefix('v1')->group(function () {
        Route::post('payway/test-purchase', function () {
            $service = app(\Modules\Payment\Services\PayWayService::class);
            $tranId = $service->generateTranId(time());

            $result = $service->createPurchase([
                'tran_id' => $tranId,
                'amount' => '0.01',
                'firstname' => 'Test',
                'lastname' => 'User',
                'email' => 'test@example.com',
                'phone' => '012345678',
                'payment_option' => 'abapay_khqr_deeplink',
                'currency' => 'USD',
            ]);

            return response()->json($result->toArray());
        });

        Route::post('payway/test-qr', function () {
            $service = app(\Modules\Payment\Services\PayWayService::class);
            $tranId = $service->generateTranId(time());

            $result = $service->generateQr([
                'tran_id' => $tranId,
                'amount' => '0.01',
                'firstname' => 'Test',
                'lastname' => 'User',
                'currency' => 'USD',
                'lifetime' => 5,
            ]);

            return response()->json($result->toArray());
        });

        Route::get('payway/check-status/{tranId}', function (string $tranId) {
            $service = app(\Modules\Payment\Services\PayWayService::class);
            $result = $service->checkTransaction($tranId);

            return response()->json($result->toArray());
        });
    });
}

// Protected routes (auth required)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('payments/payway/create', [PayWayController::class, 'createPurchase'])
        ->name('payment.payway.create');
    Route::get('payments/payway/check/{tranId}', [PayWayController::class, 'checkStatus'])
        ->name('payment.payway.check');
    Route::get('payments/payway/topup-status/{tranId}', [PayWayController::class, 'checkTopUpStatus'])
        ->name('payment.payway.topup-status');
});
