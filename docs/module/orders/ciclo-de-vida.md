# Pedidos — ciclo de vida

Estados del pedido (`orders.status`) y qué los modifica.

Última revisión: 2026-09-27 (basado en el código actual).

## Estados (`App\Models\Enums\OrderStatus`)

| Valor | Significado | Cómo se llega |
|---|---|---|
| `in_process` | Armándose (default; el resource lo asume si es null) | Alta |
| `in_preparation` | En preparación en depósito | Manual |
| `ready_to_ship` | Listo para repartir | Manual, o al salir de un reparto |
| `assigned_to_delivery` | Asignado a un reparto que no salió | Automático al agregarlo a un reparto |
| `out_for_delivery` | En ruta | Automático al iniciar el reparto |
| `delivered` | Entregado | Automático al operar el pedido como entregado |
| `failed` | Entrega fallida | Automático al operarlo como fallido |
| `cancelled` | Cancelado | Manual |

## Flujo típico

```
in_process → in_preparation → ready_to_ship ─(agregar a reparto)→ assigned_to_delivery
                                   ▲                                      │ iniciar reparto
                                   │ quitar del reparto / borrar reparto  ▼
                                   └──────────────────────────── out_for_delivery
                                                                   │ operar
                                                       ┌───────────┴───────────┐
                                                   delivered                 failed ─(re-asignable)
```

## Cambios automáticos desde repartos

| Evento | Condición | Nuevo estado |
|---|---|---|
| Agregar a un reparto (`attachOrders`, `syncOrders`, `addOrder`, `add-pending-orders`) | Estaba `ready_to_ship` | `assigned_to_delivery` |
| Quitar del reparto (`syncOrders`) | Estaba `assigned_to_delivery` | `ready_to_ship` |
| Iniciar reparto | Todos los pedidos del reparto | `out_for_delivery` |
| Operar pedido | `delivered` / `failed` | `delivered` / `failed` |
| Borrar reparto | Todos los pedidos del reparto | `ready_to_ship` ⚠️ incluso los entregados |

Pedidos que se pueden asignar a un reparto: al crearlo, `ready_to_ship` o `failed`; al editarlo, también `assigned_to_delivery`.

## Cambios manuales

- `PUT /orders/{id}/status { status }`: cualquier estado válido. Rechaza (`422`) si el pedido está bloqueado (entregado en reparto cerrado).
- `PUT /orders/bulk-status { order_ids, status }`: solo admin, ventas y administración. **Omite** los bloqueados y los informa en `skipped_ids` y en el mensaje.
- `PUT /orders/{id}` con `status`: el form de edición lo envía al confirmar.

⚠️ No hay una máquina de estados: el cambio manual permite cualquier salto (por ejemplo, pasar a `delivered` un pedido que nunca estuvo en un reparto). Eso **no** genera cargo en la cuenta corriente: los cargos solo nacen de repartos.
