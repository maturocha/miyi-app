<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'cuit' => $this->cuit,
            'fullname' => $this->fullname,
            'name' => $this->name,
            'email' => $this->email,
            'address' => $this->address,
            'time_visit' => $this->time_visit,
            //'neighborhood' => $this->neighborhood?->name,
            'id_neighborhood' => $this->id_neighborhood,
            'cellphone' => $this->cellphone,
            'telephone' => $this->telephone,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'orders' => $this->resource->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'date' => $order->date,
                    'total' => $order->total,
                ];
            }),
            'stats' => [
                'products_ranking' => $this->when($this->resource->getProductRanking(), function () {
                    return $this->resource->getProductRanking();
                }),
            ],
        ];
    }
}
