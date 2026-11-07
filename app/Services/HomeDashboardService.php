<?php

namespace App\Services;

use App\Models\Enums\DeliveryOrderStatus;
use App\Models\Enums\DeliveryStatus;
use App\Models\Enums\OrderStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resumen de la página de Inicio (GET /api/v1/home).
 *
 * Una sola respuesta con todas las secciones; cada sección se calcula y se
 * devuelve solo si el rol del usuario puede verla (key ausente si no). Los
 * permisos replican los de los módulos de origen. Ver docs/module/inicio.md.
 */
class HomeDashboardService
{
    const TZ = 'America/Argentina/Buenos_Aires';

    const ROLE_ADMIN = 1;
    const ROLE_VENTAS = 2;
    const ROLE_REPARTIDOR = 3;
    const ROLE_ADMINISTRACION = 4;

    const OPEN_DELIVERIES_LIMIT = 5;
    const LATEST_ORDERS_LIMIT = 6;
    const TOP_PRODUCTS_LIMIT = 5;
    const LOW_STOCK_LIMIT = 6;
    const LOW_STOCK_SALES_DAYS = 30;
    const DEBTORS_LIMIT = 5;
    const BIRTHDAYS_LIMIT = 8;
    const BIRTHDAYS_WINDOW_DAYS = 14;

    /** @var Carbon Hoy en hora Argentina (orders.date se guarda en hora AR). */
    private $today;

