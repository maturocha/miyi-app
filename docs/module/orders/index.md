# Pedidos (orders)

Un pedido es la venta a un cliente: cabecera (cliente, fecha, descuento general, envío) más líneas de productos. Se entrega en un [reparto](../repartos/index.md), que genera su cargo en la cuenta corriente.

Última revisión: 2026-09-27 (basado en el código actual).

## Contenido

| Documento | Qué cubre |
|---|---|
| [ciclo-de-vida.md](ciclo-de-vida.md) | Estados del pedido, qué los cambia (manual y automático) |
| [alta-y-edicion.md](alta-y-edicion.md) | Cómo se arma un pedido, líneas, validación de cantidades, cálculo de totales |
| [reglas-con-reparto.md](reglas-con-reparto.md) | Cuándo se puede editar o borrar según la entrega y el reparto, y cómo se ajusta la cuenta corriente |

Precios y promociones de las líneas: ver [Productos](../productos/index.md).

## Modelo de datos

```
orders (cabecera)
  ├── order_details (líneas)  ── products, promotions
  ├── delivery_orders         ── deliveries (repartos donde estuvo)
  └── account_entries source_type='orders' (cargo en cuenta corriente, uno por pedido)
```

| Tabla | Columnas clave |
|---|---|
| `orders` | `id_customer`, `id_user` (quién lo cargó), `date`, `status`, `total_bruto`, `discount` (% general), `delivery_cost`, `total`, `payment_method`, `notes`, `print_status` |
| `order_details` | `id_order`, `id_product`, `quantity`, `weight` (productos por peso), `price_unit`, `discount` (% de línea), `price_final`, `promotion_id`, `promotion_snapshot` |

`orders` no tiene soft delete: borrar es físico (con cascada a `delivery_orders`).

## Mapa de archivos

| Archivo | Rol |
|---|---|
| `app/Http/Controllers/Api/V1/OrdersController.php` | Cabecera: alta, edición, estados, borrado, listado, impresión PDF |
| `app/Http/Controllers/Api/V1/OrdersDetailsController.php` | Líneas: alta, edición, baja |
| `app/Models/Order.php` | Totales (`recalculateTotals`), reglas de bloqueo (`lockForEdit`, `isLockedByClosedDelivery`, `wasDelivered`), sync del cargo (`syncPendingCharge`) |
| `app/Models/Order_details.php` | `calculateFinalPrice` |
| `app/Http/Requests/OrderUpdateRequest.php` | Validación de cabecera |
| `app/Http/Requests/OrderDetailsStoreRequest.php`, `OrderDetailsUpdateRequest.php` | Validación de líneas y aplicación de promociones |
| `app/Support/OrderDetailQuantityValidator.php` | Stock e intervalo de venta |
| `app/Http/Resources/OrderResource.php` | Detalle; incluye `is_locked` y `was_delivered` |

## Endpoints

Todos bajo `/api/v1`, con `auth:api`.

| Método | Ruta | Acción | Quién |
|---|---|---|---|
| GET | `/orders` | Listado (incluye `is_locked`, `was_delivered`) | Todos (ver visibilidad abajo) |
| POST | `/orders` `{ id_customer }` | Crear cabecera | Todos |
| GET | `/orders/{id}` | Detalle con líneas | Todos |
| PUT | `/orders/{id}` | Editar cabecera | Todos (salvo bloqueado) |
| PUT | `/orders/{id}/status` | Cambiar estado | Todos (salvo bloqueado) |
| PUT | `/orders/bulk-status` | Estado en masa (omite bloqueados) | admin, ventas, administración |
| DELETE | `/orders/{id}` | Borrar | admin, ventas, administración (salvo entregado) |
| GET | `/orders/{id}/print` | PDF del pedido | Todos |
| POST | `/details` | Agregar línea | Todos (salvo bloqueado) |
| PUT | `/details/{id}` | Editar línea | Todos (salvo bloqueado) |
| DELETE | `/details/{id}` | Quitar línea | Todos (salvo bloqueado) |

"Bloqueado" = entregado en un reparto cerrado. Ver [reglas-con-reparto.md](reglas-con-reparto.md).

### Visibilidad del listado

`OrdersController::paginatedQuery`: admin (1) y **repartidor (3)** ven todos los pedidos. El resto de los roles (**incluida administración, 4**) ve solo los que cargó (`id_user`). ⚠️ A confirmar si administración debería ver todos.

## Riesgos / deuda conocida

- **`price_unit` lo envía el cliente** al agregar una línea: el backend no lo contrasta con el precio del producto.
- **El alta no valida `id_customer`** (`OrdersController@store` usa el request sin FormRequest).
- **Imprimir un pedido de un cliente borrado** (soft delete) da 500: `$order->customer` es null.
- Varios endpoints de pedidos aceptan `sortBy`/`sortType` del request directo en `orderBy`: un valor inválido da 500.
