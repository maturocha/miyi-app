<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de orders.total_bruto = ROUND(SUM(order_details.price_final), 2).
 *
 * Alcance: pedidos desde 2025-01-01, con líneas, cuyo total_bruto difiere de la
 * suma de líneas en más de 0.02. Incluye los saturados en 999999.99 por el
 * viejo DOUBLE(8,2) (ver 2026_11_28_100000) y los de 2025 con total_bruto de más.
 *
 * Qué NO hace, a propósito:
 * - No toca total, cargos/asientos de cuenta corriente ni saldos: total_bruto es
 *   informativo y no mueve plata. No llama recalculateTotals() ni syncPendingCharge().
 * - No toca updated_at ni dispara observers: usa query builder (DB::table), no
 *   Eloquent, así que no hay eventos ni timestamps automáticos.
 * - No toca pedidos anteriores a 2025 (lógica histórica) ni pedidos sin líneas
 *   (no hay de dónde sacar el valor).
 *
 * Por qué así y no un único UPDATE ... JOIN: el UPDATE con join de MySQL no es
 * portable a SQLite (tests), y cada pedido lleva su propio valor. Se seleccionan
 * los candidatos con un join a la suma de líneas (una consulta por chunk) y se
 * actualiza por id, en chunks de CHUNK pedidos, cada chunk en su transacción.
 * El volumen es chico (cientos de pedidos): el costo lo domina la consulta de
 * selección, no los UPDATE.
 *
 * Idempotente: un pedido corregido deja de cumplir la condición; volver a
 * correrla no cambia nada.
 */
class BackfillOrdersTotalBruto extends Migration
{
    private const SINCE = '2025-01-01';
    private const TOLERANCE = 0.02;
    private const CHUNK = 500;

    public function up()
    {
        $this->assertColumnIsWide();

        $updated = 0;

        $this->candidates()->chunkById(self::CHUNK, function ($rows) use (&$updated) {
            DB::transaction(function () use ($rows, &$updated) {
                foreach ($rows as $row) {
                    $updated += DB::table('orders')
                        ->where('id', $row->id)
                        ->update(['total_bruto' => round((float) $row->lines_sum, 2)]);
                }
            });
        }, 'o.id', 'id');

        if (!app()->runningUnitTests()) {
            echo "Backfill total_bruto: $updated pedido(s) corregido(s).\n";
        }
    }

    public function down()
    {
        // No-op: no hay forma segura de revertir. El valor anterior era un dato
        // recortado (999999.99) o incorrecto y no se guardó; volver a escribirlo
        // sería reintroducir el error. total y los cargos nunca se tocaron.
    }

    /**
     * Pedidos a corregir: { id, lines_sum }.
     */
    private function candidates()
    {
        $lines = DB::table('order_details')
            ->select('id_order', DB::raw('ROUND(SUM(price_final), 2) as lines_sum'))
            ->groupBy('id_order');

        // Tolerancia como literal numérico (constante, no input): con un binding,
        // PDO la manda como texto y SQLite compara número > texto como falso.
        $tolerance = number_format(self::TOLERANCE, 2, '.', '');

        return DB::table('orders as o')
            ->joinSub($lines, 'd', 'd.id_order', '=', 'o.id')
            ->where('o.date', '>=', self::SINCE)
            ->whereRaw("ABS(o.total_bruto - d.lines_sum) > $tolerance")
            ->select('o.id', 'd.lines_sum');
    }

    /**
     * Con la columna todavía en DOUBLE(8,2) el UPDATE volvería a recortar en
     * 999999.99: corta antes de escribir. 2026_11_28_100000 corre antes.
     */
    private function assertColumnIsWide()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $type = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'orders')
            ->where('COLUMN_NAME', 'total_bruto')
            ->value('COLUMN_TYPE');

        if (strtolower((string) $type) !== 'double') {
            throw new \RuntimeException("orders.total_bruto es '$type': correr antes 2026_11_28_100000 (ensancha la columna).");
        }
    }
}
