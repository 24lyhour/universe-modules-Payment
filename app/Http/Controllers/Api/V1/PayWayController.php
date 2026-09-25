<?php

namespace Modules\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Enums\BookingStatus;
use Modules\Booking\Enums\PaymentStatus as BookingPaymentStatus;
use Modules\Booking\Models\BookingTransaction;
use Modules\Order\Enums\PaymentStatusEnum;
use Modules\Order\Models\Order;
use Modules\Order\Models\Transaction;
use Modules\Payment\Events\PaymentCompleted;
use Modules\Payment\Services\PayWayService;
use Modules\Wallets\Models\TopUp;

class PayWayController extends Controller
{
    public function __construct(
        protected PayWayService $payWayService
    ) {}

    /**
     * Create a PayWay purchase for an order.
     * Called by OrderController after creating the order.
     */
    public function createPurchase(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $customer = $request->user();
        $order = Order::where('id', $request->order_id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->payment_status === PaymentStatusEnum::Paid) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        // Generate unique transaction ID
        $tranId = $this->payWayService->generateTranId($order->id);

        // Create pending transaction record
        $transaction = $order->transactions()->create([
            'type' => Transaction::TYPE_PAYMENT,
            'payment_method' => 'aba_payway',
            'amount' => $order->total_amount,
            'net_amount' => $order->total_amount,
            'currency' => 'USD',
            'status' => 'pending',
            'customer_id' => $customer->id,
            'gateway_transaction_id' => $tranId,
            'notes' => "PayWay payment for order #{$order->order_number}",
        ]);

        // Build items for PayWay
        $items = [];
        $order->load('items');
        foreach ($order->items as $item) {
            $items[] = [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
            ];
        }

        // Call PayWay API
        $result = $this->payWayService->createPurchase([
            'tran_id' => $tranId,
            'amount' => (float) $order->total_amount,
            'firstname' => $customer->name ?? '',
            'lastname' => '',
            'email' => $customer->email ?? '',
            'phone' => $customer->phone ?? '',
            'payment_option' => 'abapay_khqr_deeplink',
            'currency' => 'USD',
            'items' => $items,
            'return_params' => $order->uuid,
        ]);

        if ($result['success']) {
            $transaction->update([
                'status' => 'processing',
                'gateway_response' => $result['data'],
            ]);

            return response()->json([
                'message' => 'Payment initiated.',
                'data' => [
                    'tran_id' => $tranId,
                    'order_uuid' => $order->uuid,
                    'abapay_deeplink' => $result['data']['abapay_deeplink'] ?? null,
                    'qr_string' => $result['data']['qr_string'] ?? null,
                    'checkout_qr_url' => $result['data']['checkout_qr_url'] ?? null,
                ],
            ]);
        }

        $transaction->markAsFailed(
            $result['error'] ?? 'PayWay API failed',
            $result['data'] ?? []
        );

        return response()->json([
            'message' => $result['error'] ?? 'Payment initiation failed.',
        ], 422);
    }

