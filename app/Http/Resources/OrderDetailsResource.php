<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'id_order' => $this->id_order,
            'id_product' => $this->id_product,
            'promotion_id' => $this->promotion_id,
            'product_name' => $this->product->name,
            'quantity' => $this->quantity,
            'price_unit' => $this->price_unit,
            'discount' => $this->discount,
            'price_final' => $this->price_final,
            'weight' => $this->weight,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'promotion' => $this->promotion ?
                [
                    'id' => $this->promotion->id,
                    'name' => $this->promotion->name,
                    'type' => $this->promotion->type
                ]
            : null,
        ];
    }
}
