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
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'type_product' => $this->product->type_product,
            'promotion' => $this->promotion_snapshot ? array_merge(
                $this->promotion_snapshot,
                [
                    'snapshot' => !$this->promotion, // true si la promoción fue borrada
                    'active' => (bool) $this->promotion // false si fue borrada
                ]
            ) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
