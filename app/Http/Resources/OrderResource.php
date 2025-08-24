<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderDetailsResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
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
                'user' => $this->whenLoaded('user', function () {
                    return $this->user->name;
                }),
                'id_customer' => $this->id_customer,
                'customer' => $this->whenLoaded('customer', function () {
                    return $this->customer->name;
                }),
                'details' => OrderDetailsResource::collection($this->whenLoaded('details')),
            ]
        ];
    }
}
