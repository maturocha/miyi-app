# Ledger de repartos (cuenta corriente)

Este documento describe cómo los repartos generan movimientos en la cuenta corriente de los clientes (`account_entries`).

## Dominio

```
Delivery (reparto)
  └── delivery_orders (pivot: estado de entrega, cobro en ruta)
        └── Order (pedido)
              └── account_entries (cargo por pedido, cobro por delivery_order)
```

Archivos principales:

- `app/Observers/DeliveryObserver.php` — dispara ledger al cambiar `status` del reparto
- `app/Services/DeliveryLedgerService.php` — crea cargos y cobros
- `app/Observers/AccountEntryObserver.php` — actualiza `customers.current_balance` al validar
- `app/Helpers/AccountEntrySourceHelper.php` — resuelve `delivery_id` y `delivery_date` para la UI (label/link los arma el frontend)

## Estados del reparto

```
not_started → in_progress → finished → closed
```

| Transición | Acción en pedidos | Acción en ledger |
|---|---|---|
| `in_progress` | `orders.status` → `out_for_delivery` | — |
| `finished` | — | Crea **cargos** (deuda) por pedidos entregados |
| `closed` | — | Crea **cobros** + valida movimientos pendientes |

## Cuándo se crea cada movimiento

| Momento | Movimiento | `type` | `source_type` | `source_id` | `validation_status` inicial | Impacto en saldo |
|---|---|---|---|---|---|---|
| `finished` | Cargo (deuda) | `charge` | `orders` | `order.id` | `pending` | Al validar |
| `closed` | Cobro | `payment` | `delivery_orders` | `delivery_order.id` | `not_validated` | Al validar |
| `closed` | Validación batch | — | varios | — | → `validated` | Sí (`AccountEntryObserver`) |

**El saldo del cliente solo cambia cuando `validation_status` pasa a `validated`.**

Al cerrar el reparto, `validateAllPendingEntriesForClosedDelivery()` valida en bloque cargos (`pending`) y cobros (`not_validated`) ligados al reparto.

### Origen en la UI (Movimientos / Clientes)

El API envía datos crudos; el frontend (`models/AccountEntry.ts` → `getSourceDisplay`) arma label y link:

| `source_type` | Campos API | Label / link (frontend) |
|---|---|---|
| `orders` | `source_id` | `Pedido #ID` → `/pedidos/{id}` |
| `delivery` | `delivery_id`, `delivery_date` | `Reparto #ID` → `/repartos/{id}` |
| `delivery_orders` | `delivery_id`, `delivery_date` (vía pivot) | `Reparto #ID` → `/repartos/{id}` |
| `manual` | — | `Manual` |

## Reglas de negocio — cargos (deuda)

### 1. Solo pedidos entregados en ese reparto

Al pasar a `finished`, solo se crea cargo si el pivot `delivery_orders.delivery_status = delivered`.

No generan cargo: `failed`, `untouched`, `skipped`.

### 2. Un cargo máximo por pedido (anti-duplicado)

El cargo se identifica por `source_type = orders` + `source_id = order_id`. Es **global**: no depende del reparto.

Si el pedido ya tiene cargo (p. ej. se entregó en otro reparto, o quedó un cargo erróneo de un finish anterior), **no se crea otro**.

### 3. Pedidos en múltiples repartos

Un pedido puede figurar en dos repartos si en uno de ellos el pivot quedó `failed` (reasignación tras fallo de entrega).

- El cargo lo genera el reparto donde el pedido está `delivered` y aún no existe cargo global.
- El pivot `failed` en el reparto original no bloquea ni genera deuda en ese reparto.

Validación al agregar pedidos: `DeliveryService::assertOrderNotAssignedElsewhere()` — bloquea si el pedido está en otro reparto con pivot distinto de `failed`.

## Reglas de negocio — cobros

Al pasar a `closed`:

- Se crea un cobro por cada `delivery_order` con `collected_amount > 0`.
- Idempotencia por `source_type = delivery_orders` + `source_id = delivery_order.id`.
- Si hay filas en `delivery_order_payments`, se copian a `account_entry_payment_methods`.

## Bug corregido: guard global en el observer

**Problema anterior:** `DeliveryObserver` usaba `exists()` sobre todos los pedidos del reparto. Si **un solo** pedido ya tenía cargo, se omitía `createChargesForFinishedDelivery()` para **todo** el reparto.

**Solución:** el observer siempre invoca al service; la idempotencia y el filtro `delivered` están en `DeliveryLedgerService`.

## Troubleshooting — reparto #129

### Síntoma

Reparto cerrado con cobros cargados pero sin deudas de la mayoría de los pedidos.

### Causa

1. El pedido **69503** figuraba en el reparto #129 (`failed`) y en el #134 (`delivered`).
2. El reparto #134 finalizó primero (2026-06-05 14:53:42) y creó el cargo del 69503.
3. Al finalizar el #129 (14:59:23), el guard `alreadyHasCharges` detectó ese cargo y **no creó cargos para los otros 30 pedidos**.
4. Al cerrar el #129 (15:06:29), los 27 cobros sí se crearon y validaron.

### Caso cliente 113 / pedido 69528

- Entregado en ruta, `collected_amount = 0` → sin cobro (correcto).
- Sin cargo → deuda no registrada ($493.686,80 faltantes).
- Corregido por migración `2026_06_10_100000_backfill_missing_charges_delivery_129`.

### Queries de diagnóstico

Pedidos `delivered` de un reparto sin cargo:

```sql
SELECT do.order_id, o.id_customer, o.total
FROM delivery_orders do
JOIN orders o ON o.id = do.order_id
JOIN deliveries d ON d.id = do.delivery_id
WHERE do.delivery_id = :delivery_id
  AND do.delivery_status = 'delivered'
  AND NOT EXISTS (
    SELECT 1 FROM account_entries ae
    WHERE ae.source_type = 'orders' AND ae.source_id = do.order_id
  );
```

Pedidos en más de un reparto:

```sql
SELECT order_id, COUNT(*) AS reparto_count
FROM delivery_orders
GROUP BY order_id
HAVING reparto_count > 1;
```

Cobros sin cargo correspondiente en un reparto cerrado:

```sql
SELECT ae.id, ae.customer_id, ae.amount, ae.source_id AS delivery_order_id
FROM account_entries ae
JOIN delivery_orders do ON do.id = ae.source_id
WHERE ae.source_type = 'delivery_orders'
  AND do.delivery_id = :delivery_id
  AND NOT EXISTS (
    SELECT 1 FROM account_entries ae2
    WHERE ae2.source_type = 'orders' AND ae2.source_id = do.order_id
  );
```
