<?php

namespace App\Helpers;

use App\Models\Delivery;
use App\Models\DeliveryOrder;

class AccountEntrySourceHelper
{
    /**
     * Resolve delivery id and date for account entry source (delivery / delivery_orders).
     * Label and link are built in the frontend.
     *
     * @return array{delivery_id: int|null, delivery_date: string|null}
     */
    public static function resolveDeliveryContext(?string $sourceType, $sourceId): array
    {
        $empty = ['delivery_id' => null, 'delivery_date' => null];

        if (!$sourceType || $sourceId === null) {
            return $empty;
        }

        if ($sourceType === 'delivery') {
            $delivery = Delivery::find($sourceId);
            if (!$delivery) {
                return $empty;
            }
            return [
                'delivery_id' => (int) $delivery->id,
                'delivery_date' => $delivery->delivery_date
                    ? $delivery->delivery_date->format('Y-m-d')
                    : null,
            ];
        }

        if ($sourceType === 'delivery_orders') {
            $deliveryOrder = DeliveryOrder::with('delivery')->find($sourceId);
            if (!$deliveryOrder || !$deliveryOrder->delivery) {
                return $empty;
            }
            $delivery = $deliveryOrder->delivery;
            return [
                'delivery_id' => (int) $delivery->id,
                'delivery_date' => $delivery->delivery_date
                    ? $delivery->delivery_date->format('Y-m-d')
                    : null,
            ];
        }

        return $empty;
    }
}
