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
 * Es un único UPDATE con subconsulta correlacionada (la suma de líneas de cada
 * pedido), portable a MySQL/MariaDB y SQLite (tests). Es atómico: corrige todo o
 * nada. La subconsulta usa el índice de order_details.id_order (en una copia de
 * prod con 67k pedidos, el filtro tarda ~0.2 s).
 *
 * Idempotente: un pedido corregido deja de cumplir la condición; volver a
 * correrla no cambia nada.
 */
class BackfillOrdersTotalBruto extends Migration
{
    private const SINCE = '2025-01-01';

    /**
     * Tolerancia como literal numérico (constante, no input): con un binding,
     * PDO la manda como texto y SQLite compara número > texto como falso.
     */
    private const TOLERANCE = '0.02';

    private const LINES_SUM = '(SELECT ROUND(SUM(order_details.price_final), 2)
        FROM order_details WHERE order_details.id_order = orders.id)';

    public function up()
    {
        $this->assertColumnIsWide();

        $updated = DB::table('orders')
            ->where('orders.date', '>=', self::SINCE)
            // Sin líneas, la subconsulta da NULL y ABS(... - NULL) > x es NULL:
            // esos pedidos quedan afuera sin un EXISTS aparte (en MariaDB un
            // EXISTS acá se planifica como semi-join materializado y es mucho más lento).
            ->whereRaw('ABS(orders.total_bruto - ' . self::LINES_SUM . ') > ' . self::TOLERANCE)
            ->update(['total_bruto' => DB::raw(self::LINES_SUM)]);

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
