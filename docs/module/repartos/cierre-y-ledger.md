# Repartos — finalizar, revisión, cierre y ledger

Cómo un reparto genera y valida movimientos en la cuenta corriente (`account_entries`) de los clientes.

Última revisión: 2026-09-27 (basado en el código actual). Reemplaza a `docs/delivery-ledger.md`.

## Regla de oro

**El saldo del cliente (`customers.current_balance`) solo cambia cuando un movimiento pasa a `validated`.** Lo aplica `AccountEntryObserver` con un `UPDATE ... current_balance + delta` atómico (`UpdateCustomerBalanceService`). Débito suma, crédito resta. Borrar un movimiento validado revierte su efecto.

## Qué movimiento se crea y cuándo

| Momento | Movimiento | `type` / `direction` | `source_type` | `source_id` | Estado inicial |
|---|---|---|---|---|---|
| Cobro extra durante la ruta | Cobro | `payment` / `credit` | `delivery` | `delivery.id` | `pending` |
| **Finalizar** | Cargo (deuda) por pedido entregado | `charge` / `debit` | `orders` | `order.id` | `pending` |
| **Cerrar** | Cobro por pedido con `collected_amount > 0` | `payment` / `credit` | `delivery_orders` | `delivery_order.id` | `not_validated` → `validated` en el mismo cierre |

Las líneas de pago (`delivery_order_payments`) se copian a `account_entry_payment_methods`. Si no hay líneas (datos legacy), se crea una sola con `delivery_orders.payment_method` (o `cash`).

## Finalizar

`POST /deliveries/{id}/finish` → lock del reparto → `status = finished` → `createChargesForFinishedDelivery`:

- Solo pedidos con `delivery_orders.delivery_status = delivered`.
- **Un cargo por pedido, global**: si el pedido ya tiene un cargo `orders` (de cualquier reparto), no se crea otro.
- Cada pedido se bloquea (`lockForUpdate`) antes de chequear y crear, y se usa **esa** fila para el monto. Esto evita duplicados entre repartos concurrentes y cargos con un total viejo.
- Monto = `orders.total` al momento de finalizar. Fecha = `finished_at`.

## Revisión (reparto `finished`)

Entre finalizar y cerrar, los privilegiados pueden corregir:

| Corrección | Efecto en ledger |
|---|---|
| Editar el pedido (líneas, descuento, envío) | El cargo `pending` se ajusta **en el momento** al nuevo total (`Order::syncPendingCharge`) |
| Pedido entregado → fallido | Su cargo queda `pending` y se **descarta al cerrar** |
| Pedido fallido → entregado | No tiene cargo; se **crea al cerrar** |
| Cambiar cobros | Se toman al cerrar |

## Cerrar

`POST /deliveries/{id}/close` → lock del reparto → rechaza si no está `finished` → `status = closed`. `DeliveryObserver` ejecuta, **en la misma transacción y en este orden**:

| # | Paso | Método |
|---|---|---|
| 1 | Crear cargos faltantes de pedidos entregados (idempotente) | `createChargesForFinishedDelivery` |
| 2 | Alinear cargos `pending` con el total actual del pedido | `syncPendingChargesForClosedDelivery` |
| 3 | Crear cobros por `delivery_order` con `collected_amount > 0` (idempotente) | `createPaymentsForClosedDelivery` |
| 4 | Validar: cobros extra del reparto, cobros de sus `delivery_orders` y cargos de pedidos **entregados en este reparto** | `validateAllPendingEntriesForClosedDelivery` |
| 5 | Borrar cargos `pending` de pedidos que en este reparto **no** quedaron entregados (salvo que estén entregados en otro reparto) | `discardPendingChargesForUndeliveredOrders` |

Después del cierre:
- No se pueden operar sus pedidos (policy + chequeo con lock en el servicio).
- Los pedidos entregados en él **no se pueden editar ni borrar** ([reglas](../orders/reglas-con-reparto.md)). Las correcciones se hacen con nota de crédito o débito manual.
- No se aceptan cobros extra con ese `delivery_id` (`422`).

