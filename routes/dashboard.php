<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\Dashboard\V1\TransactionController;
use Modules\Payment\Http\Controllers\Dashboard\V1\PaymentSettingsController;

Route::middleware(['auth', 'verified', 'auto.permission'])
    ->prefix('dashboard')
    ->group(function () {
        // ==================== TRANSACTION ROUTES ====================

        // Payment Transactions - Export (BEFORE resource)
        Route::get('payment-transactions/export', [TransactionController::class, 'export'])
            ->name('payment.transactions.export');

        // Payment Transactions CRUD (Index + Show only, no create/edit/delete)
        Route::resource('payment-transactions', TransactionController::class)
            ->names('payment.transactions')
            ->parameters(['payment-transactions' => 'transaction'])
            ->only(['index', 'show']);

        // ==================== PAYMENT SETTINGS ROUTES ====================

        Route::get('payment-settings', [PaymentSettingsController::class, 'index'])
            ->name('payment.settings.index');
        Route::put('payment-settings', [PaymentSettingsController::class, 'update'])
            ->name('payment.settings.update');
    });
