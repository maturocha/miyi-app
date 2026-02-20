<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
        $totalCollected = $this->relationLoaded('deliveryOrders')
            ? $this->deliveryOrders->sum('collected_amount')
            : ($this->orders()->sum('delivery_orders.collected_amount') ?? 0);
        $netAmount = $totalCollected - ($this->expenses_amount ?? 0);

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
                'totals' => [
                    'collected' => (float) $totalCollected,
                    'expenses' => (float) ($this->expenses_amount ?? 0),
                    'net' => (float) $netAmount,
                ],
                'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
                'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            ]
        ];
    }
}
