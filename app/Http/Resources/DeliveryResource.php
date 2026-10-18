<?php

namespace App\Http\Resources;

use App\Models\Enums\PaymentMethod;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Delivery */

class DeliveryResource extends JsonResource
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
            'data' => [
                'id' => $this->id,
                'delivery_date' => $this->delivery_date ? $this->delivery_date->format('Y-m-d') : null,
                'status' => $this->status,
                'owner_user_id' => $this->owner_user_id,
                'owner' => $this->whenLoaded('owner', function () {
                    return $this->owner ? [
                        'id' => $this->owner->id,
                        'name' => $this->owner->name,
                    ] : null;
                }),
                'started_at' => $this->started_at ? $this->started_at->toDateTimeString() : null,
                'finished_at' => $this->finished_at ? $this->finished_at->toDateTimeString() : null,
                'expenses_amount' => (float) ($this->expenses_amount ?? 0),
                'expenses_notes' => $this->expenses_notes,
                'notes' => $this->notes,
                'orders' => DeliveryOrderResource::collection($this->whenLoaded('deliveryOrders')),
                'orders_count' => $this->when($this->relationLoaded('deliveryOrders'), function () {
                    return $this->deliveryOrders->count();
                }),
                'totals' => $this->totals(),
                'account_entries' => AccountEntryResource::collection($this->whenLoaded('accountEntries')),
                'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
                'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            ]
        ];
    }

    /**
     * Totales del reparto (mismo objeto que `data.totals` del show). Si
     * `deliveryOrders.payments` / `accountEntries` ya están cargados se calcula
     * en memoria; si no, con las queries de agregado del modelo.
     * `sales_total` solo cuando `deliveryOrders` está cargado.
     *
     * @return array
     */
    public function totals(): array
    {
        $ordersWithPayments = $this->relationLoaded('deliveryOrders')
            && $this->deliveryOrders->every(function ($row) {
                return $row->relationLoaded('payments');
            });
        $fromOrders = $ordersWithPayments
            ? $this->collectedByPaymentMethodFromLoadedOrders()
            : $this->collectedByPaymentMethodFromOrders();
        $offRoute = $this->relationLoaded('accountEntries')
            ? $this->collectedByPaymentMethodOffRouteFromEntries($this->accountEntries)
            : $this->collectedByPaymentMethodOffRoute();

        $totalCollected = (float) (array_sum($fromOrders) + array_sum($offRoute));
        $cashCollected = (float) (($fromOrders[PaymentMethod::CASH] ?? 0) + ($offRoute[PaymentMethod::CASH] ?? 0));
        $netAmount = $totalCollected - ($this->expenses_amount ?? 0);

        $totals = [
            'collected' => (float) $totalCollected,
            'cash_collected' => (float) $cashCollected,
            'expenses' => (float) ($this->expenses_amount ?? 0),
            'net' => (float) $netAmount,
        ];

        if ($this->relationLoaded('deliveryOrders')) {
            $totals['sales_total'] = (float) $this->deliveryOrders->sum(function ($row) {
                return $row->relationLoaded('order') && $row->order
                    ? (float) ($row->order->total ?? 0)
                    : 0;
            });
        }

        return $totals;
    }
}
