# Repartos — operar

Qué pasa cuando el encargado marca un pedido como entregado o fallido, registra cobros o gastos, o carga un cobro extra.

Última revisión: 2026-09-27 (basado en el código actual).

## Operar un pedido

`PUT /deliveries/{id}/orders/{orderId}` → `DeliveryService::updateDeliveryOrder`.

Permitido (policy `updateOrder`): reparto no `closed`; el encargado si está `in_progress`; roles privilegiados en cualquier estado no cerrado. Ver [permisos.md](permisos.md).

### Payload (`DeliveryOrderUpdateRequest`)

| Campo | Regla |
|---|---|
| `delivery_status` | Requerido: `delivered`, `failed`, `untouched`, `skipped` |
| `failure_reason` | Requerido si `failed` |
| `payments[]` | `{ payment_method, amount, payment_reference }`. Se pueden repetir métodos (FT + FT) |
| `collected_amount` | Legacy; se usa solo si no vienen `payments` con monto |
| `payment_method`, `payment_reference`, `observations` | Opcionales |

### Qué hace (en transacción, con lock del reparto)

1. Relee el reparto con lock. Si ya está `closed` → `422` (cubre la carrera con un cierre simultáneo).
2. `collected_amount` = suma de `payments` con monto > 0. Si no hay, usa `collected_amount` del request (o 0).
3. `payment_method` del pivot = el método si hay uno solo; si hay varios, el del request.
4. Si `delivered`, setea `delivered_at = now`.
5. **Reemplaza** las líneas de `delivery_order_payments` por las recibidas (solo monto > 0 y con método).
6. Actualiza `orders.status`: `delivered` → `delivered`, `failed` → `failed`.

⚠️ Es un reemplazo total: si no se envía `payments`, se borran las líneas y el cobrado queda en 0. Es consistente con el front actual (que siempre envía el estado completo), pero hay que tenerlo en cuenta en integraciones nuevas.

Operar **no** toca la cuenta corriente. Los cargos se generan al finalizar y los cobros al cerrar ([cierre-y-ledger.md](cierre-y-ledger.md)).

## Cobros extra (fuera del reparto)

Plata que el encargado cobra durante la ruta y no corresponde a un pedido del reparto (por ejemplo, deuda vieja de un cliente).

`POST /account-entries` con `delivery_id`:

| Regla | Detalle |
|---|---|
| Reparto | Debe existir y **no estar cerrado** (`422` si está `closed`) |
| Estado inicial | `pending` (no impacta el saldo) |
| Origen | `source_type = 'delivery'`, `source_id = delivery_id` |
| Líneas | `lines[]` `{ method, amount, reference }` en `account_entry_payment_methods` |
| Impacto | Se valida al **cerrar** el reparto |

Edición: `PATCH /account-entries/{id}`. Si se envían `lines`, se reemplazan en transacción. `AccountEntryPolicy::update` lo permite a roles privilegiados o al encargado del reparto, siempre que el movimiento no esté validado y el reparto no esté cerrado.

⚠️ No se valida que la suma de `lines` coincida con `amount`.

## Gastos

`PUT /deliveries/{id}/expenses` `{ amount, notes }` → guarda `expenses_amount` y `expenses_notes`.

Permitido al encargado con el reparto en curso, y a los privilegiados **en cualquier estado, incluso cerrado**. Los gastos **no** generan movimientos en cuenta corriente; solo afectan `totals.net = collected - expenses`.
