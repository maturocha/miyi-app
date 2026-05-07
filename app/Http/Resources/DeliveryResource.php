<?php

namespace App\Http\Resources;

use App\Models\AccountEntry;
use App\Models\Enums\AccountEntryType;
use App\Models\Enums\PaymentMethod;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/** @mixin \App\Models\Delivery */

class DeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $orderCollected = $this->relationLoaded('deliveryOrders')
            ? (float) $this->deliveryOrders->sum('collected_amount')
            : (float) ($this->orders()->sum('delivery_orders.collected_amount') ?? 0);

        $offRouteCollected = (float) AccountEntry::query()
            ->where('source_type', 'delivery')
            ->where('source_id', $this->id)
            ->where('type', AccountEntryType::PAYMENT)
            ->sum('amount');

        $totalCollected = $orderCollected + $offRouteCollected;
        $netAmount = $totalCollected - ($this->expenses_amount ?? 0);

        $cashCollected = $this->getCashCollectedFromOrders() + $this->computeCashCollectedOffRoute();

        return [
            'data' => [
                'id' => $this->id,
                'delivery_date' => $this->delivery_date ? $this->delivery_date->format('Y-m-d') : null,
                'status' => $this->status,
                'owner_user_id' => $this->owner_user_id,
                'owner' => $this->whenLoaded('owner', function () {
                    return $this->owner ? [
                        'id' => $this->owner->id,
                        'name' => $this->owner->name,
                    ] : null;
                }),
                'started_at' => $this->started_at ? $this->started_at->toDateTimeString() : null,
                'finished_at' => $this->finished_at ? $this->finished_at->toDateTimeString() : null,
                'expenses_amount' => (float) ($this->expenses_amount ?? 0),
                'expenses_notes' => $this->expenses_notes,
                'notes' => $this->notes,
                'orders' => DeliveryOrderResource::collection($this->whenLoaded('deliveryOrders')),
                'orders_count' => $this->when($this->relationLoaded('deliveryOrders'), function () {
                    return $this->deliveryOrders->count();
                }),
                'totals' => [
                    'collected' => (float) $totalCollected,
                    'cash_collected' => (float) $cashCollected,
                    'expenses' => (float) ($this->expenses_amount ?? 0),
                    'net' => (float) $netAmount,
                ],
                'account_entries' => AccountEntryResource::collection($this->whenLoaded('accountEntries')),
                'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
                'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            ]
        ];
    }

    /**
     * Efectivo cobrado vía entregas de pedidos (líneas delivery_order_payments o monto único legacy en delivery_orders).
     */
    protected function getCashCollectedFromOrders(): float
    {
        if ($this->relationLoaded('deliveryOrders')) {
            $cash = 0.0;
            foreach ($this->deliveryOrders as $do) {
                if ($do->relationLoaded('payments') && $do->payments && $do->payments->isNotEmpty()) {
                    foreach ($do->payments as $p) {
                        if (($p->payment_method ?? '') === PaymentMethod::CASH) {
                            $cash += (float) $p->amount;
                        }
                    }
                } elseif (($do->payment_method ?? '') === PaymentMethod::CASH) {
                    $cash += (float) ($do->collected_amount ?? 0);
                }
            }

            return $cash;
        }

        $deliveryOrderIds = DB::table('delivery_orders')
            ->where('delivery_id', $this->id)
            ->pluck('id');
        if ($deliveryOrderIds->isEmpty()) {
            return 0.0;
        }
        $ids = $deliveryOrderIds->all();
        $cashFromLines = (float) DB::table('delivery_order_payments')
            ->whereIn('delivery_order_id', $ids)
            ->where('payment_method', PaymentMethod::CASH)
            ->sum('amount');

        $cashLegacy = (float) DB::table('delivery_orders as dord')
            ->where('dord.delivery_id', $this->id)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('delivery_order_payments as dop')
                    ->whereColumn('dop.delivery_order_id', 'dord.id');
            })
            ->where('dord.payment_method', PaymentMethod::CASH)
            ->sum('dord.collected_amount');

        return $cashFromLines + $cashLegacy;
    }

    /**
     * Efectivo de cobros registrados con origen reparto (fuera de una entrega puntual).
     */
    protected function computeCashCollectedOffRoute(): float
    {
        return (float) DB::table('account_entry_payment_methods as apm')
            ->join('account_entries as ae', 'ae.id', '=', 'apm.account_entry_id')
            ->where('ae.source_type', 'delivery')
            ->where('ae.source_id', $this->id)
            ->where('ae.type', AccountEntryType::PAYMENT)
            ->where('apm.payment_method', PaymentMethod::CASH)
            ->sum('apm.amount');
    }
}
