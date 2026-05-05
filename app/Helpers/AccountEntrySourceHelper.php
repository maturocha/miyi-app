<?php

namespace App\Helpers;

use App\Delivery;
use App\DeliveryOrder;

class AccountEntrySourceHelper
{
    /**
     * Resolve source_type + source_id to display label and frontend link.
     * Returns ['label' => string, 'link' => string|null].
     */
    public static function resolve(?string $sourceType, $sourceId): array
    {
        if (!$sourceType || $sourceId === null) {
            return ['label' => 'Manual', 'link' => null];
        }

        if ($sourceType === 'manual') {
            return ['label' => 'Manual', 'link' => null];
        }

        if ($sourceType === 'orders') {
            return [
                'label' => 'Pedido #' . (int) $sourceId,
                'link' => '/pedidos/' . (int) $sourceId,
            ];
        }

        if ($sourceType === 'delivery') {
            $delivery = Delivery::find($sourceId);
            if (!$delivery || !$delivery->delivery_date) {
                return ['label' => 'Reparto #' . (int) $sourceId, 'link' => '/repartos/' . (int) $sourceId];
            }
            return [
                'label' => 'Reparto del día ' . $delivery->delivery_date->format('d/m/Y'),
                'link' => '/repartos/' . (int) $sourceId,
            ];
        }

        if ($sourceType === 'delivery_orders') {
            $deliveryOrder = DeliveryOrder::with('delivery')->find($sourceId);
            if (!$deliveryOrder || !$deliveryOrder->delivery) {
                return ['label' => 'Entrega reparto', 'link' => null];
            }
            $delivery = $deliveryOrder->delivery;
            $dateStr = $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : '';
            return [
                'label' => $dateStr ? ('Reparto del día ' . $dateStr) : ('Reparto #' . $delivery->id),
                'link' => '/repartos/' . (int) $delivery->id,
            ];
        }

        return ['label' => $sourceType, 'link' => null];
    }
}
