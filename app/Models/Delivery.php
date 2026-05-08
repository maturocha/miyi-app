<?php

namespace App\Models;

use App\Models\Enums\AccountEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Cobrado por método de pago desde pedidos del reparto.
     *
     * - Usa `delivery_order_payments` cuando existe (multi-método).
     * - Fallback legacy: para `delivery_orders` sin filas en `delivery_order_payments`,
     *   agrupa por `delivery_orders.payment_method` y suma `delivery_orders.collected_amount`.
     *
     * @return array<string,float>
     */
    public function collectedByPaymentMethodFromOrders(): array
    {
        $deliveryOrderIds = DB::table('delivery_orders')
            ->where('delivery_id', $this->id)
            ->pluck('id');

        if ($deliveryOrderIds->isEmpty()) {
            return [];
        }

        $ids = $deliveryOrderIds->all();

        $rowsFromLines = DB::table('delivery_order_payments')
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->whereIn('delivery_order_id', $ids)
            ->groupBy('payment_method')
            ->get();

        $byMethod = [];
        foreach ($rowsFromLines as $row) {
            $method = (string) ($row->payment_method ?? '');
            $amount = (float) ($row->total ?? 0);
            if ($method === '' || $amount <= 0) {
                continue;
            }
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;
        }

        $rowsLegacy = DB::table('delivery_orders as dord')
            ->select('dord.payment_method', DB::raw('SUM(dord.collected_amount) as total'))
            ->where('dord.delivery_id', $this->id)
            ->whereNotNull('dord.payment_method')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('delivery_order_payments as dop')
                    ->whereColumn('dop.delivery_order_id', 'dord.id');
            })
            ->groupBy('dord.payment_method')
            ->get();

        foreach ($rowsLegacy as $row) {
            $method = (string) ($row->payment_method ?? '');
            $amount = (float) ($row->total ?? 0);
            if ($method === '' || $amount <= 0) {
                continue;
            }
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;
        }

        return $byMethod;
    }

    /**
     * Cobrado por método de pago pero de cobros fuera del reparto.
     *
     * @return array<string,float>
     */
    public function collectedByPaymentMethodOffRoute(): array
    {
        $rows = DB::table('account_entry_payment_methods as apm')
            ->join('account_entries as ae', 'ae.id', '=', 'apm.account_entry_id')
            ->select('apm.payment_method', DB::raw('SUM(apm.amount) as total'))
            ->where('ae.source_type', 'delivery')
            ->where('ae.source_id', $this->id)
            ->where('ae.type', AccountEntryType::PAYMENT)
            ->groupBy('apm.payment_method')
            ->get();

        $byMethod = [];
        foreach ($rows as $row) {
            $method = (string) ($row->payment_method ?? '');
            $amount = (float) ($row->total ?? 0);
            if ($method === '' || $amount <= 0) {
                continue;
            }
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;
        }

        return $byMethod;
    }

    /**
     * Cobrado combinado por método (orders + off-route).
     *
     * @return array<string,float>
     */
    public function collectedByPaymentMethod(): array
    {
        $combined = [];

        foreach ($this->collectedByPaymentMethodFromOrders() as $method => $amount) {
            if ($amount > 0) {
                $combined[$method] = ($combined[$method] ?? 0) + (float) $amount;
            }
        }

        foreach ($this->collectedByPaymentMethodOffRoute() as $method => $amount) {
            if ($amount > 0) {
                $combined[$method] = ($combined[$method] ?? 0) + (float) $amount;
            }
        }

        foreach ($combined as $method => $amount) {
            if ($amount <= 0) {
                unset($combined[$method]);
            }
        }

        return $combined;
    }
}
