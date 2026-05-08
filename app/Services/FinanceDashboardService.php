<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class FinanceDashboardService
{
    public function dashboard(array $filters): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $curr = $this->computeAggregates($filters, $dateFrom, $dateTo);

        $kpis = $this->buildKpis($curr);
        $series = $this->buildSeries($filters, $dateFrom, $dateTo);
        $breakdowns = $this->buildBreakdowns($filters, $dateFrom, $dateTo);
        $recaudacionesRows = $this->buildRecaudacionesRows($filters, $dateFrom, $dateTo);

        return [
            'meta' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'kpis' => $kpis,
            'series' => $series,
            'breakdowns' => $breakdowns,
            'recaudaciones_rows' => $recaudacionesRows,
        ];
    }

    public function rowDetail(array $filters): array
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $ownerUserId = $filters['owner_user_id'];
        $zoneId = $filters['zone_id'];
        $customerId = $filters['customer_id'];
        $paymentMethod = $filters['payment_method'];
        $collectionStatus = $filters['collection_status'];

        $page = max(1, (int) $filters['page']);
        $perPage = min(100, max(1, (int) $filters['per_page']));

        $entriesQuery = $this->baseCollectionsQuery($dateFrom, $dateTo, [
            'owner_user_id' => $ownerUserId,
            'zone_id' => $zoneId,
            'customer_id' => $customerId,
            'payment_method' => $paymentMethod,
            'collection_status' => $collectionStatus,
        ])->orderByDesc('ae.occurred_at');

        $total = (clone $entriesQuery)->count(DB::raw('distinct ae.id'));

        $rows = $entriesQuery
            ->select([
                'ae.id',
                'ae.customer_id',
                'ae.type',
                'ae.direction',
                'ae.amount',
                'ae.occurred_at',
                'ae.notes',
                'ae.source_type',
                'ae.source_id',
                'ae.validation_status',
                'c.name as customer_name',
                DB::raw('COALESCE(pms.total_lines, ae.amount) as amount_effective'),
            ])
            ->leftJoinSub($this->paymentMethodsSubquery($dateFrom, $dateTo), 'pms', function ($join) {
                $join->on('pms.account_entry_id', '=', 'ae.id');
            })
            ->forPage($page, $perPage)
            ->get();

        // Orders associated (best-effort): for owner filter, pull orders in deliveries of the period.
        $orders = [];
        if ($ownerUserId || $zoneId) {
            $orders = $this->ordersQuery($dateFrom, $dateTo, [
                'owner_user_id' => $ownerUserId,
                'zone_id' => $zoneId,
                'customer_id' => $customerId,
            ])
                ->select([
                    'o.id',
                    'o.date',
                    'o.total',
                    'c.name as customer_name',
                    'z.id as zone_id',
                    'z.name as zone_name',
                ])
                ->orderByDesc('o.date')
                ->limit(200)
                ->get();
        }

        return [
            'meta' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
            ],
            'entries' => $rows,
            'orders' => $orders,
        ];
    }

    private function computeAggregates(array $filters, string $dateFrom, string $dateTo): array
    {
        $sold = $this->ordersQuery($dateFrom, $dateTo, $filters)
            ->select([
                DB::raw('COUNT(DISTINCT o.id) as orders_count'),
                DB::raw('COALESCE(SUM(o.total), 0) as sold_total'),
            ])
            ->first();

        $collections = $this->collectionsTotals($dateFrom, $dateTo, $filters);

        $soldTotal = $this->round2((float) ($sold ? $sold->sold_total : 0));
        $ordersCount = (int) ($sold ? $sold->orders_count : 0);
        $collectedTotal = $this->round2((float) $collections['total']);

        return [
            'sold_total' => $soldTotal,
            'orders_count' => $ordersCount,
            'collected_total' => $collectedTotal,
        ];
    }

    private function buildKpis(array $curr): array
    {
        return [
            'sold_total' => ['value' => $this->round2((float) $curr['sold_total'])],
            'collected_total' => ['value' => $this->round2((float) $curr['collected_total'])],
            'orders_count' => ['value' => (int) $curr['orders_count']],
        ];
    }

    private function buildSeries(array $filters, string $dateFrom, string $dateTo): array
    {
        $soldByDay = $this->ordersQuery($dateFrom, $dateTo, $filters)
            ->select([
                DB::raw("DATE(o.date) as day"),
                DB::raw('COALESCE(SUM(o.total), 0) as sold'),
            ])
            ->groupBy(DB::raw('DATE(o.date)'))
            ->pluck('sold', 'day')
            ->toArray();

        $collectedByDay = $this->collectionsByDay($dateFrom, $dateTo, $filters);

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $rows = [];
        foreach ($period as $d) {
            $day = $d->format('Y-m-d');
            $rows[] = [
                'date' => $day,
                'sold' => $this->round2((float) ($soldByDay[$day] ?? 0)),
                'collected' => $this->round2((float) ($collectedByDay[$day] ?? 0)),
            ];
        }

        return [
            'daily_sales_vs_collections' => $rows,
        ];
    }

    private function buildBreakdowns(array $filters, string $dateFrom, string $dateTo): array
    {
        $byPaymentMethod = $this->collectionsByPaymentMethod($dateFrom, $dateTo, $filters);
        $byZone = $this->breakdownByZone($dateFrom, $dateTo, $filters);

        return [
            'by_payment_method' => $byPaymentMethod,
            'by_zone' => $byZone,
        ];
    }

    private function breakdownByZone(string $dateFrom, string $dateTo, array $filters): array
    {
        $sold = $this->ordersQuery($dateFrom, $dateTo, $filters)
            ->select([
                'z.id as zone_id',
                'z.name as zone_name',
                DB::raw('COALESCE(SUM(o.total), 0) as sold_total'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count'),
            ])
            ->groupBy('z.id', 'z.name')
            ->get();

        $collected = $this->collectionsByZone($dateFrom, $dateTo, $filters);
        $collectedMap = [];
        foreach ($collected as $row) {
            $collectedMap[(int) $row->zone_id] = (float) $row->collected_total;
        }

        $rows = [];
        foreach ($sold as $row) {
            $zoneId = (int) $row->zone_id;
            $soldTotal = $this->round2((float) $row->sold_total);
            $colTotal = $this->round2((float) ($collectedMap[$zoneId] ?? 0));
            $rows[] = [
                'zone_id' => $zoneId,
                'zone_name' => $row->zone_name,
                'orders_count' => (int) $row->orders_count,
                'sold_total' => $soldTotal,
                'collected_total' => $colTotal,
                'pending_total' => max(0, $soldTotal - $colTotal),
                'efficiency' => $soldTotal > 0 ? ($colTotal / $soldTotal) : 0,
            ];
        }

        // Also include zones that only have collections (no sales in range)
        foreach ($collected as $row) {
            $zoneId = (int) $row->zone_id;
            $exists = false;
            foreach ($rows as $r) {
                if ((int) $r['zone_id'] === $zoneId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $rows[] = [
                    'zone_id' => $zoneId,
                    'zone_name' => $row->zone_name,
                    'orders_count' => 0,
                    'sold_total' => 0,
                    'collected_total' => $this->round2((float) $row->collected_total),
                    'pending_total' => 0,
                    'efficiency' => 0,
                ];
            }
        }

        usort($rows, function ($a, $b) {
            return ($b['sold_total'] <=> $a['sold_total']);
        });

        return $rows;
    }

    private function buildTableRows(array $filters, string $dateFrom, string $dateTo): array
    {
        // Group by zone + delivery owner (repartidor). Source of sales: orders linked to deliveries in range.
        $q = DB::table('delivery_orders as do')
            ->join('deliveries as d', 'd.id', '=', 'do.delivery_id')
            ->join('orders as o', 'o.id', '=', 'do.order_id')
            ->join('customers as c', 'c.id', '=', 'o.id_customer')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->join('users as u', 'u.id', '=', 'd.owner_user_id')
            ->whereBetween('d.delivery_date', [$dateFrom, $dateTo]);

        if (!empty($filters['zone_id'])) {
            $q->where('z.id', (int) $filters['zone_id']);
        }
        if (!empty($filters['owner_user_id'])) {
            $q->where('d.owner_user_id', (int) $filters['owner_user_id']);
        }
        if (!empty($filters['customer_id'])) {
            $q->where('o.id_customer', (int) $filters['customer_id']);
        }

        $soldRows = $q
            ->select([
                'z.id as zone_id',
                'z.name as zone_name',
                'u.id as owner_user_id',
                'u.name as owner_name',
                DB::raw('COUNT(DISTINCT o.id) as orders_count'),
                DB::raw('COALESCE(SUM(o.total), 0) as sold_total'),
            ])
            ->groupBy('z.id', 'z.name', 'u.id', 'u.name')
            ->get();

        $collectionsRows = $this->collectionsByZoneAndOwner($dateFrom, $dateTo, $filters);
        $colMap = [];
        foreach ($collectionsRows as $r) {
            $key = (int) $r->zone_id . ':' . (int) $r->owner_user_id;
            $colMap[$key] = [
                'collected_total' => (float) $r->collected_total,
                'last_collected_at' => $r->last_collected_at,
            ];
        }

        $rows = [];
        foreach ($soldRows as $r) {
            $key = (int) $r->zone_id . ':' . (int) $r->owner_user_id;
            $soldTotal = (float) $r->sold_total;
            $collectedTotal = isset($colMap[$key]) ? (float) $colMap[$key]['collected_total'] : 0.0;
            $last = isset($colMap[$key]) ? $colMap[$key]['last_collected_at'] : null;
            $rows[] = [
                'zone_id' => (int) $r->zone_id,
                'zone_name' => $r->zone_name,
                'owner_user_id' => (int) $r->owner_user_id,
                'owner_name' => $r->owner_name,
                'orders_count' => (int) $r->orders_count,
                'sold_total' => $soldTotal,
                'collected_total' => $collectedTotal,
                'last_collected_at' => $last,
            ];
        }

        usort($rows, function ($a, $b) {
            return ($b['sold_total'] <=> $a['sold_total']);
        });

        return $rows;
    }

    private function ordersQuery(string $dateFrom, string $dateTo, array $filters)
    {
        $q = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.id_customer')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->whereBetween('o.date', [$dateFrom, $dateTo]);

        if (!empty($filters['zone_id'])) {
            $q->where('z.id', (int) $filters['zone_id']);
        }
        if (!empty($filters['customer_id'])) {
            $q->where('o.id_customer', (int) $filters['customer_id']);
        }

        // Optional owner filter: only orders linked to deliveries owned by user
        if (!empty($filters['owner_user_id'])) {
            $ownerId = (int) $filters['owner_user_id'];
            $q->join('delivery_orders as do', 'do.order_id', '=', 'o.id')
              ->join('deliveries as d', 'd.id', '=', 'do.delivery_id')
              ->where('d.owner_user_id', $ownerId);
        }

        return $q;
    }

    private function expensesTotal(string $dateFrom, string $dateTo, array $filters): float
    {
        $q = DB::table('deliveries as d')
            ->whereBetween('d.delivery_date', [$dateFrom, $dateTo]);

        if (!empty($filters['owner_user_id'])) {
            $q->where('d.owner_user_id', (int) $filters['owner_user_id']);
        }

        $total = $q->sum('d.expenses_amount');
        return (float) ($total ?: 0);
    }

    private function paymentMethodsSubquery(string $dateFrom, string $dateTo)
    {
        // Sum payment method lines per entry (for filtering and breakdowns)
        return DB::table('account_entry_payment_methods as aepm')
            ->select([
                'aepm.account_entry_id',
                DB::raw('SUM(aepm.amount) as total_lines'),
            ])
            ->groupBy('aepm.account_entry_id');
    }

    private function baseCollectionsQuery(string $dateFrom, string $dateTo, array $filters)
    {
        $fromTs = Carbon::parse($dateFrom)->startOfDay()->format('Y-m-d H:i:s');
        $toTs = Carbon::parse($dateTo)->endOfDay()->format('Y-m-d H:i:s');

        $q = DB::table('account_entries as ae')
            ->join('customers as c', 'c.id', '=', 'ae.customer_id')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->whereBetween('ae.occurred_at', [$fromTs, $toTs])
            ->where('ae.type', 'payment')
            ->where('ae.direction', 'credit');

        if (!empty($filters['customer_id'])) {
            $q->where('ae.customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['zone_id'])) {
            $q->where('z.id', (int) $filters['zone_id']);
        }
        if (!empty($filters['collection_status'])) {
            $q->where('ae.validation_status', $filters['collection_status']);
        }

        // Map entries to delivery for owner filter. Manual entries won't match.
        if (!empty($filters['owner_user_id'])) {
            $ownerId = (int) $filters['owner_user_id'];
            $q->leftJoin('delivery_orders as do_map', function ($join) {
                $join->on('do_map.id', '=', 'ae.source_id')
                    ->where('ae.source_type', '=', 'delivery_orders');
            });
            $q->leftJoin('deliveries as d_map', function ($join) {
                // delivery entries: source_id=delivery.id
                $join->on('d_map.id', '=', DB::raw("CASE WHEN ae.source_type='delivery' THEN ae.source_id ELSE do_map.delivery_id END"));
            });
            $q->where('d_map.owner_user_id', $ownerId);
        }

        if (!empty($filters['payment_method'])) {
            $q->join('account_entry_payment_methods as aepm_filter', 'aepm_filter.account_entry_id', '=', 'ae.id')
              ->where('aepm_filter.payment_method', $filters['payment_method']);
        }

        return $q;
    }

    private function collectionsTotals(string $dateFrom, string $dateTo, array $filters): array
    {
        $q = $this->baseCollectionsQuery($dateFrom, $dateTo, $filters);

        // If filtering by payment_method, sum method lines; otherwise use lines when present, fallback to entry amount.
        if (!empty($filters['payment_method'])) {
            $total = $q->sum('aepm_filter.amount');
            return ['total' => $this->round2((float) ($total ?: 0))];
        }

        $sub = $this->paymentMethodsSubquery($dateFrom, $dateTo);
        $totalRow = $q
            ->leftJoinSub($sub, 'pms', function ($join) {
                $join->on('pms.account_entry_id', '=', 'ae.id');
            })
            ->select([DB::raw('COALESCE(SUM(COALESCE(pms.total_lines, ae.amount)), 0) as total')])
            ->first();

        return ['total' => $this->round2((float) ($totalRow ? $totalRow->total : 0))];
    }

    private function collectionsByDay(string $dateFrom, string $dateTo, array $filters): array
    {
        $q = $this->baseCollectionsQuery($dateFrom, $dateTo, $filters);

        if (!empty($filters['payment_method'])) {
            $rows = $q
                ->select([
                    DB::raw("DATE(ae.occurred_at) as day"),
                    DB::raw('COALESCE(SUM(aepm_filter.amount), 0) as collected'),
                ])
                ->groupBy(DB::raw('DATE(ae.occurred_at)'))
                ->get();
        } else {
            $sub = $this->paymentMethodsSubquery($dateFrom, $dateTo);
            $rows = $q
                ->leftJoinSub($sub, 'pms', function ($join) {
                    $join->on('pms.account_entry_id', '=', 'ae.id');
                })
                ->select([
                    DB::raw("DATE(ae.occurred_at) as day"),
                    DB::raw('COALESCE(SUM(COALESCE(pms.total_lines, ae.amount)), 0) as collected'),
                ])
                ->groupBy(DB::raw('DATE(ae.occurred_at)'))
                ->get();
        }

        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = $this->round2((float) $r->collected);
        }
        return $map;
    }

    private function collectionsByPaymentMethod(string $dateFrom, string $dateTo, array $filters): array
    {
        $fromTs = Carbon::parse($dateFrom)->startOfDay()->format('Y-m-d H:i:s');
        $toTs = Carbon::parse($dateTo)->endOfDay()->format('Y-m-d H:i:s');

        $q = DB::table('account_entries as ae')
            ->join('account_entry_payment_methods as aepm', 'aepm.account_entry_id', '=', 'ae.id')
            ->join('customers as c', 'c.id', '=', 'ae.customer_id')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->whereBetween('ae.occurred_at', [$fromTs, $toTs])
            ->where('ae.type', 'payment')
            ->where('ae.direction', 'credit');

        if (!empty($filters['zone_id'])) {
            $q->where('z.id', (int) $filters['zone_id']);
        }
        if (!empty($filters['customer_id'])) {
            $q->where('ae.customer_id', (int) $filters['customer_id']);
        }
        if (!empty($filters['collection_status'])) {
            $q->where('ae.validation_status', $filters['collection_status']);
        }
        if (!empty($filters['owner_user_id'])) {
            $ownerId = (int) $filters['owner_user_id'];
            $q->leftJoin('delivery_orders as do_map', function ($join) {
                $join->on('do_map.id', '=', 'ae.source_id')
                    ->where('ae.source_type', '=', 'delivery_orders');
            });
            $q->leftJoin('deliveries as d_map', function ($join) {
                $join->on('d_map.id', '=', DB::raw("CASE WHEN ae.source_type='delivery' THEN ae.source_id ELSE do_map.delivery_id END"));
            });
            $q->where('d_map.owner_user_id', $ownerId);
        }

        $rows = $q
            ->select([
                'aepm.payment_method',
                DB::raw('COALESCE(SUM(aepm.amount), 0) as total'),
            ])
            ->groupBy('aepm.payment_method')
            ->orderByDesc(DB::raw('SUM(aepm.amount)'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'payment_method' => $r->payment_method,
                'total' => $this->round2((float) $r->total),
            ];
        }
        return $out;
    }

    private function collectionsByZone(string $dateFrom, string $dateTo, array $filters)
    {
        $q = $this->baseCollectionsQuery($dateFrom, $dateTo, $filters);

        if (!empty($filters['payment_method'])) {
            return $q
                ->select([
                    'z.id as zone_id',
                    'z.name as zone_name',
                    DB::raw('COALESCE(SUM(aepm_filter.amount), 0) as collected_total'),
                ])
                ->groupBy('z.id', 'z.name')
                ->get();
        }

        $sub = $this->paymentMethodsSubquery($dateFrom, $dateTo);
        return $q
            ->leftJoinSub($sub, 'pms', function ($join) {
                $join->on('pms.account_entry_id', '=', 'ae.id');
            })
            ->select([
                'z.id as zone_id',
                'z.name as zone_name',
                DB::raw('COALESCE(SUM(COALESCE(pms.total_lines, ae.amount)), 0) as collected_total'),
            ])
            ->groupBy('z.id', 'z.name')
            ->get();
    }

    private function collectionsByZoneAndOwner(string $dateFrom, string $dateTo, array $filters)
    {
        $fromTs = Carbon::parse($dateFrom)->startOfDay()->format('Y-m-d H:i:s');
        $toTs = Carbon::parse($dateTo)->endOfDay()->format('Y-m-d H:i:s');

        $ownerFilter = !empty($filters['owner_user_id']) ? (int) $filters['owner_user_id'] : null;
        $zoneFilter = !empty($filters['zone_id']) ? (int) $filters['zone_id'] : null;
        $customerFilter = !empty($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        $collectionStatus = !empty($filters['collection_status']) ? $filters['collection_status'] : null;
        $paymentMethod = !empty($filters['payment_method']) ? $filters['payment_method'] : null;

        $q = DB::table('account_entries as ae')
            ->join('customers as c', 'c.id', '=', 'ae.customer_id')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->whereBetween('ae.occurred_at', [$fromTs, $toTs])
            ->where('ae.type', 'payment')
            ->where('ae.direction', 'credit')
            ->leftJoin('delivery_orders as do_map', function ($join) {
                $join->on('do_map.id', '=', 'ae.source_id')
                    ->where('ae.source_type', '=', 'delivery_orders');
            })
            ->leftJoin('deliveries as d', function ($join) {
                $join->on('d.id', '=', DB::raw("CASE WHEN ae.source_type='delivery' THEN ae.source_id ELSE do_map.delivery_id END"));
            })
            ->join('users as u', 'u.id', '=', 'd.owner_user_id');

        if ($zoneFilter) $q->where('z.id', $zoneFilter);
        if ($customerFilter) $q->where('ae.customer_id', $customerFilter);
        if ($collectionStatus) $q->where('ae.validation_status', $collectionStatus);
        if ($ownerFilter) $q->where('d.owner_user_id', $ownerFilter);

        if ($paymentMethod) {
            $q->join('account_entry_payment_methods as aepm', 'aepm.account_entry_id', '=', 'ae.id')
              ->where('aepm.payment_method', $paymentMethod);
            return $q
                ->select([
                    'z.id as zone_id',
                    'u.id as owner_user_id',
                    DB::raw('COALESCE(SUM(aepm.amount), 0) as collected_total'),
                    DB::raw('MAX(ae.occurred_at) as last_collected_at'),
                ])
                ->groupBy('z.id', 'u.id')
                ->get();
        }

        $sub = $this->paymentMethodsSubquery($dateFrom, $dateTo);
        return $q
            ->leftJoinSub($sub, 'pms', function ($join) {
                $join->on('pms.account_entry_id', '=', 'ae.id');
            })
            ->select([
                'z.id as zone_id',
                'u.id as owner_user_id',
                DB::raw('COALESCE(SUM(COALESCE(pms.total_lines, ae.amount)), 0) as collected_total'),
                DB::raw('MAX(ae.occurred_at) as last_collected_at'),
            ])
            ->groupBy('z.id', 'u.id')
            ->get();
    }

    /**
     * Tabla estilo Recaudaciones V1: montos vendidos agrupados por zona y vendedor (users.id = orders.id_user)
     * pero para rango de fechas. Mantiene el shape compatible con el front viejo.
     */
    private function buildRecaudacionesRows(array $filters, string $dateFrom, string $dateTo)
    {
        $q = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.id_customer')
            ->join('neighborhoods as n', 'n.id', '=', 'c.id_neighborhood')
            ->join('zones as z', 'z.id', '=', 'n.id_zone')
            ->join('users as u', 'u.id', '=', 'o.id_user')
            ->whereBetween('o.date', [$dateFrom, $dateTo]);

        if (!empty($filters['zone_id'])) {
            $q->where('z.id', (int) $filters['zone_id']);
        }
        if (!empty($filters['owner_user_id'])) {
            // En esta tabla, owner_user_id representa el vendedor/repartidor seleccionado en el filtro de UI.
            // Mapeamos directo al usuario asociado al pedido.
            $q->where('u.id', (int) $filters['owner_user_id']);
        }
        if (!empty($filters['customer_id'])) {
            $q->where('o.id_customer', (int) $filters['customer_id']);
        }

        return $q
            ->select([
                'z.id as zone_id',
                'z.name as zone',
                'u.id as id_user',
                'u.name as name_user',
                DB::raw('ROUND(SUM(o.total), 2) as total'),
            ])
            ->groupBy('z.id', 'z.name', 'u.id', 'u.name')
            ->orderBy('z.id', 'asc')
            ->orderBy('u.id', 'asc')
            ->get();
    }

    private function round2(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }
}

