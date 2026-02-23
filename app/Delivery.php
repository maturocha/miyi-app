<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_date',
        'status',
        'owner_user_id',
        'started_at',
        'finished_at',
        'expenses_amount',
        'expenses_notes',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expenses_amount' => 'decimal:2',
    ];

    protected $appends = ['totals'];

    /**
     * Get the user (owner) that owns the delivery.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Pivot records (delivery_orders). Use this for show/detail with nested order + customer.
     */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)->orderBy('sequence');
    }

    /**
     * Get the orders for the delivery.
     *
     * Note: To access the related order model from DeliveryOrder, use $deliveryOrder->order,
     * so make sure the 'order' relationship is defined in DeliveryOrder, not here.
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_orders')
            ->using(DeliveryOrder::class)
            ->withPivot([
                'sequence',
                'delivery_status',
                'collected_amount',
                'payment_method',
                'payment_reference',
                'observations',
                'delivered_at',
                'failure_reason',
            ])
            ->withTimestamps()
            ->orderBy('delivery_orders.sequence');
    }

    /**
     * Get total collected amount from all orders.
     */
    public function getTotalCollectedAttribute(): float
    {
        return $this->orders()->sum('delivery_orders.collected_amount') ?? 0;
    }

    /**
     * Get net amount (collected - expenses).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->getTotalCollectedAttribute() - ($this->expenses_amount ?? 0);
    }

    /**
     * Totals for list/detail (collected, expenses, net).
     * Uses collected_total when set (e.g. from index subquery), otherwise computes via relation.
     */
    public function getTotalsAttribute(): array
    {
        $collected = isset($this->attributes['collected_total'])
            ? (float) $this->attributes['collected_total']
            : $this->getTotalCollectedAttribute();
        $expenses = (float) ($this->expenses_amount ?? 0);

        return [
            'collected' => $collected,
            'expenses' => $expenses,
            'net' => $collected - $expenses,
        ];
    }
}
