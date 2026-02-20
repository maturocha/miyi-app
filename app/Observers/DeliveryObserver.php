<?php

namespace App\Observers;

use App\Delivery;
use App\DeliveryStatus;
use App\Order;
use App\OrderStatus;

class DeliveryObserver
{
    /**
     * Handle the Delivery "updated" event.
     *
     * When the delivery status changes, update related orders accordingly.
     *
     * @param Delivery $delivery
     * @return void
     */
    public function updated(Delivery $delivery)
    {
        if ($delivery->isDirty('status')) {
            $newStatus = $delivery->status;

            if ($newStatus === DeliveryStatus::IN_PROGRESS) {
                // When delivery starts, mark all associated orders as "out for delivery"
                $orderIds = $delivery->orders()->pluck('orders.id');
                Order::whereIn('id', $orderIds)
                    ->update(['status' => OrderStatus::OUT_FOR_DELIVERY]);
            }
        }
    }
}
