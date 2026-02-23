<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'delivery_id' => $this->delivery_id,
            'order_id' => $this->order_id,
            'order' => $this->when($this->relationLoaded('order'), function () {
                return $this->order ? [
                    'id' => $this->order->id,
                    'date' => $this->order->date,
                    'total' => $this->order->total,
                    'customer' => $this->order->relationLoaded('customer') && $this->order->customer ? [
                        'id' => $this->order->customer->id,
                        'name' => $this->order->customer->name,
                        'address' => $this->order->customer->address,
                        'cellphone' => $this->order->customer->cellphone,
                    ] : null,
                ] : null;
            }),
            'sequence' => $this->sequence,
            'delivery_status' => $this->delivery_status,
            'collected_amount' => (float) ($this->collected_amount ?? 0),
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'observations' => $this->observations,
            'payments' => $this->when($this->relationLoaded('payments'), function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_method' => $payment->payment_method,
                        'amount' => (float) $payment->amount,
                        'payment_reference' => $payment->payment_reference,
                    ];
                })->values();
            }),
            'delivered_at' => $this->delivered_at ? $this->delivered_at->toDateTimeString() : null,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