    public function summary(User $user): array
    {
        $this->today = Carbon::now(self::TZ)->startOfDay();
        $role = (int) $user->role_id;

        $data = [
            'meta' => [
                'generated_at' => Carbon::now(self::TZ)->toIso8601String(),
                'today' => $this->today->format('Y-m-d'),
                'month_start' => $this->today->copy()->startOfMonth()->format('Y-m-d'),
            ],
        ];

        // Mismo gate que /finance/dashboard (Analytics).
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_VENTAS, self::ROLE_ADMINISTRACION], true)) {
            $data['sales'] = $this->sales();
            $data['top_products'] = $this->topProducts();
        }

        // Mismo gate que el menú de Repartos; el repartidor solo ve los suyos.
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_REPARTIDOR, self::ROLE_ADMINISTRACION], true)) {
            $ownerId = in_array($role, [self::ROLE_ADMIN, self::ROLE_ADMINISTRACION], true) ? null : (int) $user->id;
            $data['open_deliveries'] = $this->openDeliveries($ownerId);
        }

        // Mismo scope que OrdersController::paginatedQuery: salvo admin y
        // repartidor, cada usuario ve solo sus pedidos.
        $orderUserId = in_array($role, [self::ROLE_ADMIN, self::ROLE_REPARTIDOR], true) ? null : (int) $user->id;
        $data['latest_orders'] = $this->latestOrders($orderUserId);

        // min_stock y la deuda global solo se exponen a admin / administración.
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_ADMINISTRACION], true)) {
            $data['low_stock'] = $this->lowStock();
            $data['debtors'] = $this->debtors();
        }

        // Cumpleaños (#55): roles comerciales. Si la migración de
        // customers.birthday todavía no corrió, la sección no viene y la home
        // sigue funcionando (protege el orden de deploy entre #55 y #56).
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_VENTAS, self::ROLE_ADMINISTRACION], true)
            && $this->hasBirthdayColumn()) {
            $data['birthdays'] = $this->birthdays();
        }

        return $data;
    }

    /**
     * Clientes cuyo próximo cumpleaños cae entre hoy y BIRTHDAYS_WINDOW_DAYS.
     * El año se ajusta en SQL: si el día/mes ya pasó este año, va al próximo.
     * El 29/02 en años no bisiestos cae el 28/02 (DATE_ADD de MariaDB).
     */
    private function birthdays(): array
    {
        $today = $this->today->format('Y-m-d');

        $daysUntil = 'DATEDIFF(DATE_ADD(c.birthday, INTERVAL ('
            . 'YEAR(?) - YEAR(c.birthday)'
            . " + IF(DATE_FORMAT(c.birthday, '%m%d') < DATE_FORMAT(?, '%m%d'), 1, 0)"
            . ') YEAR), ?)';

        $rows = DB::table('customers as c')
            ->whereNull('c.deleted_at')
            ->whereNotNull('c.birthday')
            ->select(['c.id', 'c.name', 'c.birthday', 'c.cellphone'])
            ->selectRaw($daysUntil . ' as days_until', [$today, $today, $today])
            ->having('days_until', '<=', self::BIRTHDAYS_WINDOW_DAYS)
            ->orderBy('days_until')
            ->orderBy('c.name')
            ->limit(self::BIRTHDAYS_LIMIT)
            ->get();

        return [
            'window_days' => self::BIRTHDAYS_WINDOW_DAYS,
            'items' => array_map(function ($r) {
                $cellphone = trim((string) $r->cellphone);
                return [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'birthday' => $this->dateOnly($r->birthday),
                    'days_until' => (int) $r->days_until,
                    'cellphone' => $cellphone !== '' ? $cellphone : null,
                ];
            }, $rows->all()),
        ];
    }

    /**
     * Solo se cachea el "sí" (la columna no desaparece): mientras no exista se
     * vuelve a chequear en cada cálculo (1 query a information_schema, y la
     * respuesta de la home ya está cacheada 60s por usuario). Así la sección
     * aparece apenas corre la migración.
     */
    private function hasBirthdayColumn(): bool
    {
        $key = 'home:schema:customers.birthday';
        if (Cache::get($key)) {
            return true;
        }
        $exists = Schema::hasColumn('customers', 'birthday');
        if ($exists) {
            Cache::put($key, true, 86400);
        }
        return $exists;
    }

    /**
     * Ventas del mes en curso (hasta hoy) vs. el mismo período del mes anterior,
     * más la serie diaria para el sparkline. Excluye pedidos cancelados.
     */
    private function sales(): array
    {
        $monthStart = $this->today->copy()->startOfMonth();
        $tomorrow = $this->today->copy()->addDay();

        // Mismo período del mes anterior: del 1 al mismo día (acotado al último
        // día de ese mes, ej. 31/03 -> 01/02..28/02).
        $prevStart = $monthStart->copy()->subMonthNoOverflow();
        $prevDay = min($this->today->day, $prevStart->daysInMonth);
        $prevEnd = $prevStart->copy()->day($prevDay)->addDay();

        $current = $this->salesTotals($monthStart, $tomorrow);
        $previous = $this->salesTotals($prevStart, $prevEnd);

        $daily = $this->validOrders()
            ->where('o.date', '>=', $monthStart->format('Y-m-d'))
            ->where('o.date', '<', $tomorrow->format('Y-m-d'))
            ->selectRaw('DATE(o.date) as day, COALESCE(SUM(o.total), 0) as total, COUNT(*) as orders')
            ->groupBy(DB::raw('DATE(o.date)'))
            ->get()
            ->keyBy('day');

        $series = [];
        for ($d = $monthStart->copy(); $d->lt($tomorrow); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $row = $daily->get($key);
            $series[] = [
                'date' => $key,
                'total' => $this->round2($row ? (float) $row->total : 0.0),
                'orders' => $row ? (int) $row->orders : 0,
            ];
        }

        return [
            'month_total' => $current['total'],
            'month_orders' => $current['orders'],
            'prev_period_total' => $previous['total'],
            'prev_period_orders' => $previous['orders'],
            'daily' => $series,
        ];
    }

    private function salesTotals(Carbon $from, Carbon $toExclusive): array
    {
        $row = $this->validOrders()
            ->where('o.date', '>=', $from->format('Y-m-d'))
            ->where('o.date', '<', $toExclusive->format('Y-m-d'))
            ->selectRaw('COALESCE(SUM(o.total), 0) as total, COUNT(*) as orders')
            ->first();

        return [
            'total' => $this->round2((float) $row->total),
            'orders' => (int) $row->orders,
        ];
    }

    /**
     * Top productos del mes por cantidad vendida (mismo criterio de orden que
     * Analytics > Productos), sin pedidos cancelados.
     */
    private function topProducts(): array
    {
        $rows = $this->validOrders()
            ->join('order_details as od', 'od.id_order', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'od.id_product')
            ->where('o.date', '>=', $this->today->copy()->startOfMonth()->format('Y-m-d'))
            ->where('o.date', '<', $this->today->copy()->addDay()->format('Y-m-d'))
            ->select([
                'p.id',
                'p.name',
                'p.type_product',
                DB::raw('COALESCE(SUM(od.quantity), 0) as quantity_sold'),
                DB::raw('COALESCE(SUM(od.weight), 0) as weight_sold'),
            ])
            ->groupBy('p.id', 'p.name', 'p.type_product')
            ->orderByDesc(DB::raw('SUM(od.quantity)'))
            ->limit(self::TOP_PRODUCTS_LIMIT)
            ->get();

        return array_map(function ($r) {
            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'type_product' => $r->type_product,
                'quantity_sold' => (float) $r->quantity_sold,
                'weight_sold' => $this->round2((float) $r->weight_sold),
            ];
        }, $rows->all());
    }

    /**
     * Repartos no cerrados. Totales con subqueries (no se serializa el modelo
     * Delivery: su accessor `totals` hace una query por fila).
     */
    private function openDeliveries($ownerId): array
    {
        $base = DB::table('deliveries as d')
            ->where('d.status', '<>', DeliveryStatus::CLOSED)
            ->when($ownerId !== null, function ($q) use ($ownerId) {
                $q->where('d.owner_user_id', $ownerId);
            });

        $byStatus = (clone $base)
            ->selectRaw('d.status, COUNT(*) as total')
            ->groupBy('d.status')
            ->pluck('total', 'status');

        $counts = [];
        foreach ([DeliveryStatus::NOT_STARTED, DeliveryStatus::IN_PROGRESS, DeliveryStatus::FINISHED] as $status) {
            $counts[$status] = isset($byStatus[$status]) ? (int) $byStatus[$status] : 0;
        }

        $rows = (clone $base)
            ->leftJoin('users as u', 'u.id', '=', 'd.owner_user_id')
            ->select([
                'd.id',
                'd.delivery_date',
                'd.status',
                'd.started_at',
                'd.finished_at',
                'u.id as owner_id',
                'u.name as owner_name',
            ])
            ->selectSub(function ($q) {
                $q->from('delivery_orders as dor')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('dor.delivery_id', 'd.id');
            }, 'orders_count')
            ->selectSub(function ($q) {
                $q->from('delivery_orders as dor')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('dor.delivery_id', 'd.id')
                    ->where('dor.delivery_status', DeliveryOrderStatus::DELIVERED);
            }, 'delivered_orders_count')
            ->selectSub(function ($q) {
                $q->from('delivery_orders as dor')
                    ->join('orders as so', 'so.id', '=', 'dor.order_id')
                    ->selectRaw('COALESCE(SUM(so.total), 0)')
                    ->whereColumn('dor.delivery_id', 'd.id');
            }, 'orders_sales_total')
            ->orderBy('d.delivery_date', 'desc')
            ->orderBy('d.id', 'desc')
            ->limit(self::OPEN_DELIVERIES_LIMIT)
            ->get();

        // Reparto terminado hace más tiempo sin cerrar (para el insight "falta cerrarlo").
        $oldestFinished = null;
        if ($counts[DeliveryStatus::FINISHED] > 0) {
            $f = (clone $base)
                ->where('d.status', DeliveryStatus::FINISHED)
                ->orderByRaw('COALESCE(d.finished_at, d.delivery_date) asc')
                ->first(['d.id', 'd.delivery_date', 'd.finished_at']);
            if ($f) {
                $oldestFinished = [
                    'id' => (int) $f->id,
                    'delivery_date' => $this->dateOnly($f->delivery_date),
                    'finished_at' => $f->finished_at,
                ];
            }
        }

        return [
            'total' => array_sum($counts),
            'by_status' => $counts,
            'oldest_finished' => $oldestFinished,
            'items' => array_map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'delivery_date' => $this->dateOnly($r->delivery_date),
                    'status' => $r->status,
                    'started_at' => $r->started_at,
                    'finished_at' => $r->finished_at,
                    'owner' => $r->owner_id ? ['id' => (int) $r->owner_id, 'name' => $r->owner_name] : null,
                    'orders_count' => (int) $r->orders_count,
                    'delivered_orders_count' => (int) $r->delivered_orders_count,
                    'orders_sales_total' => $this->round2((float) $r->orders_sales_total),
                ];
            }, $rows->all()),
        ];
    }

    /**
     * Últimos pedidos por fecha (orders_date_id_idx / orders_user_date_id_idx).
     */
    private function latestOrders($userId): array
    {
        $rows = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.id_customer')
            ->when($userId !== null, function ($q) use ($userId) {
                $q->where('o.id_user', $userId);
            })
            ->select([
                'o.id',
                'o.date',
                'o.total',
                'o.status',
                'c.id as customer_id',
                'c.name as customer_name',
            ])
            ->orderBy('o.date', 'desc')
            ->orderBy('o.id', 'desc')
            ->limit(self::LATEST_ORDERS_LIMIT)
            ->get();

        return array_map(function ($r) {
            return [
                'id' => (int) $r->id,
                'date' => $r->date,
                'total' => $this->round2((float) $r->total),
                'status' => $r->status,
                'customer' => $r->customer_id ? ['id' => (int) $r->customer_id, 'name' => $r->customer_name] : null,
            ];
        }, $rows->all());
    }

    /**
     * Productos propios con umbral configurado (min_stock > 0), stock en o por
     * debajo del mínimo (regla de Product::inLowStock()) **y con ventas en los
     * últimos LOW_STOCK_SALES_DAYS días**. Sin ese filtro la lista es ruido: la
     * gran mayoría del catálogo tiene stock 0 porque no se trabaja. Ordenado
     * por demanda reciente (lo que más se vende y falta, primero).
     */
    private function lowStock(): array
    {
        $recentSales = $this->validOrders()
            ->join('order_details as od', 'od.id_order', '=', 'o.id')
            ->where('o.date', '>=', $this->today->copy()->subDays(self::LOW_STOCK_SALES_DAYS)->format('Y-m-d'))
            ->where('o.date', '<', $this->today->copy()->addDay()->format('Y-m-d'))
            ->select(['od.id_product', DB::raw('SUM(od.quantity) as sold_recent')])
            ->groupBy('od.id_product');

        $base = DB::table('products as p')
            ->joinSub($recentSales, 'rs', 'rs.id_product', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->where('p.own_product', 1)
            ->where('p.min_stock', '>', 0)
            ->whereRaw('p.stock <= p.min_stock');

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->select(['p.id', 'p.name', 'p.stock', 'p.min_stock', 'p.type_product', 'rs.sold_recent'])
            ->orderBy('rs.sold_recent', 'desc')
            ->orderBy('p.stock', 'asc')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get();

        return [
            'total' => $total,
            'sales_days' => self::LOW_STOCK_SALES_DAYS,
            'items' => array_map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'stock' => (float) $r->stock,
                    'min_stock' => (float) $r->min_stock,
                    'type_product' => $r->type_product,
                    'sold_recent' => (float) $r->sold_recent,
                ];
            }, $rows->all()),
        ];
    }

    /**
     * Clientes con saldo deudor (current_balance > 0 = debe), mismo criterio
     * que AccountEntriesController@balanceSummary.
     */
    private function debtors(): array
    {
        $base = DB::table('customers as c')
            ->whereNull('c.deleted_at')
            ->where('c.current_balance', '>', 0);

        $agg = (clone $base)
            ->selectRaw('COALESCE(SUM(c.current_balance), 0) as total_debt, COUNT(*) as debtors_count')
            ->first();

        $rows = (clone $base)
            ->select(['c.id', 'c.name', 'c.current_balance'])
            ->orderBy('c.current_balance', 'desc')
            ->limit(self::DEBTORS_LIMIT)
            ->get();

        return [
            'total_debt' => $this->round2((float) $agg->total_debt),
            'debtors_count' => (int) $agg->debtors_count,
            'items' => array_map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'current_balance' => $this->round2((float) $r->current_balance),
                ];
            }, $rows->all()),
        ];
    }

    /**
     * Pedidos que cuentan como venta: todos menos los cancelados (status NULL
     * en pedidos legacy se considera válido).
     */
    private function validOrders()
    {
        return DB::table('orders as o')
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '<>', OrderStatus::CANCELLED);
            });
    }

    private function dateOnly($value)
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    private function round2(float $value): float
    {
        return round($value, 2);
    }
}
