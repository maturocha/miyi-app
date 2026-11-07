# Inicio (home)

Resumen agregado para la página de Inicio del dashboard: una sola request devuelve todas las secciones que el rol del usuario puede ver.

Última revisión: 2026-11-07

## Endpoint

`GET /api/v1/home` (`auth:api` + `cors`), sin parámetros.

- Controller: `app/Http/Controllers/Api/V1/HomeController.php`.
- Lógica: `app/Services/HomeDashboardService.php`. Usa solo `DB::table` con agregados: no serializa modelos (el accessor `Delivery::totals` haría una query por fila).
- **Cache**: `home:v1:user:{id}`, TTL 60s (driver por defecto). La clave es por usuario porque pedidos y repartos tienen scope por usuario. Un pedido nuevo puede tardar hasta 1 minuto en aparecer en la home.
- **Fechas**: "hoy" y "mes actual" se calculan en `America/Argentina/Buenos_Aires`, porque `orders.date` (DATE) se guarda en hora AR y `APP_TIMEZONE=UTC`.
- **Costo**: unas 12 queries indexadas (unos 10–15ms de SQL con la base local).

## Secciones y permisos

Una sección que el rol no puede ver **no viene en la respuesta** (key ausente). El frontend renderiza solo lo que llega. Los gates replican los de cada módulo de origen.

| key | Roles | Origen del permiso |
|---|---|---|
| `meta` | todos | — |
| `sales` | admin, ventas, administración | `FinanceDashboardController` (Analytics) |
| `top_products` | admin, ventas, administración | ídem |
| `open_deliveries` | admin, administración: todos los repartos. Repartidor: solo los propios (`owner_user_id`) | `DeliveriesController::paginatedQuery` |
| `latest_orders` | todos. Admin y repartidor ven todos los pedidos; el resto, solo los propios (`orders.id_user`) | `OrdersController::paginatedQuery` |
| `low_stock` | admin, administración | `ProductResource` expone `min_stock` solo a estos roles |
| `debtors` | admin, administración | `AccountEntriesController@balanceSummary` (Cuenta Corriente) |
| `birthdays` | admin, ventas, administración | Acciones comerciales (#55). Solo si existe la columna `customers.birthday`. |

## Reglas

| Concepto | Regla |
|---|---|
| Venta válida | Todo pedido salvo `status = cancelled` (`status` NULL legacy cuenta). |
| `sales.month_total` | `SUM(orders.total)` del 1 del mes a hoy. |
| `sales.prev_period_*` | Mismo período del mes anterior: del 1 al mismo día, acotado al último día de ese mes (el 31/03 compara contra 01/02–28/02). |
| `sales.daily` | Serie diaria del mes (`total` y `orders`), con días sin ventas en 0, para los mini gráficos de los KPIs. |
| `top_products` | Top 5 del mes por `SUM(order_details.quantity)`, mismo orden que Analytics > Productos. Incluye `weight_sold` para los productos por peso (`type_product = 'w'`). |
| Reparto abierto | `deliveries.status <> 'closed'` (`not_started`, `in_progress`, `finished`). `finished` = terminó la ruta pero falta cerrarlo (recién al cerrar impacta en la cuenta corriente). |
| `open_deliveries.oldest_finished` | El reparto `finished` más antiguo (por `finished_at`), para el aviso "falta cerrarlo". |
| Bajo stock | `own_product = 1`, `min_stock > 0`, `stock <= min_stock` **y con ventas en los últimos 30 días**. Ordenado por unidades vendidas en esos 30 días. |
| Deudor | `customers.current_balance > 0` (positivo = debe). `total_debt` coincide con `account-entries/balance-summary` sin filtros. |
| Próximo cumpleaños | Clientes no borrados con `birthday` cuyo próximo cumpleaños cae entre hoy y 14 días (`days_until` 0..14). Hasta 8, ordenados por cercanía. El año se ajusta en SQL: si el día ya pasó este año, cuenta el del año que viene. El 29/02 en años no bisiestos cae el 28/02. |

Ejemplo de cumpleaños, con hoy = 07/11: 07/11 → `days_until` 0, 08/11 → 1, 21/11 → 14 (entra), 22/11 → 15 (no entra), 06/11 → 364 (no entra). Con hoy = 25/12, el 02/01 → 8.

**Guard de deploy**: si la migración de #55 (`2026_11_07_100000_add_birthday_to_customers_table`) todavía no corrió, `birthdays` no viene y el resto de la home funciona igual. Solo se cachea el resultado positivo (`home:schema:customers.birthday`, 24 h), así que la sección aparece apenas corre la migración (como mucho, después de los 60s del cache de la home).

Ejemplo de bajo stock: con la base local (28/10/2026), 2.619 de 2.822 productos tienen stock ≤ 0 porque no se trabajan. Sin el filtro de ventas recientes la sección listaba 2.671 productos; con el filtro, 40.

## Diferencias conocidas

- ⚠️ `/finance/dashboard` (Analytics) **no** excluye los pedidos cancelados y la home sí, así que "ventas del mes" puede no coincidir con Analytics. Queda pendiente decidir si Analytics debe excluirlos.
