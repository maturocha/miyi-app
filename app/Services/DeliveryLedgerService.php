<?php

namespace App\Services;

use App\Models\AccountEntry;
use App\Models\Enums\AccountEntryDirection;
use App\Models\AccountEntryPaymentMethod;
use App\Models\Enums\AccountEntryType;
use App\Models\Enums\AccountEntryValidationStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\Enums\DeliveryOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Ledger movements tied to a delivery: charges when the route finishes, payments when it closes.
 * Balance updates when validation_status becomes validated (AccountEntryObserver).
 */
class DeliveryLedgerService
{
    /**
     * Cargo por pedido en reparto: pending hasta que el reparto se cierre.
     */
    public function createChargesForFinishedDelivery(Delivery $delivery): void
    {
        $delivery->load(['deliveryOrders.order']);

        $occurredAt = $delivery->finished_at ?: now();

        DB::transaction(function () use ($delivery, $occurredAt) {
            $processedOrderIds = [];

            foreach ($delivery->deliveryOrders as $deliveryOrder) {
                if ($deliveryOrder->delivery_status !== DeliveryOrderStatus::DELIVERED) {
                    continue;
                }

                $order = $deliveryOrder->order;
                if (!$order || isset($processedOrderIds[$order->id])) {
                    continue;
                }
                $processedOrderIds[$order->id] = true;

                // Lock del pedido: serializa el exists()+create() si el mismo pedido
                // se procesa en dos repartos a la vez (evita cargos duplicados), y
                // se usa ESTA fila (lectura con lock = último valor confirmado): el
                // `$order` precargado puede tener un total previo a una edición.
                $order = Order::whereKey($order->id)->lockForUpdate()->first();
                if (!$order) {
                    continue;
                }

                if (AccountEntry::where('source_type', 'orders')
                    ->where('source_id', $order->id)
                    ->exists()) {
                    continue;
                }

                $customer = Customer::find($order->id_customer);
                $balanceAtEntry = $customer ? (float) $customer->current_balance : null;
                AccountEntry::create([
                    'customer_id' => $order->id_customer,
                    'type' => AccountEntryType::CHARGE,
                    'direction' => AccountEntryDirection::DEBIT,
                    'amount' => $order->total,
                    'occurred_at' => $occurredAt,
                    'notes' => null,
                    'source_type' => 'orders',
                    'source_id' => $order->id,
                    'created_by_user_id' => null,
                    'validation_status' => AccountEntryValidationStatus::PENDING,
                    'balance_at_entry' => $balanceAtEntry,
                ]);
            }
        });
    }

    /**
     * Cobros por delivery_order al cerrar: not_validated hasta el batch que los valida junto al resto.
     */
    public function createPaymentsForClosedDelivery(Delivery $delivery): void
    {
        $delivery->load(['deliveryOrders.order.customer', 'deliveryOrders.payments']);

        $occurredAt = $delivery->finished_at ?: now();

        $existingEntryIds = AccountEntry::where('source_type', 'delivery_orders')
            ->whereIn('source_id', $delivery->deliveryOrders->pluck('id'))
            ->pluck('id', 'source_id')
            ->all();

        DB::transaction(function () use ($delivery, $occurredAt, $existingEntryIds) {
            foreach ($delivery->deliveryOrders as $deliveryOrder) {
                $totalCollected = (float) ($deliveryOrder->collected_amount ?? 0);
                if ($totalCollected <= 0) {
                    continue;
                }
                if (isset($existingEntryIds[$deliveryOrder->id])) {
                    continue;
                }
                $customerId = $deliveryOrder->order->id_customer;
                $customer = Customer::find($customerId);
                $balanceAtEntry = $customer ? (float) $customer->current_balance : null;

                $entry = AccountEntry::create([
                    'customer_id' => $customerId,
                    'type' => AccountEntryType::PAYMENT,
                    'direction' => AccountEntryDirection::CREDIT,
                    'amount' => $totalCollected,
                    'occurred_at' => $occurredAt,
                    'notes' => null,
                    'source_type' => 'delivery_orders',
                    'source_id' => $deliveryOrder->id,
                    'created_by_user_id' => null,
                    'validation_status' => AccountEntryValidationStatus::NOT_VALIDATED,
                    'balance_at_entry' => $balanceAtEntry,
                ]);

                $payments = $deliveryOrder->payments;
                if ($payments && $payments->isNotEmpty()) {
                    foreach ($payments as $p) {
                        AccountEntryPaymentMethod::create([
                            'account_entry_id' => $entry->id,
                            'payment_method' => is_object($p->payment_method) ? (string) $p->payment_method : $p->payment_method,
                            'amount' => $p->amount,
                            'payment_reference' => $p->payment_reference,
                        ]);
                    }
                } else {
                    AccountEntryPaymentMethod::create([
                        'account_entry_id' => $entry->id,
                        'payment_method' => $deliveryOrder->payment_method ?? 'cash',
                        'amount' => $totalCollected,
                        'payment_reference' => $deliveryOrder->payment_reference,
                    ]);
                }
            }
        });
    }

