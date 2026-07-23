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
        $ordersTotal = $this->resource->orders()->count();
        $ordersSpent = (float) $this->resource->orders()->sum('total');

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
            'current_balance' => (float) ($this->current_balance ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'orders' => [
                'data' => $this->resource->orders()
                    ->orderByDesc('date')
                    ->take(5)
                    ->get()
                    ->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'date' => $order->date,
                            'total' => $order->total,
                        ];
                    }),
                'total' => $ordersTotal,
            ],
            'order_stats' => [
                'total_orders' => $ordersTotal,
                'total_spent' => $ordersSpent,
            ],
            'stats' => [
                'products_ranking' => $this->when($this->resource->getProductRanking(), function () {
                    return $this->resource->getProductRanking();
                }),
            ],
            'account_entries' => [
                'data' => $this->resource->accountEntries()
                    ->with(['paymentMethods', 'createdByUser'])
                    ->orderByDesc('occurred_at')
                    ->take(5)
                    ->get()
                    ->map(function ($entry) {
                        return new AccountEntryResource($entry);
                    }),
                'total' => $this->resource->accountEntries()->count(),
            ],
        ];
    }
}
