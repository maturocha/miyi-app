<?php

namespace App\Console\Commands;

use App\Services\OrderTotalsAuditService;
use Illuminate\Console\Command;

/**
 * SOLO LECTURA. Lista los pedidos desde 2025 cuyo total no cierra con
 * líneas + envío - líneas * descuento / 100, a un CSV. No corrige nada: el total
 * mueve plata (cargo en cuenta corriente) y cada caso se revisa a mano.
 */
class AuditOrderTotals extends Command
{
    protected $signature = 'orders:audit-totals
                            {--output= : Ruta del CSV (default storage/app/audits/orders-totals-<fecha>.csv)}';

    protected $description = 'Solo lectura: CSV de pedidos desde 2025 cuyo total no cierra con sus líneas.';

    public function handle(OrderTotalsAuditService $audit): int
    {
        $rows = $audit->totalMismatches();

        $path = $this->option('output')
            ?: storage_path('app/audits/orders-totals-' . date('Ymd-His') . '.csv');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'id', 'fecha', 'estado', 'total', 'suma_lineas', 'descuento_pct', 'envio',
            'total_esperado', 'diferencia', 'cargos_cc', 'estado_cargos', 'monto_cargos', 'entregado_reparto_cerrado',
        ]);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->id, $row->date, $row->status, $row->total, $row->lines_sum, $row->discount,
                $row->delivery_cost, $row->expected_total, $row->diff, $row->charge_count,
                $row->charge_statuses, $row->charge_amount, $row->locked ? 'si' : 'no',
            ]);
        }
        fclose($handle);

        $this->info(sprintf(
            '%d pedido(s) desde %s con total descuadrado (> %.2f). Con cargo en cuenta corriente: %d. En reparto cerrado: %d.',
            $rows->count(),
            OrderTotalsAuditService::SINCE,
            OrderTotalsAuditService::TOLERANCE,
            $rows->where('charge_count', '>', 0)->count(),
            $rows->where('locked', true)->count()
        ));
        $this->line("CSV: $path");

        return 0;
    }
}