    /**
     * PayWay callback webhook (POST from PayWay server).
     * This route must be PUBLIC (no auth).
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X_PAYWAY_HMAC_SHA512', '');

        Log::info('PayWay: Callback received', [
            'payload' => $payload,
            'signature' => substr($signature, 0, 20) . '...',
        ]);

        $tranId = $payload['tran_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$tranId) {
            return response()->json(['error' => 'Missing tran_id'], 400);
        }

        // Try to find Order transaction first
        $transaction = Transaction::where('gateway_transaction_id', $tranId)->first();
        $bookingTransaction = null;
        $topup = null;
        $order = null;
        $booking = null;

        if ($transaction) {
            $order = $transaction->order;
        } else {
            // Try to find Booking transaction
            $bookingTransaction = BookingTransaction::where('gateway_transaction_id', $tranId)->first();
            if ($bookingTransaction) {
                $booking = $bookingTransaction->booking;
            } else {
                // Try to find TopUp
                $topup = TopUp::where('gateway_reference', $tranId)->first();
            }
        }

        if (!$transaction && !$bookingTransaction && !$topup) {
            Log::warning('PayWay: Transaction not found', ['tran_id' => $tranId]);
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Use outlet's credentials for signature verification if available
        $service = $this->payWayService;
        if ($order && $order->outlet && $order->outlet->hasPayWay()) {
            $service = app(PayWayService::class)->forOutlet($order->outlet);
        }

        // Verify signature
        if ($signature && !$service->verifyCallback($payload, $signature)) {
            Log::warning('PayWay: Invalid callback signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Status 0 or "0" = success
        if ($status === 0 || $status === '0') {
            // Handle Order payment
            if ($transaction && $order) {
                $transaction->markAsCompleted($tranId);
                $transaction->update(['gateway_response' => $payload]);

                $order->update([
                    'payment_status' => PaymentStatusEnum::Paid,
                    'status' => 'confirmed',
                ]);

                Log::info('PayWay: Order payment successful', ['tran_id' => $tranId, 'order' => $order->order_number]);

                // Broadcast payment success to Flutter app
                if ($transaction->customer_id) {
                    event(new PaymentCompleted(
                        customerId: $transaction->customer_id,
                        tranId: $tranId,
                        orderUuid: $order->uuid,
                        orderNumber: $order->order_number,
                        amount: (float) $transaction->amount,
                        status: 'paid',
                    ));
                }
            }

            // Handle Booking payment
            if ($bookingTransaction && $booking) {
                $bookingTransaction->markAsCompleted($tranId);
                $bookingTransaction->update(['gateway_response' => $payload]);

                $booking->update([
                    'payment_status' => BookingPaymentStatus::Paid,
                    'status' => BookingStatus::Confirmed,
                    'confirmed_at' => now(),
                    'payment_reference' => $tranId,
                ]);

                Log::info('PayWay: Booking payment successful', ['tran_id' => $tranId, 'booking' => $booking->code]);
            }

            // Handle TopUp payment
            if ($topup) {
                $topup->update(['metadata' => array_merge($topup->metadata ?? [], [
                    'payway_callback' => $payload,
                ])]);

                $walletTransaction = $topup->complete();

                if ($walletTransaction) {
                    Log::info('PayWay: TopUp payment successful', [
                        'tran_id' => $tranId,
                        'topup_reference' => $topup->reference,
                        'amount' => $topup->amount,
                        'wallet_id' => $topup->wallet_id,
                    ]);
                } else {
                    Log::warning('PayWay: TopUp could not be completed', [
                        'tran_id' => $tranId,
                        'topup_reference' => $topup->reference,
                        'status' => $topup->status,
                    ]);
                }
            }
        } else {
            // Handle Order payment failure
            if ($transaction) {
                $transaction->markAsFailed("PayWay status: {$status}", $payload);
                if ($order) {
                    $order->update(['payment_status' => PaymentStatusEnum::Failed]);
                }
            }

            // Handle Booking payment failure
            if ($bookingTransaction) {
                $bookingTransaction->markAsFailed("PayWay status: {$status}", $payload);
                if ($booking) {
                    $booking->update(['payment_status' => BookingPaymentStatus::Failed]);
                }
            }

            // Handle TopUp payment failure
            if ($topup) {
                $topup->update(['metadata' => array_merge($topup->metadata ?? [], [
                    'payway_callback' => $payload,
                ])]);
                $topup->markAsFailed("PayWay status: {$status}");

                Log::info('PayWay: TopUp payment failed', [
                    'tran_id' => $tranId,
                    'topup_reference' => $topup->reference,
                    'status' => $status,
                ]);
            }

            Log::info('PayWay: Payment failed', ['tran_id' => $tranId, 'status' => $status]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Check payment status (called by Flutter to poll).
     */
    public function checkStatus(Request $request, string $tranId): JsonResponse
    {
        $customer = $request->user();

        $transaction = Transaction::where('gateway_transaction_id', $tranId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        // Use outlet's credentials if available
        $service = $this->payWayService;
        $order = $transaction->order;
        if ($order && $order->outlet && $order->outlet->hasPayWay()) {
            $service = app(PayWayService::class)->forOutlet($order->outlet);
        }

        // Also check with PayWay API for latest status
        $paywayResult = $service->checkTransaction($tranId);

        $paymentStatus = 'pending';
        // PayWay response structure: { data: { data: { payment_status_code: 0 }, status: {...} } }
        $paywayData = $paywayResult['data']['data'] ?? $paywayResult['data'] ?? [];
        if ($paywayResult['success'] && isset($paywayData['payment_status_code'])) {
            $statusCode = $paywayData['payment_status_code'];

            if ($statusCode === 0) {
                $paymentStatus = 'paid';

                // Update local records if not already updated
                if ($transaction->isPending() || $transaction->status->value === 'processing') {
                    $transaction->markAsCompleted($tranId);
                    $transaction->update(['gateway_response' => $paywayData]);

                    $order = $transaction->order;
                    if ($order && $order->payment_status !== PaymentStatusEnum::Paid) {
                        $order->update([
                            'payment_status' => PaymentStatusEnum::Paid,
                            'status' => 'confirmed',
                        ]);
                    }
                }
            } elseif ($statusCode === 3) {
                $paymentStatus = 'declined';
            } elseif ($statusCode === 7) {
                $paymentStatus = 'cancelled';
            } elseif ($statusCode === 2) {
                $paymentStatus = 'pending';
            }
        }

        return response()->json([
            'data' => [
                'tran_id' => $tranId,
                'payment_status' => $paymentStatus,
                'order_uuid' => $transaction->order?->uuid,
                'order_status' => $transaction->order?->status->value ?? $transaction->order?->status,
            ],
        ]);
    }

    /**
     * Check top-up payment status (called by Flutter to poll).
     */
    public function checkTopUpStatus(Request $request, string $tranId): JsonResponse
    {
        $customer = $request->user();

        $topup = TopUp::where('gateway_reference', $tranId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$topup) {
            return response()->json(['message' => 'Top-up not found.'], 404);
        }

        // Check with PayWay API for latest status
        $paywayResult = $this->payWayService->checkTransaction($tranId);

        $paymentStatus = 'pending';
        $paywayData = $paywayResult['data']['data'] ?? $paywayResult['data'] ?? [];

        if ($paywayResult['success'] && isset($paywayData['payment_status_code'])) {
            $statusCode = $paywayData['payment_status_code'];

            if ($statusCode === 0) {
                $paymentStatus = 'paid';

                // Update local records if not already completed
                if ($topup->status->value === 'pending' || $topup->status->value === 'processing') {
                    $topup->update(['metadata' => array_merge($topup->metadata ?? [], [
                        'payway_check' => $paywayData,
                    ])]);

                    $walletTransaction = $topup->complete();

                    if ($walletTransaction) {
                        Log::info('PayWay: TopUp completed via status check', [
                            'tran_id' => $tranId,
                            'topup_reference' => $topup->reference,
                            'amount' => $topup->amount,
                        ]);
                    }
                }
            } elseif ($statusCode === 3) {
                $paymentStatus = 'declined';
            } elseif ($statusCode === 7) {
                $paymentStatus = 'cancelled';
            } elseif ($statusCode === 2) {
                $paymentStatus = 'pending';
            }
        }

        // Refresh topup to get latest status
        $topup->refresh();

        return response()->json([
            'data' => [
                'tran_id' => $tranId,
                'payment_status' => $paymentStatus,
                'topup_reference' => $topup->reference,
                'topup_status' => $topup->status->value,
                'amount' => (float) $topup->amount,
                'wallet_balance' => $topup->wallet ? (float) $topup->wallet->balance : null,
            ],
        ]);
    }
}
