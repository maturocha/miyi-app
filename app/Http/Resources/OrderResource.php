<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\OrderDetailsResource;
use App\Http\Controllers\Api\V1\DeliveriesController;
use App\Models\Customer;

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
                'customer' => $customerName ? array_merge([
                    'id' => $this->whenLoaded('customer') ? $this->customer->id : null,
                    'name' => $customerName,
                    'type' => $this->whenLoaded('customer') && $this->customer ? $this->customer->type : null,
                ], $this->customerContact()) : ($this->whenLoaded('customer') && $this->customer ? array_merge([
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'type' => $this->customer->type,
                ], $this->customerContact()) : null),
                'details' => OrderDetailsResource::collection($this->whenLoaded('details')),
                // Regla de reparto: entregado en reparto cerrado = no editable ni borrable.
                'is_locked' => $this->resource->isLockedByClosedDelivery(),
                'was_delivered' => $this->resource->wasDelivered(),
                'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
                // Solo si `deliveries` viene cargado (show); en otros usos la clave no aparece.
                'delivery' => $this->when($this->relationLoaded('deliveries'), function () use ($request) {
                    return $this->deliveryPayload($request);
                }),
            ]
        ];
    }

    /**
     * Datos de contacto/ubicación del cliente. Solo usa relaciones ya cargadas
     * (sin lazy loading): lo que no venga cargado sale en null.
     */
    private function customerContact(): array
    {
        $customer = $this->relationLoaded('customer') ? $this->customer : null;
        if (!$customer instanceof Customer) {
            return ['address' => null, 'cellphone' => null, 'neighborhood' => null, 'zone' => null];
        }

        $neighborhood = $customer->relationLoaded('neighborhood') ? $customer->neighborhood : null;
        $zone = $neighborhood && $neighborhood->relationLoaded('zone') ? $neighborhood->zone : null;

        return [
            'address' => $customer->address,
            'cellphone' => $customer->cellphone,
            'neighborhood' => $neighborhood ? $neighborhood->name : null,
            'zone' => $zone ? $zone->name : null,
        ];
    }

    /**
     * Reparto principal del pedido (ver Order::primaryDelivery). Si el usuario no
     * puede ver el detalle de ese reparto (misma regla que GET /deliveries/{id}),
     * solo se exponen id, fecha y estado del reparto.
     */
    private function deliveryPayload($request): ?array
    {
        $delivery = $this->resource->primaryDelivery();
        if (!$delivery) {
            return null;
        }

        $payload = [
            'id' => $delivery->id,
            'delivery_date' => $delivery->delivery_date ? $delivery->delivery_date->format('Y-m-d') : null,
            'status' => $delivery->status,
        ];

        if (!DeliveriesController::canViewDetail($request->user(), $delivery)) {
            return $payload;
        }

        $owner = $delivery->relationLoaded('owner') ? $delivery->owner : null;
        $pivot = $delivery->pivot;

        return array_merge($payload, [
            'owner' => $owner ? ['id' => $owner->id, 'name' => $owner->name] : null,
            'delivery_status' => $pivot ? $pivot->delivery_status : null,
            'delivered_at' => $pivot && $pivot->delivered_at ? $pivot->delivered_at->toIso8601String() : null,
            'failure_reason' => $pivot ? $pivot->failure_reason : null,
        ]);
    }
}
