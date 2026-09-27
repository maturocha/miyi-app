# Repartos (deliveries)

Un reparto agrupa pedidos que un encargado entrega en una ruta, registra lo cobrado y, al cerrarse, contabiliza todo en la cuenta corriente de los clientes.

Última revisión: 2026-09-27 (basado en el código actual).

## Contenido

| Documento | Qué cubre |
|---|---|
| [ciclo-de-vida.md](ciclo-de-vida.md) | Estados del reparto, transiciones, qué pasa con los pedidos en cada una |
| [armado.md](armado.md) | Crear/editar un reparto, agregar/quitar pedidos, orden de paradas por zona |
| [operar.md](operar.md) | Marcar pedidos entregados/fallidos, cobros por pedido, cobros extra, gastos |
| [cierre-y-ledger.md](cierre-y-ledger.md) | Finalizar, revisión, cerrar: cómo se generan y validan cargos y cobros |
| [permisos.md](permisos.md) | Quién puede hacer qué (policy + roles) |

Relacionado: [Pedidos](../orders/index.md) (reglas de edición/borrado según el reparto).

## Modelo de datos

```
deliveries (reparto)
  ├── delivery_orders (pivot: estado de entrega, cobro en ruta, secuencia)
  │     ├── delivery_order_payments (líneas de cobro por método)
  │     └── orders (pedido)
  └── account_entries source_type='delivery' (cobros extra "fuera del reparto")
```

| Tabla | Columnas clave |
|---|---|
| `deliveries` | `status`, `delivery_date`, `owner_user_id` (encargado), `started_at`, `finished_at`, `expenses_amount`, `expenses_notes`, `notes` |
| `delivery_orders` | `delivery_id`, `order_id` (único por reparto), `sequence`, `delivery_status`, `collected_amount`, `payment_method`, `payment_reference`, `observations`, `failure_reason`, `delivered_at` |
| `delivery_order_payments` | `delivery_order_id` (cascade), `payment_method`, `amount`, `payment_reference` |

Borrar un pedido borra en cascada su `delivery_orders` y sus pagos (ver [reglas de borrado](../orders/reglas-con-reparto.md)).

### Enums (`app/Models/Enums/`)

| Enum | Valores |
|---|---|
| `DeliveryStatus` | `not_started`, `in_progress`, `finished`, `closed` |
| `DeliveryOrderStatus` | `untouched` (sin gestionar), `delivered`, `failed`, `skipped` |
| `PaymentMethod` | `cash`, `transfer`, `card`, `mercado_pago`, `other` |

## Mapa de archivos

| Archivo | Rol |
|---|---|
| `app/Http/Controllers/Api/V1/DeliveriesController.php` | Endpoints; listado con totales en SQL |
| `app/Services/DeliveryService.php` | Lógica: crear, sincronizar pedidos, operar, iniciar/finalizar/cerrar (con locks) |
| `app/Services/DeliveryLedgerService.php` | Cargos, cobros y validación en cuenta corriente |
| `app/Observers/DeliveryObserver.php` | Reacciona al cambio de `status` (pedidos + ledger) y al borrado |
| `app/Policies/DeliveryPolicy.php` | Permisos por acción |
| `app/Models/Delivery.php` | Totales cobrados por método (`collectedByPaymentMethod*`) |
| `app/Http/Resources/DeliveryResource.php` | Detalle con totales, pedidos y cobros extra |
| `app/Http/Requests/Delivery*Request.php` | Validaciones de alta, edición, agregar pedido, operar pedido |

## Endpoints

Todos bajo `/api/v1`, con `auth:api`.

| Método | Ruta | Acción | Doc |
|---|---|---|---|
| GET | `/deliveries` | Listado paginado con `totals` (cobrado, gastos, neto, ventas) | [armado](armado.md) |
| POST | `/deliveries` | Crear reparto (con pedidos) | [armado](armado.md) |
| GET | `/deliveries/{id}` | Detalle | — |
| PUT | `/deliveries/{id}` | Editar datos + sincronizar pedidos | [armado](armado.md) |
| DELETE | `/deliveries/{id}` | Borrar (no si está cerrado) | [ciclo](ciclo-de-vida.md) |
| POST | `/deliveries/{id}/orders` | Agregar un pedido | [armado](armado.md) |
| POST | `/deliveries/{id}/add-pending-orders` | Agregar pendientes de una zona y fecha | [armado](armado.md) |
| PUT | `/deliveries/{id}/orders/{orderId}` | Operar un pedido (estado, cobro) | [operar](operar.md) |
| PUT | `/deliveries/{id}/expenses` | Gastos del reparto | [operar](operar.md) |
| POST | `/deliveries/{id}/start` | Iniciar | [ciclo](ciclo-de-vida.md) |
| POST | `/deliveries/{id}/finish` | Finalizar | [cierre](cierre-y-ledger.md) |
| POST | `/deliveries/{id}/close` | Cerrar | [cierre](cierre-y-ledger.md) |
| GET | `/deliveries/{id}/account-entries` | Movimientos de cuenta corriente del reparto | [cierre](cierre-y-ledger.md) |
| GET | `/deliveries/{id}/cargas` | Carga de mercadería del reparto | — |

Los cobros extra se crean con `POST /account-entries` enviando `delivery_id` (ver [operar.md](operar.md#cobros-extra-fuera-del-reparto)).

## Riesgos / deuda conocida

- **Alcance del rol Repartidor.** `DeliveryPolicy` y `AccountEntryPolicy` tratan al rol 3 (Repartidor) como privilegiado, junto con admin (1) y administración (4). Es necesario para que cargue cobros, pero por API también puede finalizar, cerrar u operar repartos **ajenos** y validar movimientos manuales (la UI lo oculta). Ver [permisos.md](permisos.md). ⚠️ A decidir.
- **Pedidos `untouched`/`skipped` en repartos cerrados bloquean re-asignarlos.** `assertOrderNotAssignedElsewhere` solo ignora `failed`. Ver [armado.md](armado.md#validación-de-pedido-en-otro-reparto).
- **Borrar un reparto `finished`** vuelve todos sus pedidos a `ready_to_ship` (también los entregados) y deja sus cargos pendientes sin reparto que los valide.
- **Borrar un pedido de un reparto en curso** deja un hueco en `sequence` (no se renumera).
