<?php

namespace App\Console\Commands;

use App\Services\OrderTotalsAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige SOLO orders.total_bruto = ROUND(SUM(order_details.price_final), 2) en
 * pedidos desde 2025 que no cierran con sus líneas (incluye los saturados en
 * 999999.99 por el viejo DOUBLE(8,2)).
 *
 * No toca total, ni cargos de cuenta corriente, ni updated_at, ni dispara
 * observers: es un UPDATE directo de una columna informativa. Idempotente:
 * una segunda corrida no encuentra nada. Ver docs/module/orders/totales.md.
 */
class BackfillOrdersTotalBruto extends Command
{
    protected $signature = 'orders:backfill-total-bruto
                            {--dry-run : Solo muestra qué pedidos cambiaría, sin escribir}';

    protected $description = 'Recalcula orders.total_bruto desde las líneas (pedidos desde 2025 que no cierran). Solo total_bruto.';

    public function handle(OrderTotalsAuditService $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$this->columnIsWide()) {
            $this->error('orders.total_bruto sigue siendo DOUBLE(8,2): correr la migración antes (si no, vuelve a recortar en 999999.99).');
            return 1;
        }

        $rows = $audit->totalBrutoMismatches();
        $saturated = $rows->filter(function ($row) {
            return (float) $row->total_bruto >= OrderTotalsAuditService::SATURATED;
        })->count();

        $this->info(sprintf(
            '%s %d pedido(s) desde %s (%d saturado(s) en %.2f).',
            $dryRun ? '[dry-run] Cambiaría' : 'Corrigiendo',
            $rows->count(),
            OrderTotalsAuditService::SINCE,
            $saturated,
            OrderTotalsAuditService::SATURATED
        ));

        if ($rows->isEmpty()) {
            return 0;
        }

        $this->table(
            ['id', 'fecha', 'total_bruto actual', 'suma de líneas'],
            $rows->take(20)->map(function ($row) {
                return [$row->id, $row->date, $row->total_bruto, $row->lines_sum];
            })->all()
        );
        if ($rows->count() > 20) {
            $this->line('… y ' . ($rows->count() - 20) . ' más.');
        }

        if ($dryRun) {
            return 0;
        }

        $updated = 0;
        DB::transaction(function () use ($rows, &$updated) {
            foreach ($rows as $row) {
                // DB::table (no Eloquent): sin observers, sin updated_at, sin tocar total.
                $updated += DB::table('orders')
                    ->where('id', $row->id)
                    ->update(['total_bruto' => (float) $row->lines_sum]);
            }
        });

        $this->info("Listo: $updated pedido(s) actualizados.");

        return 0;
    }

    private function columnIsWide(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        $info = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'total_bruto'"
        );

        return $info && strtolower($info->COLUMN_TYPE) === 'double';
    }
}
