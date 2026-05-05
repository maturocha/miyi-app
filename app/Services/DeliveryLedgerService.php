<?php

namespace App\Services;

use App\Models\AccountEntry;
use App\Models\AccountEntryDirection;
use App\Models\AccountEntryPaymentMethod;
use App\Models\AccountEntryType;
use App\Models\AccountEntryValidationStatus;
use App\Models\Customer;
use App\Models\Delivery;
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

        $ordersByKey = [];
        foreach ($delivery->deliveryOrders as $deliveryOrder) {
            $order = $deliveryOrder->order;
            if (!$order) {
                continue;
            }
            $key = $order->id;
            if (isset($ordersByKey[$key])) {
                continue;
            }
            $ordersByKey[$key] = $order;
        }

        if (empty($ordersByKey)) {
            return;
        }

        $existingSourceIds = AccountEntry::where('source_type', 'orders')
            ->whereIn('source_id', array_keys($ordersByKey))
            ->pluck('source_id')
            ->flip()
            ->all();

        $occurredAt = $delivery->finished_at ?: now();

        DB::transaction(function () use ($ordersByKey, $existingSourceIds, $occurredAt) {
            foreach ($ordersByKey as $orderId => $order) {
                if (isset($existingSourceIds[$orderId])) {
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
                    'source_id' => $orderId,
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
     * Al cerrar el reparto: todos los movimientos ligados pasan a validated (impacto en saldo vía observer).
     */
    public function validateAllPendingEntriesForClosedDelivery(Delivery $delivery): void
    {
        $deliveryOrderIds = $delivery->deliveryOrders()->pluck('id')->all();
        $orderIds = $delivery->orders()->pluck('orders.id')->all();

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
