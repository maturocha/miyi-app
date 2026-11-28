# Totales del pedido: `total_bruto` saturado y auditoría

## El problema

`orders.total_bruto` y `orders.delivery_cost` eran `DOUBLE(8,2)` (máximo 999999.99). La conexión corre con `'strict' => false`, así que MySQL/MariaDB **recortaban en silencio** cualquier valor mayor. Ejemplo: pedido 73360, líneas por 1124556.81 y `total_bruto` guardado 999999.99. `orders.total` es `DOUBLE` sin tope y no está afectado.

Además, en 2025 hay pedidos con `total_bruto` de más (en muchos, el doble de las líneas). `total` está bien en esos casos.

## Qué cambia

| Pieza | Qué hace | ¿Corre sola en el deploy? |
|---|---|---|
| Migración `2026_11_28_100000_widen_orders_total_bruto_and_delivery_cost` | `total_bruto` y `delivery_cost` pasan a `DOUBLE` sin precisión (como `total`). Solo esquema. Idempotente. `down()` no achica a propósito. | **Sí** (`php artisan migrate`) |
| `php artisan orders:backfill-total-bruto [--dry-run]` | `total_bruto = ROUND(SUM(price_final), 2)` en pedidos **desde 2025-01-01**, **con líneas**, cuyo `total_bruto` difiere de las líneas en más de 0.02 (incluye los saturados). | **No**: manual |
| `php artisan orders:audit-totals [--output=ruta.csv]` | **Solo lectura.** CSV de pedidos desde 2025 cuyo `total` no cierra. No corrige nada. | **No**: manual |

Además, `Order` castea `total_bruto` y `delivery_cost` a `float`, así que viajan como número en el JSON con cualquier driver.

El backfill:
- **no** toca `total`, ni cargos o asientos de cuenta corriente, ni saldos, ni `updated_at`. Hace un UPDATE directo de una columna, sin observers ni `recalculateTotals()`.
- **no** toca pedidos anteriores a 2025 (lógica histórica) ni pedidos sin líneas (no hay de dónde sacar el valor).
- se niega a escribir si la columna sigue siendo `DOUBLE(8,2)`: volvería a recortar.
- es idempotente: una segunda corrida no encuentra nada.

### Por qué el backfill es un comando y no parte de la migración

La migración corre sola en cada deploy. La corrección de datos conviene **verla antes** (`--dry-run`), elegir cuándo correrla y poder repetirla sin riesgo. Además depende de que el esquema ya esté ensanchado, y el comando lo verifica.

## Runbook para producción (requiere aprobación del dueño)

1. Antes del deploy, correr las consultas de solo lectura de abajo y guardar los números.
2. Deploy con `php artisan migrate`. El `ALTER TABLE orders` reconstruye la tabla y bloquea escrituras en `orders` unos segundos: correrlo fuera del horario de carga.
3. `php artisan orders:backfill-total-bruto --dry-run`: revisar que la cantidad coincida con la consulta 2.
4. `php artisan orders:backfill-total-bruto`.
5. `php artisan orders:audit-totals --output=/tmp/orders-totals.csv`: revisar a mano los descuadres (no se corrigen automáticamente: el total mueve plata).

## Consultas de solo lectura (MySQL/MariaDB)

```sql
-- 1) Tipo de las columnas (antes: double(8,2); después: double)
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
   AND COLUMN_NAME IN ('total', 'total_bruto', 'delivery_cost');

-- 2) Saturados por año (los < 2025 no se tocan)
SELECT YEAR(date) AS anio, COUNT(*) FROM orders
 WHERE total_bruto >= 999999.99 GROUP BY anio;

-- 3) Lo que el backfill corregiría (= --dry-run)
SELECT o.id, o.date, o.total_bruto, ROUND(d.lines_sum, 2) AS suma_lineas
  FROM orders o
  JOIN (SELECT id_order, SUM(price_final) AS lines_sum FROM order_details GROUP BY id_order) d
    ON d.id_order = o.id
 WHERE o.date >= '2025-01-01'
   AND ABS(o.total_bruto - ROUND(d.lines_sum, 2)) > 0.02
 ORDER BY o.id;

-- 4) Totales descuadrados (= orders:audit-totals), con cargo y reparto cerrado
SELECT o.id, o.date, o.status, o.total,
       ROUND(COALESCE(d.lines_sum, 0), 2) AS suma_lineas, o.discount, o.delivery_cost,
       ROUND(COALESCE(d.lines_sum, 0) + o.delivery_cost - COALESCE(d.lines_sum, 0) * o.discount / 100, 2) AS total_esperado,
       (SELECT COUNT(*) FROM account_entries ae
         WHERE ae.source_type = 'orders' AND ae.source_id = o.id AND ae.type = 'charge') AS cargos_cc,
       EXISTS (SELECT 1 FROM delivery_orders dord JOIN deliveries dl ON dl.id = dord.delivery_id
                WHERE dord.order_id = o.id AND dord.delivery_status = 'delivered' AND dl.status = 'closed') AS reparto_cerrado
  FROM orders o
  LEFT JOIN (SELECT id_order, SUM(price_final) AS lines_sum FROM order_details GROUP BY id_order) d
    ON d.id_order = o.id
 WHERE o.date >= '2025-01-01'
   AND ABS(o.total - ROUND(COALESCE(d.lines_sum, 0) + o.delivery_cost - COALESCE(d.lines_sum, 0) * o.discount / 100, 2)) > 0.02
 ORDER BY o.id;
```

## Resultado en la base local (copia, 2026-11-28)

- 141 saturados (1 de 2024, 46 de 2025, 94 de 2026). El de 2024 queda como está.
- El backfill corrigió 240 pedidos (140 saturados + 100 con `total_bruto` de más). Una segunda corrida: 0.
- Pedido 73360: `total_bruto` 999999.99 → 1124556.81.
- `total`, `account_entries` y `customers.current_balance`: idénticos antes y después (checksums).
- 39 pedidos con `total` descuadrado: ninguno con cargo en cuenta corriente ni en reparto cerrado. No se corrigieron.
