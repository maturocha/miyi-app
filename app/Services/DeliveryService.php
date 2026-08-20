<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\DeliveryOrder;
use App\Models\Enums\OrderStatus;
use App\Models\Enums\DeliveryStatus;
use App\Models\Enums\DeliveryOrderStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeliveryService
{
    /**
     * Create a new delivery, optionally attaching orders.
     *
     * @param array $data
     * @return Delivery
     */
    public function createDelivery(array $data): Delivery
    {
        return DB::transaction(function () use ($data) {
            $delivery = Delivery::create([
                'delivery_date' => $data['delivery_date'],
                'status' => DeliveryStatus::NOT_STARTED,
                'owner_user_id' => $data['owner_user_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            $attachedIds = [];

            // Nuevo payload con prioridad explícita
            if (!empty($data['orders'])) {
                $attachedIds = $this->attachOrders($delivery, $data['orders']);
            } elseif (!empty($data['order_ids'])) {
                // Compat: payload anterior sólo con IDs
                $attachedIds = $this->attachOrders($delivery, $data['order_ids']);
            }

            $this->reorderDeliveryOrdersByZone($delivery, $attachedIds);

            return $delivery;
        });
    }

    /**
     * Attach multiple orders to a delivery and mark them as assigned.
     *
     * @param Delivery $delivery
     * @param array $orders Array de IDs o de arrays ['id' => int, 'sequence' => int]
     * @return array IDs de pedidos incluidos en el attach
     */
    protected function attachOrders(Delivery $delivery, array $orders): array
    {
        $sequence = $delivery->orders()->max('sequence') ?? 0;
        $syncData = [];
        $orderIds = [];

        foreach ($orders as $item) {
            if (is_array($item)) {
                $orderId = (int) $item['id'];
                $this->assertOrderNotAssignedElsewhere($delivery, $orderId);
                $orderIds[] = $orderId;
                if (isset($item['sequence']) && (int) $item['sequence'] > 0) {
                    $seq = (int) $item['sequence'];
                    // Mantener coherencia con la secuencia máxima actual
                    if ($seq > $sequence) {
                        $sequence = $seq;
                    }
                } else {
                    $seq = ++$sequence;
                }
            } else {
                $orderId = (int) $item;
                $this->assertOrderNotAssignedElsewhere($delivery, $orderId);
                $orderIds[] = $orderId;
                $seq = ++$sequence;
            }

            $syncData[$orderId] = [
                'sequence' => $seq,
            ];
        }

        // Bulk attach (sync without detaching)
        $delivery->orders()->syncWithoutDetaching($syncData);

        Order::whereIn('id', $orderIds)
            ->where('status', OrderStatus::READY_TO_SHIP)
            ->update(['status' => OrderStatus::ASSIGNED_TO_DELIVERY]);

        return $orderIds;
    }

    /**
     * Reordena las filas de `delivery_orders` agrupando por zona y renumerando
     * `sequence` de forma consecutiva (1..N) sin huecos ni duplicados.
     *
     * Criterio de zona (el mismo usado al crear un reparto): zonas ordenadas por
     * `zones.name` ascendente, pedidos sin zona al final y, dentro de cada zona,
     * por `orders.id` ascendente.
     *
     * - Los pedidos que ya estaban en el reparto conservan siempre su orden
     *   relativo actual (incluye repartos ordenados manualmente).
     * - Los pedidos indicados en $newOrderIds se insertan al final del grupo de
     *   su zona. Si esa zona todavía no existe en el reparto, el grupo nuevo se
     *   inserta en su posición canónica cuando el reparto sigue el orden por
     *   nombre de zona; si el reparto fue reordenado manualmente, se agrega al
     *   final para no alterar el recorrido definido.
     *
     * @param Delivery $delivery
     * @param array $newOrderIds IDs de pedidos recién agregados (vacío = sólo renumerar)
     * @return void
     */
    protected function reorderDeliveryOrdersByZone(Delivery $delivery, array $newOrderIds = []): void
    {
        $rows = DB::table('delivery_orders')
            ->join('orders', 'delivery_orders.order_id', '=', 'orders.id')
            ->leftJoin('customers', 'orders.id_customer', '=', 'customers.id')
            ->leftJoin('neighborhoods', 'customers.id_neighborhood', '=', 'neighborhoods.id')
            ->leftJoin('zones', 'neighborhoods.id_zone', '=', 'zones.id')
            ->where('delivery_orders.delivery_id', $delivery->id)
            ->orderByRaw('zones.name IS NULL, zones.name')
            ->orderBy('orders.id')
            ->select([
                'delivery_orders.id',
                'delivery_orders.order_id',
                'delivery_orders.sequence',
                'zones.id as zone_id',
                'zones.name as zone_name',
            ])
            ->get()
            ->all();

        if (empty($rows)) {
            return;
        }

        $newIds = [];
        foreach ($newOrderIds as $newOrderId) {
            $newIds[(int) $newOrderId] = true;
        }

        // $rows ya viene en el criterio de creación (zona por nombre, luego id de pedido).
        $existing = [];
        $added = [];
        foreach ($rows as $row) {
            if (isset($newIds[(int) $row->order_id])) {
                $added[] = $row;
            } else {
                $existing[] = $row;
            }
        }

        // Los pedidos ya presentes conservan su orden actual (sequence, y pivot id como desempate).
        usort($existing, function ($a, $b) {
            $cmp = ((int) $a->sequence) <=> ((int) $b->sequence);

            return $cmp !== 0 ? $cmp : (((int) $a->id) <=> ((int) $b->id));
        });

        $ordered = $existing;

        // Agrupar los nuevos por zona respetando el criterio de creación dentro de cada grupo.
        $newByZone = [];
        foreach ($added as $row) {
            $newByZone[$this->zoneGroupKey($row)][] = $row;
        }

        $existingFollowsZoneOrder = $this->followsZoneOrder($existing);

        foreach ($newByZone as $zoneKey => $group) {
            $lastIndex = -1;
            foreach ($ordered as $index => $row) {
                if ($this->zoneGroupKey($row) === $zoneKey) {
                    $lastIndex = $index;
                }
            }

            if ($lastIndex >= 0) {
                // La zona ya existe: insertar al final de ese grupo.
                array_splice($ordered, $lastIndex + 1, 0, $group);
                continue;
            }

            $insertAt = count($ordered);
            if ($existingFollowsZoneOrder) {
                // Reparto en orden canónico: ubicar la zona nueva donde le corresponde.
                foreach ($ordered as $index => $row) {
                    if ($this->compareZones($group[0], $row) < 0) {
                        $insertAt = $index;
                        break;
                    }
                }
            }

            array_splice($ordered, $insertAt, 0, $group);
        }

        $seq = 0;
        foreach ($ordered as $row) {
            $seq++;
            if ((int) $row->sequence === $seq) {
                continue; // Ya está en la posición correcta, evitamos escrituras innecesarias.
            }
            DB::table('delivery_orders')->where('id', $row->id)->update(['sequence' => $seq]);
        }
    }

    /**
     * Clave de agrupación por zona de una fila de delivery_orders (null = sin zona).
     *
     * @param object $row
     * @return string
     */
    protected function zoneGroupKey($row): string
    {
        return $row->zone_id === null ? 'none' : 'z' . (int) $row->zone_id;
    }

    /**
     * Compara dos filas según el criterio de zona: nombre ascendente, sin zona al final.
     *
     * @param object $a
     * @param object $b
     * @return int
     */
    protected function compareZones($a, $b): int
    {
        $aNull = $a->zone_name === null;
        $bNull = $b->zone_name === null;

        if ($aNull || $bNull) {
            return ($aNull ? 1 : 0) <=> ($bNull ? 1 : 0);
        }

        return strcmp((string) $a->zone_name, (string) $b->zone_name);
    }

    /**
     * Indica si las filas dadas (en su orden actual) respetan el criterio de zona
     * usado al crear un reparto: zonas agrupadas y ordenadas por nombre, sin zona al final.
     *
     * @param array $rows
     * @return bool
     */
    protected function followsZoneOrder(array $rows): bool
    {
        $seenKeys = [];
        $previous = null;

        foreach ($rows as $row) {
            $key = $this->zoneGroupKey($row);
            if ($previous !== null && $key === $this->zoneGroupKey($previous)) {
                continue;
            }

            if (isset($seenKeys[$key])) {
                return false; // La zona se repite en bloques separados: orden manual.
            }

            if ($previous !== null && $this->compareZones($previous, $row) > 0) {
                return false;
            }

            $seenKeys[$key] = true;
            $previous = $row;
        }

        return true;
    }

    /**
     * Sync delivery orders (pivot) with given payload and keep order statuses consistent.
     *
     * @param Delivery $delivery
     * @param array $orders Array de IDs o de arrays ['id' => int, 'sequence' => int]
     * @return void
     */
    public function syncOrders(Delivery $delivery, array $orders): void
    {
        DB::transaction(function () use ($delivery, $orders) {
            $currentOrderIds = $delivery->orders()->pluck('orders.id')->all();

            $normalized = [];
            $sequence = 0;

            foreach ($orders as $item) {
                if (is_array($item)) {
                    $orderId = isset($item['id']) ? (int) $item['id'] : 0;
                    if ($orderId <= 0) {
                        continue;
                    }

                    if (isset($item['sequence']) && (int) $item['sequence'] > 0) {
                        $seq = (int) $item['sequence'];
                        if ($seq > $sequence) {
                            $sequence = $seq;
                        }
                    } else {
                        $seq = ++$sequence;
                    }
                } else {
                    $orderId = (int) $item;
                    if ($orderId <= 0) {
                        continue;
                    }
                    $seq = ++$sequence;
                }

                $normalized[$orderId] = [
                    'sequence' => $seq,
                ];
            }

            $newOrderIds = array_keys($normalized);

            // Detach orders that are no longer present and rollback their status
            $ordersToDetach = array_diff($currentOrderIds, $newOrderIds);
            if (!empty($ordersToDetach)) {
                $delivery->orders()->detach($ordersToDetach);

                Order::whereIn('id', $ordersToDetach)
                    ->where('status', OrderStatus::ASSIGNED_TO_DELIVERY)
                    ->update(['status' => OrderStatus::READY_TO_SHIP]);
            }

            // Attach / update remaining orders with their sequence
            if (!empty($normalized)) {
                $delivery->orders()->syncWithoutDetaching($normalized);

                // Ensure orders are marked as assigned to delivery
                $allOrderIds = array_keys($normalized);
                Order::whereIn('id', $allOrderIds)
                    ->where('status', OrderStatus::READY_TO_SHIP)
                    ->update(['status' => OrderStatus::ASSIGNED_TO_DELIVERY]);
            }

            // Los pedidos recién agregados se ubican dentro del grupo de su zona;
            // el resto conserva el orden recibido y se renumera sin huecos ni duplicados.
            $addedOrderIds = array_values(array_diff($newOrderIds, $currentOrderIds));
            $this->reorderDeliveryOrdersByZone($delivery, $addedOrderIds);
        });
    }

    /**
     * Add pending orders from the given zone and date to the delivery (idempotent).
     *
     * @param Delivery $delivery
     * @param int $zoneId
     * @param string $date Y-m-d
     * @return int Number of orders added
     */
    public function addPendingOrders(Delivery $delivery, int $zoneId, string $date): int
    {
        return DB::transaction(function () use ($delivery, $zoneId, $date) {
            // Get pending orders from the zone and date
            $pendingOrders = Order::join('customers', 'orders.id_customer', '=', 'customers.id')
                ->join('neighborhoods', 'customers.id_neighborhood', '=', 'neighborhoods.id')
                ->join('zones', 'neighborhoods.id_zone', '=', 'zones.id')
                ->where('zones.id', $zoneId)
                ->where('orders.date', $date)
                ->where('orders.status', OrderStatus::READY_TO_SHIP)
                ->orderBy('zones.name')
                ->orderBy('orders.id')
                ->select('orders.*')
                ->get();

            $added = 0;
            $addedIds = [];
            $sequence = $delivery->orders()->max('sequence') ?? 0;

            foreach ($pendingOrders as $order) {
                // Check if order is already in the delivery (idempotent)
                $exists = $delivery->orders()->where('orders.id', $order->id)->exists();
                
                if (!$exists) {
                    $delivery->orders()->attach($order->id, [
                        'sequence' => ++$sequence,
                    ]);

                    // Update order status a ASIGNADO A REPARTO
                    $order->update(['status' => OrderStatus::ASSIGNED_TO_DELIVERY]);
                    $addedIds[] = (int) $order->id;
                    $added++;
                }
            }

            $this->reorderDeliveryOrdersByZone($delivery, $addedIds);

            return $added;
        });
    }

    /**
     * Add a single order to the delivery.
     *
     * @param Delivery $delivery
     * @param Order $order
     * @param bool $override
     * @return void
     * @throws \Exception
     */
    public function addOrder(Delivery $delivery, Order $order, bool $override = false): void
    {
        DB::transaction(function () use ($delivery, $order, $override) {
            if (!$override) {
                $this->assertOrderNotAssignedElsewhere($delivery, (int) $order->id);
            }

            // Check if order is already in this delivery
            $exists = $delivery->orders()->where('orders.id', $order->id)->exists();
            if ($exists) {
                return; // Already added, idempotent
            }

            // Add order to delivery
            $sequence = $delivery->orders()->max('sequence') ?? 0;
            $delivery->orders()->attach($order->id, [
                'sequence' => $sequence + 1,
            ]);

            // Update order status a ASIGNADO A REPARTO si estaba listo para salir
            if ($order->status === OrderStatus::READY_TO_SHIP) {
                $order->update(['status' => OrderStatus::ASSIGNED_TO_DELIVERY]);
            }

            $this->reorderDeliveryOrdersByZone($delivery, [(int) $order->id]);
        });
    }

    /**
     * Start the delivery.
     * The DeliveryObserver handles updating order statuses to OUT_FOR_DELIVERY.
     *
     * @param Delivery $delivery
     * @return void
     */
    public function startDelivery(Delivery $delivery): void
    {
        $delivery->update([
            'status' => DeliveryStatus::IN_PROGRESS,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Finish the delivery.
     *
     * @param Delivery $delivery
     * @return void
     */
    public function finishDelivery(Delivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery->update([
                'status' => DeliveryStatus::FINISHED,
                'finished_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Close the delivery.
     *
     * @param Delivery $delivery
     * @return void
     */
    public function closeDelivery(Delivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            if ($delivery->status !== DeliveryStatus::FINISHED) {
                throw new \Exception('Solo se pueden cerrar repartos que están finalizados.');
            }
            $delivery->update([
                'status' => DeliveryStatus::CLOSED,
            ]);
        });
    }

    /**
     * Update delivery order (pivot) and order status.
     *
     * @param Delivery $delivery
     * @param Order $order
     * @param array $data
     * @return void
     */
    public function updateDeliveryOrder(Delivery $delivery, Order $order, array $data): void
    {
        DB::transaction(function () use ($delivery, $order, $data) {
            $payments = $data['payments'] ?? [];

            $totalFromPayments = 0;
            $uniqueMethods = [];

            if (is_array($payments)) {
                foreach ($payments as $payment) {
                    $amount = isset($payment['amount']) ? (float) $payment['amount'] : 0;
                    if ($amount <= 0) {
                        continue;
                    }
                    $totalFromPayments += $amount;
                    if (!empty($payment['payment_method'])) {
                        $uniqueMethods[] = $payment['payment_method'];
                    }
                }
            }

            $uniqueMethods = array_values(array_unique($uniqueMethods));

            $pivotData = [
                'delivery_status' => $data['delivery_status'],
                'collected_amount' => $totalFromPayments > 0
                    ? $totalFromPayments
                    : ($data['collected_amount'] ?? 0),
                'payment_method' => count($uniqueMethods) === 1 ? $uniqueMethods[0] : ($data['payment_method'] ?? null),
                'payment_reference' => $data['payment_reference'] ?? null,
                'observations' => $data['observations'] ?? null,
                'failure_reason' => $data['failure_reason'] ?? null,
            ];

            // Set delivered_at if status is DELIVERED
            if ($data['delivery_status'] === DeliveryOrderStatus::DELIVERED) {
                $pivotData['delivered_at'] = Carbon::now();
            }

            // Update pivot - use the correct pivot key
            $delivery->orders()->updateExistingPivot($order->id, $pivotData, false);

            // Sync payments table if provided
            if (is_array($payments)) {
                $deliveryOrder = DeliveryOrder::where('delivery_id', $delivery->id)
                    ->where('order_id', $order->id)
                    ->first();

                if ($deliveryOrder) {
                    $deliveryOrder->payments()->delete();

                    foreach ($payments as $payment) {
                        $amount = isset($payment['amount']) ? (float) $payment['amount'] : 0;
                        if ($amount <= 0 || empty($payment['payment_method'])) {
                            continue;
                        }

                        $deliveryOrder->payments()->create([
                            'payment_method' => $payment['payment_method'],
                            'amount' => $amount,
                            'payment_reference' => $payment['payment_reference'] ?? null,
                        ]);
                    }
                }
            }

            // Update order status based on delivery_status
            if ($data['delivery_status'] === DeliveryOrderStatus::DELIVERED) {
                $order->update(['status' => OrderStatus::DELIVERED]);
            } elseif ($data['delivery_status'] === DeliveryOrderStatus::FAILED) {
                $order->update(['status' => OrderStatus::FAILED]);
            }
        });
    }

    /**
     * Update delivery expenses.
     *
     * @param Delivery $delivery
     * @param float $amount
     * @param string|null $notes
     * @return void
     */
    public function updateExpenses(Delivery $delivery, float $amount, ?string $notes = null): void
    {
        $delivery->update([
            'expenses_amount' => $amount,
            'expenses_notes' => $notes,
        ]);
    }

    /**
     * Remove order from delivery and rollback status if needed.
     *
     * @param Delivery $delivery
     * @param Order $order
     * @return void
     */
    public function removeOrder(Delivery $delivery, Order $order): void
    {
        DB::transaction(function () use ($delivery, $order) {
            // Remove from delivery
            $delivery->orders()->detach($order->id);

            // Rollback order status si estaba asignado al reparto
            if ($order->status === OrderStatus::ASSIGNED_TO_DELIVERY) {
                $order->update(['status' => OrderStatus::READY_TO_SHIP]);
            }
        });
    }

    /**
     * Block assignment when the order is already in another delivery with a non-failed pivot.
     *
     * @param Delivery $delivery
     * @param int $orderId
     * @return void
     *
     * @throws \Exception
     */
    protected function assertOrderNotAssignedElsewhere(Delivery $delivery, int $orderId): void
    {
        $hasConflict = DeliveryOrder::where('order_id', $orderId)
            ->where('delivery_id', '!=', $delivery->id)
            ->where('delivery_status', '!=', DeliveryOrderStatus::FAILED)
            ->exists();

        if ($hasConflict) {
            throw new \Exception('El pedido ya está asignado a otro reparto sin estado fallido.');
        }
    }
}
