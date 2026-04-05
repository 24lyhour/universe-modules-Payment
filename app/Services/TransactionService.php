<?php

namespace Modules\Payment\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\Order\Enums\TransactionStatusEnum;
use Modules\Order\Models\Transaction;

class TransactionService
{
    /**
     * Get paginated transactions with filters.
     */
    public function paginate(int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->with(['order.outlet', 'customer']);

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('gateway_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%"));
            });
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Type filter
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        // Payment method filter
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Outlet filter (via order)
        if (!empty($filters['outlet_id'])) {
            $query->whereHas('order', fn ($q) => $q->where('outlet_id', $filters['outlet_id']));
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get transaction statistics.
     */
    public function getStats(): array
    {
        return Cache::remember('payment_transaction_stats', 300, function () {
            return [
                'total' => Transaction::count(),
                'pending' => Transaction::where('status', TransactionStatusEnum::Pending)->count(),
                'processing' => Transaction::where('status', 'processing')->count(),
                'completed' => Transaction::where('status', TransactionStatusEnum::Completed)->count(),
                'failed' => Transaction::where('status', TransactionStatusEnum::Failed)->count(),
                'total_revenue' => (float) Transaction::where('status', TransactionStatusEnum::Completed)
                    ->where('type', Transaction::TYPE_PAYMENT)
                    ->sum('amount'),
                'total_refunded' => (float) Transaction::where('status', TransactionStatusEnum::Completed)
                    ->where('type', Transaction::TYPE_REFUND)
                    ->sum('amount'),
            ];
        });
    }

    /**
     * Clear statistics cache.
     */
    public function clearStatsCache(): void
    {
        Cache::forget('payment_transaction_stats');
    }
}
