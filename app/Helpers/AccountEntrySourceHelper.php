<?php

namespace App\Helpers;

use App\Models\Delivery;
use App\Models\DeliveryOrder;

class AccountEntrySourceHelper
{
    /** @var array<int, Delivery|null> Memo por request: delivery_id => Delivery. */
    protected static $deliveries = [];

    /** @var array<int, int|null> Memo por request: delivery_order_id => delivery_id. */
    protected static $deliveryOrderToDelivery = [];

    /**
     * Precarga en 2 queries el contexto de reparto de un lote de asientos, para
     * que el AccountEntryResource de cada fila no haga su propio find (N+1).
     *
     * @param iterable $entries AccountEntry
     */
    public static function preload($entries): void
    {
        $deliveryIds = [];
        $deliveryOrderIds = [];
        foreach ($entries as $entry) {
            if ($entry->source_id === null) {
                continue;
            }
            if ($entry->source_type === 'delivery') {
                $deliveryIds[] = (int) $entry->source_id;
            } elseif ($entry->source_type === 'delivery_orders') {
                $deliveryOrderIds[] = (int) $entry->source_id;
            }
        }

        $deliveryOrderIds = array_values(array_diff(
            array_unique($deliveryOrderIds),
            array_keys(self::$deliveryOrderToDelivery)
        ));
        if (!empty($deliveryOrderIds)) {
            $map = DeliveryOrder::whereIn('id', $deliveryOrderIds)->pluck('delivery_id', 'id')->all();
            foreach ($deliveryOrderIds as $doId) {
                self::$deliveryOrderToDelivery[$doId] = isset($map[$doId]) ? (int) $map[$doId] : null;
                if (isset($map[$doId])) {
                    $deliveryIds[] = (int) $map[$doId];
                }
            }
        }

        $deliveryIds = array_values(array_diff(array_unique($deliveryIds), array_keys(self::$deliveries)));
        if (!empty($deliveryIds)) {
            $found = Delivery::whereIn('id', $deliveryIds)->get()->keyBy('id');
            foreach ($deliveryIds as $id) {
                self::$deliveries[$id] = $found->get($id);
            }
        }
    }

    protected static function findDelivery(int $id): ?Delivery
    {
        if (!array_key_exists($id, self::$deliveries)) {
            self::$deliveries[$id] = Delivery::find($id);
        }
        return self::$deliveries[$id];
    }

    protected static function formatContext(?Delivery $delivery): array
    {
        if (!$delivery) {
            return ['delivery_id' => null, 'delivery_date' => null];
        }
        return [
            'delivery_id' => (int) $delivery->id,
            'delivery_date' => $delivery->delivery_date
                ? $delivery->delivery_date->format('Y-m-d')
                : null,
        ];
    }

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
            return self::formatContext(self::findDelivery((int) $sourceId));
        }

        if ($sourceType === 'delivery_orders') {
            $doId = (int) $sourceId;
            if (!array_key_exists($doId, self::$deliveryOrderToDelivery)) {
                $deliveryId = DeliveryOrder::whereKey($doId)->value('delivery_id');
                self::$deliveryOrderToDelivery[$doId] = $deliveryId !== null ? (int) $deliveryId : null;
            }
            $deliveryId = self::$deliveryOrderToDelivery[$doId];
            return self::formatContext($deliveryId !== null ? self::findDelivery($deliveryId) : null);
        }

        return $empty;
    }
}