    /**
     * Al cerrar, antes de validar: alinea el monto de los cargos PENDING de los
     * pedidos entregados con su total actual (ediciones hechas después de
     * finalizar el reparto).
     */
    public function syncPendingChargesForClosedDelivery(Delivery $delivery): void
    {
        $orderIds = $delivery->deliveryOrders()
            ->where('delivery_status', DeliveryOrderStatus::DELIVERED)
            ->pluck('order_id')
            ->all();

        // lockForUpdate: lectura bloqueante = total confirmado más reciente (no el
        // snapshot de la transacción) y serializa con ediciones en curso.
        foreach (Order::whereIn('id', $orderIds)->lockForUpdate()->get() as $order) {
            $order->syncPendingCharge();
        }
    }

    /**
     * Al cerrar: descarta los cargos PENDING de pedidos que en este reparto NO
     * quedaron entregados (ej. entregado al finalizar y pasado a fallido en la
     * revisión). Nunca toca cargos validados ni los de pedidos entregados en otro
     * reparto (ese cargo lo gestiona el otro cierre). Si el pedido se entrega más
     * adelante, ese reparto crea un cargo nuevo con total y fecha actuales.
     */
    public function discardPendingChargesForUndeliveredOrders(Delivery $delivery): void
    {
        $undeliveredOrderIds = $delivery->deliveryOrders()
            ->where('delivery_status', '!=', DeliveryOrderStatus::DELIVERED)
            ->pluck('order_id')
            ->all();

        if (empty($undeliveredOrderIds)) {
            return;
        }

        $deliveredElsewhere = DeliveryOrder::whereIn('order_id', $undeliveredOrderIds)
            ->where('delivery_id', '!=', $delivery->id)
            ->where('delivery_status', DeliveryOrderStatus::DELIVERED)
            ->pluck('order_id')
            ->all();

        $orderIds = array_values(array_diff($undeliveredOrderIds, $deliveredElsewhere));
        if (empty($orderIds)) {
            return;
        }

        $entries = AccountEntry::where('source_type', 'orders')
            ->whereIn('source_id', $orderIds)
            ->where('type', AccountEntryType::CHARGE)
            ->where('validation_status', AccountEntryValidationStatus::PENDING)
            ->get();

        // delete() por modelo: pasa por AccountEntryObserver (pending => sin impacto en saldo).
        foreach ($entries as $entry) {
            $entry->delete();
        }
    }

    /**
     * Al cerrar el reparto: todos los movimientos ligados pasan a validated (impacto en saldo vía observer).
     */
    public function validateAllPendingEntriesForClosedDelivery(Delivery $delivery): void
    {
        $deliveryOrderIds = $delivery->deliveryOrders()->pluck('id')->all();
        // Solo cargos de pedidos entregados en ESTE reparto: si un pedido quedó
        // fallido acá (o se pasó a fallido en la revisión), su cargo no se valida;
        // si se re-entregó en otro reparto, ese cargo lo valida el otro cierre.
        $orderIds = $delivery->deliveryOrders()
            ->where('delivery_status', DeliveryOrderStatus::DELIVERED)
            ->pluck('order_id')
            ->all();

        $entries = AccountEntry::query()
            ->where(function ($q) use ($delivery, $deliveryOrderIds, $orderIds) {
                $q->where(function ($q2) use ($delivery) {
                    $q2->where('source_type', 'delivery')->where('source_id', $delivery->id);
                });
                if (!empty($deliveryOrderIds)) {
                    $q->orWhere(function ($q2) use ($deliveryOrderIds) {
                        $q2->where('source_type', 'delivery_orders')->whereIn('source_id', $deliveryOrderIds);
                    });
                }
                if (!empty($orderIds)) {
                    $q->orWhere(function ($q2) use ($orderIds) {
                        $q2->where('source_type', 'orders')->whereIn('source_id', $orderIds);
                    });
                }
            })
            ->where('validation_status', '!=', AccountEntryValidationStatus::VALIDATED)
            ->get();

        foreach ($entries as $entry) {
            $entry->update(['validation_status' => AccountEntryValidationStatus::VALIDATED]);
        }
    }
}
