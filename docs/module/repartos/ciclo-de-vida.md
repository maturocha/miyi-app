# Repartos — ciclo de vida

Estados del reparto, cómo se pasa de uno a otro y qué cambia en los pedidos en cada transición.

Última revisión: 2026-09-27 (basado en el código actual).

## Estados

```
not_started ──start──▶ in_progress ──finish──▶ finished ──close──▶ closed
     │                                              │
     └──────────── delete (cualquiera menos closed) ┘
```

| Estado | Significado | Se puede editar el reparto | Se pueden operar pedidos |
|---|---|---|---|
| `not_started` | Armado, sin salir | Sí (datos y pedidos) | No* |
| `in_progress` | En ruta | No | Sí (encargado o privilegiados) |
| `finished` | Terminó la ruta; etapa de **revisión** | No | Sí (solo privilegiados) |
| `closed` | Contabilizado en cuenta corriente | No | **No** |

\* La policy de operar no chequea `not_started`; la UI solo permite operar en curso.

## Transiciones

Todas pasan por `DeliveryService` y cambian `deliveries.status`. `DeliveryObserver::updated` reacciona solo si cambió `status`.

| Transición | Endpoint | Reglas del servicio | Efecto en pedidos | Efecto en ledger |
|---|---|---|---|---|
| → `in_progress` | `POST /start` | Rechaza si está `finished` o `closed` | Todos los pedidos del reparto → `out_for_delivery` | — |
| → `finished` | `POST /finish` | Lock del reparto; rechaza si `closed`; no-op si ya `finished` | — | Crea **cargos** `pending` de los pedidos entregados |
| → `closed` | `POST /close` | Lock del reparto; solo desde `finished` | — | Pipeline de cierre (ver [cierre-y-ledger.md](cierre-y-ledger.md)) |

- La edición del reparto (`PUT /deliveries/{id}`) **no** cambia el estado: `DeliveriesController@update` descarta `status` del payload.
- `DeliveryUpdateRequest` además valida que un `status` enviado respete `not_started → in_progress → finished → closed`.
- Los errores de regla se devuelven como `400` con `{ success: false, message }`.

### Por qué hay locks

`finishDelivery` y `closeDelivery` releen el reparto con `lockForUpdate()` dentro de la transacción y verifican el estado sobre esa fila. Así, un doble tap o dos usuarios a la vez no generan cargos duplicados ni validan dos veces. `updateDeliveryOrder` toma el mismo lock: una edición que llega mientras se cierra espera y después ve el reparto `closed`.

Orden de locks en todo el módulo: **reparto → pedido**. Mantenerlo evita deadlocks.

## Pedidos: estados automáticos

`orders.status` se modifica automáticamente desde repartos:

| Evento | `orders.status` |
|---|---|
| Se agrega al reparto (si estaba `ready_to_ship`) | `assigned_to_delivery` |
| Se quita del reparto (si estaba `assigned_to_delivery`) | `ready_to_ship` |
| Inicio del reparto | `out_for_delivery` (todos) |
| Operar: `delivered` | `delivered` |
| Operar: `failed` | `failed` |
| Se borra el reparto | `ready_to_ship` (todos) |

## Borrar un reparto

`DELETE /deliveries/{id}` (solo admin). `DeliveryObserver::deleting`:

1. Rechaza si está `closed`.
2. Pone **todos** sus pedidos en `ready_to_ship`, incluidos los entregados.
3. Desasocia los pedidos.

⚠️ Si el reparto estaba `finished`, sus cargos `pending` quedan sin reparto que los valide. Ver riesgos en [index.md](index.md#riesgos--deuda-conocida).
