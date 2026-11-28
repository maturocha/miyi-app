# Totales del pedido: `total_bruto` saturado y auditoría

## El problema

`orders.total_bruto` y `orders.delivery_cost` eran `DOUBLE(8,2)` (máximo 999999.99). La conexión corre con `'strict' => false`, así que MySQL/MariaDB **recortaban en silencio** cualquier valor mayor. Ejemplo: pedido 73360, líneas por 1124556.81 y `total_bruto` guardado 999999.99. `orders.total` es `DOUBLE` sin tope y no está afectado.

Además, en 2025 hay pedidos con `total_bruto` de más (en muchos, el doble de las líneas). `total` está bien en esos casos.

## Qué cambia

| Pieza | Qué hace | ¿Corre sola en el deploy? |
|---|---|---|
| Migración `2026_11_28_100000_widen_orders_total_bruto_and_delivery_cost` | `total_bruto` y `delivery_cost` pasan a `DOUBLE` sin precisión (como `total`). Solo esquema. Idempotente. `down()` no achica a propósito. | **Sí** |
| Migración `2026_11_28_100001_backfill_orders_total_bruto` | `total_bruto = ROUND(SUM(price_final), 2)` en pedidos **desde 2025-01-01**, **con líneas**, cuyo `total_bruto` difiere de las líneas en más de 0.02 (incluye los saturados). | **Sí** |

Además, `Order` castea `total_bruto` y `delivery_cost` a `float`, así que viajan como número en el JSON con cualquier driver.

La migración de backfill:
- **no** toca `total`, ni cargos o asientos de cuenta corriente, ni saldos, ni `updated_at`. Usa query builder (`DB::table`): sin observers, sin timestamps, sin `recalculateTotals()` ni `syncPendingCharge()`.
- **no** toca pedidos anteriores a 2025 (lógica histórica) ni pedidos sin líneas (no hay de dónde sacar el valor).
- es **un único `UPDATE`** con subconsulta correlacionada (la suma de líneas de cada pedido), portable a MySQL/MariaDB y SQLite. Es atómico: corrige todo o nada. Usa el índice `order_details.id_order`. Los pedidos sin líneas quedan afuera porque la subconsulta da `NULL`. No lleva un `EXISTS` aparte porque en MariaDB se planifica como semi-join materializado y es mucho más lento.
- falla antes de escribir si la columna sigue en `DOUBLE(8,2)`, porque volvería a recortar. Corre después del ensanche por orden de nombre.
- es idempotente: una segunda corrida corrige 0.
- tiene `down()` no-op: no hay forma segura de revertir (el valor anterior era el dato recortado o incorrecto).

## Deploy (requiere aprobación del dueño)

`php artisan migrate` corre **las dos migraciones juntas**.

Tiempo medido en una copia de la base de prod (67484 pedidos, MariaDB 10.5):
- Ensanche: **3-5 ms**. En MariaDB 10.5 es un cambio de metadatos (mismo almacenamiento de 8 bytes), sin reconstruir la tabla. Si el motor de prod no pudiera hacerlo en el lugar, con copia forzada (`ALGORITHM=COPY`) tardó **0.46 s**, bloqueando escrituras en `orders` ese tiempo.
- Backfill: **~120 ms** para 249 pedidos.

Pasos:
1. Antes del deploy, correr las consultas 1 a 3 de abajo y guardar los números. La 3 es exactamente lo que va a corregir el backfill.
2. Deploy con `php artisan migrate`. La salida imprime `Backfill total_bruto: N pedido(s) corregido(s).` y N debería coincidir con la consulta 3.
3. Repetir las consultas 2 y 3: la 3 debe dar 0 filas y la 2 solo pedidos anteriores a 2025.
4. Correr la consulta 4 (solo lectura) y revisar a mano los pedidos con `total` descuadrado. No se corrigen automáticamente: el total mueve plata.

## Consultas de solo lectura (MySQL/MariaDB)

```sql
-- 1) Tipo de las columnas (antes: double(8,2); después: double)
SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
   AND COLUMN_NAME IN ('total', 'total_bruto', 'delivery_cost');

-- 2) Saturados por año (los < 2025 no se tocan)
SELECT YEAR(date) AS anio, COUNT(*) FROM orders
 WHERE total_bruto >= 999999.99 GROUP BY anio;

-- 3) Lo que la migración de backfill va a corregir
SELECT o.id, o.date, o.total_bruto, ROUND(d.lines_sum, 2) AS suma_lineas
  FROM orders o
  JOIN (SELECT id_order, SUM(price_final) AS lines_sum FROM order_details GROUP BY id_order) d
    ON d.id_order = o.id
 WHERE o.date >= '2025-01-01'
   AND ABS(o.total_bruto - ROUND(d.lines_sum, 2)) > 0.02
 ORDER BY o.id;

-- 4) Totales descuadrados (desde 2025), con cargo en cuenta corriente y reparto cerrado. Solo lectura: se revisan a mano.
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

## Resultado en una copia de la base de prod (2026-11-28)

Copia temporal de la base (columna `DOUBLE(8,2)`, 150 saturados: 1 de 2024, 46 de 2025 y 103 de 2026), con `php artisan migrate`:
- El backfill corrigió **249 pedidos** (149 saturados de 2025 en adelante y 100 de 2025 con `total_bruto` de más, en muchos el doble). Volver a correrla corrige 0.
- Queda 1 saturado, de 2024-10-28, sin tocar por regla.
- Pedido 73360: `total_bruto` 999999.99 → 1124556.81.
- Checksums de `orders.total`/`updated_at`, `account_entries` y `customers.current_balance`: **idénticos** antes y después.
- 39 pedidos con `total` descuadrado (consulta 4): ninguno con cargo en cuenta corriente ni en reparto cerrado. No se corrigieron.
