<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * orders.total_bruto y orders.delivery_cost eran DOUBLE(8,2): el máximo es
 * 999999.99 y, con la conexión en modo no estricto ('strict' => false), MySQL /
 * MariaDB recortan en silencio cualquier valor mayor (pedido 73360: líneas por
 * 1124556.81, total_bruto guardado 999999.99). Pasan a DOUBLE sin precisión
 * fija, igual que orders.total.
 *
 * - DOUBLE y no DECIMAL: PDO devuelve DECIMAL como string y el front espera número.
 * - El ALTER es SQL directo (como 2026_10_18_100000_convert_customers_type...) y
 *   no ->change() de doctrine/dbal: dbal 2 no distingue DOUBLE(8,2) de DOUBLE
 *   (ambos son "float"), así que change() no garantiza quitar la precisión.
 * - Idempotente: si la columna ya es DOUBLE sin precisión, no hace nada.
 * - Solo esquema. Los datos los corrige la migración siguiente,
 *   2026_11_28_100001_backfill_orders_total_bruto (ver docs/module/orders/totales.md).
 * - MODIFY reconstruye la tabla (ALTER con copia): bloquea escrituras en
 *   `orders` unos segundos; correr fuera del horario de carga.
 */
class WidenOrdersTotalBrutoAndDeliveryCost extends Migration
{
    private const COLUMNS = ['total_bruto', 'delivery_cost'];

    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            // Tests (SQLite): los tipos son dinámicos, no hay tope que ensanchar.
            return;
        }

        $modifications = [];
        foreach (self::COLUMNS as $column) {
            $type = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'orders')
                ->where('COLUMN_NAME', $column)
                ->value('COLUMN_TYPE');
            if ($type === null) {
                throw new \RuntimeException("orders.$column no existe; revisar antes de migrar.");
            }
            if (strtolower($type) !== 'double') {
                $modifications[] = "MODIFY $column DOUBLE NOT NULL DEFAULT 0";
            }
        }

        if ($modifications) {
            DB::statement('ALTER TABLE orders ' . implode(', ', $modifications));
        }
    }

    public function down()
    {
        // No se vuelve a DOUBLE(8,2) a propósito: achicar recortaría (sin error,
        // en modo no estricto) todo total_bruto / delivery_cost mayor a 999999.99
        // y se perderían los valores corregidos. Ensanchar es compatible con el
        // código anterior, así que un rollback del código no necesita esto.
    }
}