## Cobrado del reparto

Misma fórmula en el detalle (`Delivery::collectedByPaymentMethod`) y en el listado (SQL en `DeliveriesController::paginatedQuery`):

```
cobrado = Σ delivery_order_payments.amount (> 0)
        + Σ delivery_orders.collected_amount de filas SIN líneas y con payment_method (legacy)
        + Σ líneas de cobros extra (account_entries source_type='delivery', type='payment')
neto    = cobrado − expenses_amount
```

## Pedidos en varios repartos

Un pedido puede estar en dos repartos si en uno quedó `failed`. El cargo lo genera el reparto donde quedó `delivered`. El `failed` no genera deuda ni bloquea.

## Troubleshooting

### Reparto #129 (histórico, junio 2026)

Un guard viejo del observer omitía los cargos de **todo** el reparto si **un** pedido ya tenía cargo (el 69503 estaba `failed` en #129 y `delivered` en #134). Se corrigió: la idempotencia es por pedido, en `DeliveryLedgerService`. Los cargos faltantes se cargaron con la migración `2026_06_10_100000_backfill_missing_charges_delivery_129`.

### Auditoría 27/09/2026 (copia de producción)

Anomalías encontradas, detalle en `miyi/docs/auditoria-cuenta-corriente-2026-09-27.xlsx` (fuera de los repos; contiene datos de clientes):

| Caso | Causa | Estado |
|---|---|---|
| Cargos validados de pedidos no entregados (5) | Primeras semanas del ledger | Prevenido (paso 4 filtra entregados) |
| Cargos de pedidos borrados (7) | Se podía borrar un pedido con cargo | Prevenido (reglas de borrado) |
| Pedidos editados después del cargo sin ajuste (37) | El cargo no seguía el total | Prevenido (sync del cargo + bloqueo tras cierre) |

### Queries de diagnóstico

Pedidos entregados de un reparto sin cargo:

```sql
SELECT do.order_id, o.id_customer, o.total
FROM delivery_orders do
JOIN orders o ON o.id = do.order_id
WHERE do.delivery_id = :delivery_id
  AND do.delivery_status = 'delivered'
  AND NOT EXISTS (SELECT 1 FROM account_entries ae
                  WHERE ae.source_type = 'orders' AND ae.source_id = do.order_id);
```

Cargos validados que no coinciden con el total actual del pedido:

```sql
SELECT ae.id, ae.source_id AS order_id, ae.amount, o.total, o.total - ae.amount AS diferencia
FROM account_entries ae
JOIN orders o ON o.id = ae.source_id
WHERE ae.source_type = 'orders' AND ae.validation_status = 'validated'
  AND ABS(ae.amount - o.total) > 0.01;
```

Cargos de pedidos que ya no existen:

```sql
SELECT ae.* FROM account_entries ae
LEFT JOIN orders o ON o.id = ae.source_id
WHERE ae.source_type = 'orders' AND o.id IS NULL;
```

Saldo del cliente contra el ledger (debería dar 0 filas):

```sql
SELECT c.id, c.current_balance,
       COALESCE(SUM(CASE WHEN ae.direction = 'debit' THEN ae.amount ELSE -ae.amount END), 0) AS ledger
FROM customers c
LEFT JOIN account_entries ae ON ae.customer_id = c.id AND ae.validation_status = 'validated'
GROUP BY c.id, c.current_balance
HAVING ABS(c.current_balance - ledger) > 0.01;
```

## Cómo lo ve el front

El API envía datos crudos; `models/AccountEntry.ts` (`getSourceDisplay`) arma label y link:

| `source_type` | Label / link |
|---|---|
| `orders` | `Pedido #ID` → `/pedidos/{id}` |
| `delivery`, `delivery_orders` | `Reparto #ID` → `/repartos/{id}` (usa `delivery_id`, que resuelve `AccountEntrySourceHelper` precargando en lote) |
| `manual` | `Manual` |
