<?php

namespace Modules\Payment\Http\Controllers\Dashboard\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Order\Models\Transaction;
use Modules\Outlet\Models\Outlet;
use Modules\Payment\Http\Requests\Dashboard\V1\UpdatePaymentSettingsRequest;

class PaymentSettingsController extends Controller
{
    /**
     * Display payment settings.
     */
    public function index(): Response
    {
        return Inertia::render('payment::Dashboard/V1/Settings/Index', [
            'settings' => $this->getSettings(),
            'stats' => $this->getPaymentStats(),
            'outletPayway' => $this->getOutletPayWaySummary(),
        ]);
    }

    /**
     * Update payment settings.
     */
    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        return redirect()->route('payment.settings.index')
            ->with('success', 'Payment settings updated successfully.');
    }

    /**
     * Get current payment settings.
     */
    private function getSettings(): array
    {
        return [
            'payment_methods' => [
                [
                    'id' => 'wallet',
                    'name' => 'Wallet',
                    'description' => 'Pay using wallet balance',
                    'enabled' => true,
                    'icon' => 'wallet',
                    'accepted_brands' => [
                        ['name' => 'Wallet', 'logo' => '/images/payments/wallet.svg'],
                    ],
                ],
                [
                    'id' => 'cash',
                    'name' => 'Cash on Delivery',
                    'description' => 'Pay when order is delivered',
                    'enabled' => true,
                    'icon' => 'banknote',
                    'accepted_brands' => [
                        ['name' => 'Cash', 'logo' => '/images/payments/cash.svg'],
                    ],
                ],
                [
                    'id' => 'aba_payway',
                    'name' => 'ABA PayWay',
                    'description' => 'KHQR, Visa, Mastercard, JCB, Alipay, WeChat',
                    'enabled' => true,
                    'icon' => 'credit-card',
                    'has_credentials' => ! empty(config('payment.payway.merchant_id')),
                    'merchant_id' => config('payment.payway.merchant_id'),
                    'base_url' => config('payment.payway.base_url'),
                    'is_sandbox' => str_contains(config('payment.payway.base_url', ''), 'sandbox'),
                    'accepted_brands' => [
                        ['name' => 'KHQR', 'logo' => '/images/payments/aba_khqr.webp'],
                        ['name' => 'Visa', 'logo' => '/images/payments/visa.svg'],
                        ['name' => 'Mastercard', 'logo' => '/images/payments/mastercard.svg'],
                        ['name' => 'JCB', 'logo' => '/images/payments/jcb.svg'],
                    ],
                ],
                [
                    'id' => 'credit_card',
                    'name' => 'Credit Card',
                    'description' => 'Visa, Mastercard, JCB',
                    'enabled' => false,
                    'icon' => 'credit-card',
                    'coming_soon' => true,
                    'accepted_brands' => [
                        ['name' => 'Visa', 'logo' => '/images/payments/visa.svg'],
                        ['name' => 'Mastercard', 'logo' => '/images/payments/mastercard.svg'],
                        ['name' => 'JCB', 'logo' => '/images/payments/jcb.svg'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get payment stats per method.
     */
    private function getPaymentStats(): array
    {
        $methods = Transaction::select('payment_method', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count'), DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count'), DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'), DB::raw('SUM(CASE WHEN status = "completed" AND type = "payment" THEN amount ELSE 0 END) as revenue'), DB::raw('SUM(CASE WHEN status = "completed" AND type = "refund" THEN amount ELSE 0 END) as refunded'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $totalTransactions = Transaction::count();
        $totalRevenue = (float) Transaction::where('status', 'completed')
            ->where('type', 'payment')
            ->sum('amount');

        return [
            'total_transactions' => $totalTransactions,
            'total_revenue' => $totalRevenue,
            'by_method' => $methods->map(fn ($m) => [
                'total_count' => (int) $m->total_count,
                'completed_count' => (int) $m->completed_count,
                'failed_count' => (int) $m->failed_count,
                'pending_count' => (int) $m->pending_count,
                'revenue' => (float) $m->revenue,
                'refunded' => (float) $m->refunded,
            ])->toArray(),
        ];
    }

    /**
     * Get outlet PayWay configuration summary.
     */
    private function getOutletPayWaySummary(): array
    {
        $outlets = Outlet::select('id', 'uuid', 'name', 'payway_merchant_id', 'payway_enabled')
            ->whereNotNull('payway_merchant_id')
            ->orderBy('name')
            ->get();

        $totalOutlets = Outlet::count();

        return [
            'total_outlets' => $totalOutlets,
            'configured_count' => $outlets->count(),
            'enabled_count' => $outlets->where('payway_enabled', true)->count(),
            'outlets' => $outlets->map(fn ($o) => [
                'uuid' => $o->uuid,
                'name' => $o->name,
                'merchant_id' => $o->payway_merchant_id,
                'enabled' => (bool) $o->payway_enabled,
            ])->values()->toArray(),
        ];
    }
}
