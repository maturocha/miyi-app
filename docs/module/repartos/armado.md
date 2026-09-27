# Repartos — armado

Crear y editar un reparto, agregar o quitar pedidos y cómo se ordenan las paradas.

Última revisión: 2026-09-27 (basado en el código actual).

## Crear

`POST /deliveries` → `DeliveryService::createDelivery` (en transacción).

| Campo | Regla (`DeliveryStoreRequest`) |
|---|---|
| `delivery_date` | Requerido, hoy o futuro |
| `owner_user_id` | Requerido, usuario existente (encargado) |
| `notes` | Opcional |
| `orders[]` | Requerido. `orders.*.id` debe existir con estado `ready_to_ship` o `failed`. `orders.*.sequence` opcional |

Acepta también `order_ids[]` (payload viejo, solo IDs).

Pasos:
1. Crea el reparto en `not_started`.
2. `attachOrders`: por cada pedido verifica que no esté en otro reparto (ver abajo), lo asocia y pasa los `ready_to_ship` a `assigned_to_delivery`.
3. Ordena las paradas por zona (`reorderDeliveryOrdersByZone`).

## Editar

`PUT /deliveries/{id}` — solo en `not_started` (policy `update`).

- Actualiza `delivery_date`, `owner_user_id`, `notes`, `expenses_*`. Ignora `status`.
- Si viene `orders[]`, `syncOrders` deja el reparto **exactamente** con esos pedidos:
  - Los que ya no están se desasocian; si estaban `assigned_to_delivery` vuelven a `ready_to_ship`.
  - Los nuevos se validan contra otros repartos y pasan a `assigned_to_delivery`.
  - Se respeta el orden recibido; los nuevos se ubican dentro del grupo de su zona.
- Datos + pedidos van en **una sola transacción**. Si un pedido está en otro reparto, responde `422` y no queda nada a medias.

## Agregar pedidos a un reparto existente

| Endpoint | Qué hace | Reglas |
|---|---|---|
| `POST /deliveries/{id}/orders` `{ order_id, override? }` | Agrega uno | Reparto `not_started` (policy `addOrders`). **Nunca** un pedido ya entregado. Sin `override`, valida que no esté en otro reparto |
| `POST /deliveries/{id}/add-pending-orders` `{ zone_id, date }` | Agrega todos los `ready_to_ship` de esa zona y fecha | Idempotente: saltea los que ya están |

⚠️ `add-pending-orders` compara `orders.date = date`, y `orders.date` se guarda con hora (`Y-m-d H:i:s` en `OrdersController@store`). A confirmar si matchea en producción.

### Validación de pedido en otro reparto

`assertOrderNotAssignedElsewhere`: rechaza si el pedido tiene un `delivery_orders` en **otro** reparto con estado distinto de `failed`.

Consecuencias:
- Un pedido `failed` puede volver a repartirse.
- Un pedido `untouched` o `skipped` en un reparto **cerrado** sigue bloqueando la re-asignación (solo se puede con `override`). ⚠️ Probablemente no deseado.

## Orden de paradas (`sequence`)

`reorderDeliveryOrdersByZone` (en `DeliveryService`):

- Criterio canónico: zonas por nombre ascendente, sin zona al final; dentro de cada zona, por `orders.id`.
- Los pedidos que ya estaban conservan su orden relativo (respeta un orden manual).
- Los nuevos se insertan al final del grupo de su zona. Si la zona no existe en el reparto:
  - con orden canónico, el grupo se inserta en su lugar alfabético;
  - con orden manual, se agrega al final.
- Renumera `1..N` sin huecos y solo escribe las filas que cambian.

## Listado

`GET /deliveries` (`DeliveriesController::paginatedQuery`), 40 por página por defecto.

- Roles que no son admin (1) ni administración (4) ven solo los repartos donde son encargados.
- Filtros: `date_from`, `date_to`, `status`, `owner_user_id`, `search` (por id). ⚠️ Usan `has()`: un filtro enviado vacío no matchea nada (el front los limpia antes de enviar).
- `totals.collected` se calcula en SQL con **la misma fórmula que el detalle**: líneas de pago + fallback legacy (sin líneas y con método) + cobros extra. Ver [cierre-y-ledger.md](cierre-y-ledger.md#cobrado-del-reparto).
