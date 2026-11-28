<?php

namespace App\Services;

use App\Models\Enums\AccountEntryType;
use App\Models\Enums\DeliveryOrderStatus;
use App\Models\Enums\DeliveryStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta (solo lectura) de pedidos cuyo total no cierra con sus líneas.
 * La usa `orders:audit-totals`. No corrige nada: el total mueve plata.
 *
 * Solo pedidos desde SINCE: los anteriores tienen lógica histórica y no se tocan.
 */
class OrderTotalsAuditService
{
    public const SINCE = '2025-01-01';

    /** Diferencia tolerada por redondeo. */
    public const TOLERANCE = 0.02;

    /**
     * Pedidos (desde SINCE) cuyo total no cierra con
     * líneas + envío - líneas * descuento / 100 (diferencia > TOLERANCE).
     * Incluye si tienen cargo en cuenta corriente y si están bloqueados
     * (entregados en un reparto cerrado).
     *
     * @return Collection de objetos { id, date, status, total, lines_sum, discount,
     *         delivery_cost, expected_total, diff, charge_count, charge_statuses, locked }
     */
    public function totalMismatches(): Collection
    {
        $expected = 'ROUND(COALESCE(d.lines_sum, 0) + o.delivery_cost - COALESCE(d.lines_sum, 0) * o.discount / 100, 2)';

        $rows = DB::table('orders as o')
            ->leftJoinSub($this->linesSubquery(), 'd', 'd.id_order', '=', 'o.id')
            ->where('o.date', '>=', self::SINCE)
            ->whereRaw("ABS(o.total - $expected) > " . self::tolerance())
            ->orderBy('o.id')
            ->select(
                'o.id', 'o.date', 'o.status', 'o.total', 'o.discount', 'o.delivery_cost',
                DB::raw('ROUND(COALESCE(d.lines_sum, 0), 2) as lines_sum'),
                DB::raw("$expected as expected_total")
            )
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('id')->all();

        $charges = DB::table('account_entries')
            ->where('source_type', 'orders')
            ->where('type', AccountEntryType::CHARGE)
            ->whereIn('source_id', $ids)
            ->select('source_id', 'validation_status', 'amount')
            ->get()
            ->groupBy('source_id');

        $locked = DB::table('delivery_orders as dord')
            ->join('deliveries as dl', 'dl.id', '=', 'dord.delivery_id')
            ->whereIn('dord.order_id', $ids)
            ->where('dord.delivery_status', DeliveryOrderStatus::DELIVERED)
            ->where('dl.status', DeliveryStatus::CLOSED)
            ->pluck('dord.order_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->flip();

        return $rows->map(function ($row) use ($charges, $locked) {
            $orderCharges = $charges->get($row->id, collect());
            $row->diff = round((float) $row->total - (float) $row->expected_total, 2);
            $row->charge_count = $orderCharges->count();
            $row->charge_statuses = $orderCharges->pluck('validation_status')->unique()->sort()->implode('|');
            $row->charge_amount = $orderCharges->sum(function ($c) {
                return (float) $c->amount;
            });
            $row->locked = $locked->has((int) $row->id);

            return $row;
        });
    }

    /**
     * Tolerancia como literal numérico (constante interna, no input): con un
     * binding, PDO la manda como texto y SQLite compara número > texto como falso.
     */
    private static function tolerance(): string
    {
        return number_format(self::TOLERANCE, 2, '.', '');
    }

    private function linesSubquery()
    {
        return DB::table('order_details')
            ->select('id_order', DB::raw('SUM(price_final) as lines_sum'))
            ->groupBy('id_order');
    }
}
