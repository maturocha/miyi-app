<?php

namespace App\Services;

use App\Delivery;
use App\Order;
use App\DeliveryOrder;
use App\OrderStatus;
use App\DeliveryStatus;
use App\DeliveryOrderStatus;
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

            // Nuevo payload con prioridad explícita
            if (!empty($data['orders'])) {
                $this->attachOrders($delivery, $data['orders']);
            } elseif (!empty($data['order_ids'])) {
                // Compat: payload anterior sólo con IDs
                $this->attachOrders($delivery, $data['order_ids']);
            }

            return $delivery;
        });
    }

    /**
     * Attach multiple orders to a delivery and mark them as assigned.
     *
     * @param Delivery $delivery
     * @param array $orders Array de IDs o de arrays ['id' => int, 'sequence' => int]
     * @return void
     */
    protected function attachOrders(Delivery $delivery, array $orders): void
    {
        $sequence = $delivery->orders()->max('sequence') ?? 0;
        $syncData = [];
        $orderIds = [];

        foreach ($orders as $item) {
            if (is_array($item)) {
                $orderId = $item['id'];
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
                $orderId = $item;
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
                ->select('orders.*')
                ->get();

            $added = 0;
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
                    $added++;
                }
            }

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
            // Check if order is already assigned to another active delivery on the same day
            if (!$override) {
                $conflictingDelivery = Delivery::where('delivery_date', $delivery->delivery_date)
                    ->where('id', '!=', $delivery->id)
                    ->whereIn('status', [DeliveryStatus::NOT_STARTED, DeliveryStatus::IN_PROGRESS])
                    ->whereHas('orders', function ($query) use ($order) {
                        $query->where('orders.id', $order->id);
                    })
                    ->first();

                if ($conflictingDelivery) {
                    throw new \Exception('El pedido ya está asignado a otro reparto activo del mismo día.');
                }
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
}
