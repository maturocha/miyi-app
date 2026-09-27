# Pedidos — reglas de edición y borrado según el reparto

Hasta cuándo se puede editar o borrar un pedido y cómo se mantiene alineada la cuenta corriente.

Última revisión: 2026-09-27 (regla definida por producto el 27/09/2026 e implementada en el código actual).

## Regla

| Situación del pedido | Editar | Borrar | Cuenta corriente |
|---|---|---|---|
| **No entregado** (nunca estuvo en reparto, o quedó `failed`/`untouched`/`skipped`) | ✅ Queda disponible para otro reparto | ✅ | Si tenía cargo, se borra (y si estaba validado se revierte el saldo) |
| **Entregado**, reparto `in_progress` o `finished` | ✅ | ❌ | El cargo `pending` sigue el total del pedido en cada edición |
| **Entregado en un reparto `closed`** | ❌ | ❌ | Corregir con nota de crédito o débito manual |

"Entregado" = tiene algún `delivery_orders.delivery_status = 'delivered'`.

## Implementación

En `app/Models/Order.php`:

| Método | Qué hace |
|---|---|
| `wasDelivered()` | ¿Entregado en algún reparto? |
| `isLockedByClosedDelivery()` | ¿Entregado en un reparto cerrado? |
| `Order::lockForEdit($id)` | `lockForUpdate` del pedido + `DomainException` si está bloqueado. Se llama dentro de una transacción |
| `syncPendingCharge()` | Ajusta el monto de los cargos `pending` del pedido a su `total`. Nunca toca un cargo validado |

Dónde se aplica:

| Endpoint | Chequeo |
|---|---|
| `PUT /orders/{id}` | `lockForEdit` → update → `recalculateTotals` (incluye sync del cargo) |
| `PUT /orders/{id}/status` | `lockForEdit` |
| `PUT /orders/bulk-status` | Omite los bloqueados (`skipped_ids`) |
| `POST/PUT/DELETE /details` | `lockForEdit` del pedido de la línea → cambio → recálculo |
| `DELETE /orders/{id}` | Reglas de borrado (abajo) |
| `POST /deliveries/{id}/orders` | Rechaza pedidos ya entregados, incluso con `override` |

Si está bloqueado, la API responde `422` con: *"El pedido fue entregado en un reparto cerrado: no se puede modificar. Corregilo con una nota de crédito o débito en la cuenta corriente."*

El front usa `is_locked` / `was_delivered` (en el listado y en `GET /orders/{id}`) para ocultar **Editar** y **Eliminar**, y muestra el aviso en la vista del pedido.

## Borrado (`DELETE /orders/{id}`)

Solo admin, ventas y administración. En una transacción:

1. Bloquea los repartos del pedido y después el pedido (mismo orden que el flujo de repartos, para evitar deadlocks).
2. Rechaza (`422`) si:
   - fue entregado → *"No se puede eliminar un pedido entregado."*;
   - tiene cobros registrados (`collected_amount > 0` o cobros `delivery_orders` en la cuenta corriente).
3. Borra sus cargos `orders` uno por uno (el observer revierte el saldo si estaban validados).
4. Borra líneas y cabecera. Sus `delivery_orders` y pagos se borran en cascada: el pedido sale de cualquier reparto donde no se entregó.

⚠️ Si se borra un pedido de un reparto en curso, la numeración de paradas (`sequence`) queda con un hueco.

## Cómo se ajusta la cuenta corriente

El cargo del pedido nace al **finalizar** el reparto, con el total de ese momento, en estado `pending`:

```
editar entre finalizar y cerrar ──▶ syncPendingCharge ajusta el monto del cargo pending
cerrar                          ──▶ se re-sincroniza y se valida con el total final
editar después de cerrar        ──▶ bloqueado: nota de crédito/débito
```

Ejemplo: pedido de $117.334, reparto finalizado (cargo `pending` $117.334). En la revisión se quita una línea: total $98.614 y cargo $98.614. Al cerrar, el cargo se valida por $98.614. Si el cliente había pagado $117.300, le queda un saldo a favor de $18.686.
