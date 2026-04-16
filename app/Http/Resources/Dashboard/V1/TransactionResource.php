<?php

namespace Modules\Payment\Http\Resources\Dashboard\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'transaction_number' => $this->transaction_number,
            'type' => $this->type,
            'payment_method' => $this->payment_method,
            'amount' => (float) $this->amount,
            'fee' => (float) ($this->fee ?? 0),
            'net_amount' => (float) ($this->net_amount ?? $this->amount),
            'currency' => $this->currency ?? 'USD',
            'status' => $this->status->value ?? $this->status,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'gateway_response' => $this->gateway_response,
            'notes' => $this->notes,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),

            // Order info
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'uuid' => $this->order->uuid,
                'order_number' => $this->order->order_number,
                'total_amount' => (float) $this->order->total_amount,
                'status' => $this->order->status->value ?? $this->order->status,
                'payment_status' => $this->order->payment_status->value ?? $this->order->payment_status,
            ]),

            // Customer info
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),

            // Outlet info (via order)
            'outlet' => $this->when(
                $this->relationLoaded('order') && $this->order?->relationLoaded('outlet'),
                fn () => $this->order->outlet ? [
                    'id' => $this->order->outlet->id,
                    'name' => $this->order->outlet->name,
                ] : null
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
