<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * customers.type pasa de letras sueltas ('m' / 'p') a valores explícitos
 * ('mayorista' / 'particular').
 *
 * El tipo real de la columna no se asume: se lee de information_schema, se
 * valida que no haya valores desconocidos antes de tocar nada y se ensancha a
 * VARCHAR(20) antes del UPDATE (un CHAR(1) o ENUM('m','p') no alcanza para los
 * valores nuevos). Los ALTER TABLE no son transaccionales en MySQL/MariaDB: si
 * algo falla a mitad de camino, volver a correr la migración es seguro porque
 * cada paso es idempotente.
 */
class ConvertCustomersTypeToExplicitValues extends Migration
{
    /** @var array<string,string> código viejo => valor nuevo */
    private $forward = [
        'm' => 'mayorista',
        'p' => 'particular',
    ];

    /** @var array<string,string> valor nuevo => código viejo */
    private $backward = [
        'mayorista' => 'm',
        'particular' => 'p',
    ];

    public function up()
    {
        $this->assertOnlyKnownValues(array_merge(array_keys($this->forward), array_values($this->forward)));

        $column = $this->typeColumn();
        $default = $this->mappedDefault($column, $this->forward);

        $this->modifyTypeColumn($column, 'VARCHAR(20)', $default);

        DB::table('customers')
            ->whereRaw("LOWER(TRIM(type)) = 'm'")
            ->update(['type' => $this->forward['m']]);

        DB::table('customers')
            ->whereRaw("LOWER(TRIM(type)) = 'p'")
            ->update(['type' => $this->forward['p']]);
    }

    public function down()
    {
        $this->assertOnlyKnownValues(array_merge(array_keys($this->backward), array_values($this->backward)));

        $column = $this->typeColumn();
        $default = $this->mappedDefault($column, $this->backward);

        // Primero se convierten los datos (con la columna todavía ancha) y recién después se achica.
        DB::table('customers')
            ->whereRaw("LOWER(TRIM(type)) = 'mayorista'")
            ->update(['type' => $this->backward['mayorista']]);

        DB::table('customers')
            ->whereRaw("LOWER(TRIM(type)) = 'particular'")
            ->update(['type' => $this->backward['particular']]);

        // Vuelve a la definición original de la columna (char(191)), no a VARCHAR(1).
        $this->modifyTypeColumn($column, 'CHAR(191)', $default);
    }

    /**
     * Corta antes de tocar la tabla si hay valores que la migración no sabe convertir.
     *
     * @param string[] $allowed
     * @throws RuntimeException
     */
    private function assertOnlyKnownValues(array $allowed)
    {
        $allowedSql = implode(',', array_map(function ($value) {
            return DB::getPdo()->quote(strtolower($value));
        }, $allowed));

        $unknown = DB::table('customers')
            ->whereNotNull('type')
            ->whereRaw("LOWER(TRIM(type)) NOT IN ($allowedSql)")
            ->count();

        if ($unknown > 0) {
            throw new \RuntimeException(
                "customers.type tiene $unknown valor(es) desconocido(s); revisar antes de migrar."
            );
        }
    }

    /**
     * @return object|null fila de information_schema para customers.type
     */
    private function typeColumn()
    {
        return DB::selectOne(
            "SELECT IS_NULLABLE, COLUMN_DEFAULT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'customers'
                AND COLUMN_NAME = 'type'"
        );
    }

    /**
     * Devuelve el default traducido con el mapa. Si el default ya está en el valor de destino
     * (por una corrida anterior que falló a mitad de camino), se conserva tal cual.
     */
    private function mappedDefault($column, array $map)
    {
        if (!$column || $column->COLUMN_DEFAULT === null) {
            return null;
        }

        $current = strtolower(trim($column->COLUMN_DEFAULT, "'"));

        if (isset($map[$current])) {
            return $map[$current];
        }

        return in_array($current, array_values($map), true) ? $current : null;
    }

    /**
     * Reemplaza la columna manteniendo la nulabilidad que tenía.
     *
     * @param string $definition tipo SQL completo, por ejemplo VARCHAR(20) o CHAR(191)
     */
    private function modifyTypeColumn($column, $definition, $default)
    {
        $nullable = $column && $column->IS_NULLABLE === 'YES';

        $sql = "ALTER TABLE customers MODIFY type $definition " . ($nullable ? 'NULL' : 'NOT NULL');

        if ($default !== null) {
            $sql .= " DEFAULT '$default'";
        }

        DB::statement($sql);
    }
}
