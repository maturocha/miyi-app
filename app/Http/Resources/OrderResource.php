<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderDetailsResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        // Si viene de un join, puede ser un objeto stdClass
        $customerName = null;
        if (isset($this->customer) && is_string($this->customer)) {
            $customerName = $this->customer;
        } elseif ($this->relationLoaded('customer') && $this->customer) {
            $customerName = $this->customer->name;
        }
        
        return [
            'data' => [
                'id' => $this->id,
                'date' => $this->date,
                'total' => $this->total,
                'discount' => $this->discount,
                'notes' => $this->notes,
                'total_bruto' => $this->total_bruto,
                'delivery_cost' => $this->delivery_cost,
                'payment_method' => $this->payment_method,
                // Si por algún motivo no hay status, asumir \"en proceso\"
                'status' => $this->status ?? 'in_process',
                'user' => $this->whenLoaded('user', function () {
                    return $this->user->name;
                }),
                'customer' => $customerName ? [
                    'id' => $this->whenLoaded('customer') ? $this->customer->id : null,
                    'name' => $customerName,
                    'type' => $this->whenLoaded('customer') && $this->customer ? $this->customer->type : null,
                ] : ($this->whenLoaded('customer') && $this->customer ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'type' => $this->customer->type,
                ] : null),
                'details' => OrderDetailsResource::collection($this->whenLoaded('details')),
            ]
        ];
    }
}
