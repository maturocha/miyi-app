<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DeliveryOrder extends Pivot
{
    protected $table = 'delivery_orders';

    protected $fillable = [
        'delivery_id',
        'order_id',
        'sequence',
        'delivery_status',
        'collected_amount',
        'payment_method',
        'payment_reference',
        'observations',
        'delivered_at',
        'failure_reason',
    ];

    protected $casts = [
        'collected_amount' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the delivery that owns the delivery order.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * Get the order that owns the delivery order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
