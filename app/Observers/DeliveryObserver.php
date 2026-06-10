<?php

namespace App\Observers;

use App\Models\Delivery;
use App\Models\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Enums\OrderStatus;
use App\Services\DeliveryLedgerService;

class DeliveryObserver
{
    protected $ledgerService;

    public function __construct(DeliveryLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Handle the Delivery "updated" event.
     *
     * When the delivery status changes, update related orders and ledger (CHARGES/PAYMENTS).
     *
     * @param Delivery $delivery
     * @return void
     */
    public function updated(Delivery $delivery)
    {
        if (!$delivery->isDirty('status')) {
            return;
        }
        $newStatus = $delivery->status;

        if ($newStatus === DeliveryStatus::IN_PROGRESS) {
            $orderIds = $delivery->orders()->pluck('orders.id');
            Order::whereIn('id', $orderIds)
                ->update(['status' => OrderStatus::OUT_FOR_DELIVERY]);
        }

        if ($newStatus === DeliveryStatus::FINISHED) {
            $this->ledgerService->createChargesForFinishedDelivery($delivery);
        }

        if ($newStatus === DeliveryStatus::CLOSED) {
            $this->ledgerService->createPaymentsForClosedDelivery($delivery);
            $this->ledgerService->validateAllPendingEntriesForClosedDelivery($delivery);
        }
    }

    /**
     * Handle the Delivery "deleting" event.
     *
     * When a delivery is deleted, reset related orders status and
     * prevent deletion if the delivery is already closed.
     *
     * @param Delivery $delivery
     * @return void
     *
     * @throws \Exception
     */
    public function deleting(Delivery $delivery)
    {
        if ($delivery->status === DeliveryStatus::CLOSED) {
            throw new \Exception('No se puede eliminar un reparto cerrado.');
        }

        $orderIds = $delivery->orders()->pluck('orders.id');

        if ($orderIds->isNotEmpty()) {
            Order::whereIn('id', $orderIds)
                ->update(['status' => OrderStatus::READY_TO_SHIP]);

            // Detach all related orders from this delivery
            $delivery->orders()->detach();
        }
    }
}
