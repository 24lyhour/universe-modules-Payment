<?php

namespace Modules\Payment\Http\Controllers\Dashboard\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Order\Models\Transaction;
use Modules\Outlet\Models\Outlet;
use Modules\Payment\Http\Resources\Dashboard\V1\TransactionResource;
use Modules\Payment\Services\TransactionService;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {
    }

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'type', 'payment_method', 'outlet_id', 'date_from', 'date_to']);
        $perPage = $request->integer('per_page', 10);

        $transactions = $this->transactionService->paginate($perPage, $filters);
        $outlets = Outlet::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('payment::Dashboard/V1/Transaction/Index', [
            'transactionItems' => TransactionResource::collection($transactions)->response()->getData(true),
            'filters' => $filters,
            'stats' => $this->transactionService->getStats(),
            'outlets' => $outlets,
            'statuses' => $this->getStatusOptions(),
            'paymentMethods' => $this->getPaymentMethodOptions(),
        ]);
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction): Response
    {
        $transaction->load(['order.outlet', 'order.items.product', 'order.shipping', 'customer']);

        return Inertia::render('payment::Dashboard/V1/Transaction/Show', [
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ]);
    }

    /**
     * Export transactions.
     */
    public function export(Request $request)
    {
        // TODO: Implement export
        return redirect()->route('payment.transactions.index')
            ->with('info', 'Export feature coming soon.');
    }

    /**
     * Get status options for filter.
     */
    private function getStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'failed', 'label' => 'Failed'],
        ];
    }

    /**
     * Get payment method options for filter.
     */
    private function getPaymentMethodOptions(): array
    {
        return [
            ['value' => 'wallet', 'label' => 'Wallet'],
            ['value' => 'cash', 'label' => 'Cash on Delivery'],
            ['value' => 'aba_payway', 'label' => 'ABA PayWay'],
            ['value' => 'credit_card', 'label' => 'Credit Card'],
        ];
    }
}
