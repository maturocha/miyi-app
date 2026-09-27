# Historiales y exportes

Endpoints de historial de stock/ventas por producto, historial de costos embebido en el detalle y exporte Excel del catálogo.

Última revisión: 2026-09-27 (basado en el código actual)

## Historial de stock

`GET /api/v1/products/{id}/stock-history?page=1&per_page=20` → `ProductsController::stockHistory` → `Product::stockMovingPaginated()` (`app/Models/Product.php:183-222`).

- Query: `products ⋈ stock_details ⋈ stocks`, filtrado por producto, `GROUP BY stock_details.id`, orden `stocks.created_at DESC`.
- Una fila por **línea** de `stock_details` (si un movimiento tiene el mismo producto dos veces, aparece dos veces con el mismo `id`).
- `total` se calcula aparte con `COUNT(DISTINCT stock_details.id)`.

Respuesta:

```json
{
  "data": [ { "id": 812, "date": "2026-09-20 14:03:11", "quantity": "-5", "type": "out" } ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 47 }
}
```

- `id` = `stocks.id`; `quantity` con signo (negativo en `out`); `date` = `stocks.created_at` (timezone de la app, no `stocks.date`).
- `404 {"message":"Product not found"}` si no existe o está soft-deleted (`Product::find`).

## Historial de ventas

`GET /api/v1/products/{id}/sales-history?page=1&per_page=20` → `Product::orderMovingPaginated()` (`app/Models/Product.php:227-256`).

- Query: `products ⋈ order_details ⋈ orders`, `GROUP BY order_details.id`, orden `orders.created_at DESC`.
- Campos: `date` (`orders.created_at`), `id` (`orders.id`), `quantity` (`order_details.quantity`).
- **No filtra por estado del pedido**: incluye pedidos cancelados/no entregados. ⚠️ A confirmar si es lo esperado.
- Para productos `w` devuelve `quantity`, no `weight`.

## Paginación de ambos

| Param | Default | Nota |
|---|---|---|
| `page` | 1 | `max(1, page)` |
| `per_page` | 20 | Sin tope. `0` devuelve `data` vacío; negativo (`take(-1)`) devuelve todo. |

Ejemplo: `total = 47`, `per_page = 20` → `last_page = ceil(47/20) = 3`.

No hay chequeo de rol: cualquier usuario autenticado ve ambos historiales, aunque `ProductResource` solo embebe los resúmenes para admin/administracion.

## Historial embebido en el detalle

En `GET /products/{product}` (y en la respuesta de `PUT`), solo para admin (1) y administracion (4) (`ProductResource.php:63-76`):

| Clave | Fuente | Límite |
|---|---|---|
| `history_prices` | `Product::historyPrices()`: `stock_details.price_purchase > 0` con `stocks.created_at` | **Sin límite** |
| `history_stock` | `{ data: stockMoving(5), total: stockMovingTotal() }` | 5 |
| `history_sales` | `{ data: orderMoving(5), total: orderMovingTotal() }` | 5 |

`history_prices` es el historial de **costos de ingreso**, no de precios de venta (que no se historizan).

## Exporte Excel

`GET /products/export` (ruta en `routes/web.php:27`, sin prefijo `/api/v1`) → `ProductsController::export` → `Excel::download(new ProductsExport, 'products.xlsx')`.

- **Sin autenticación ni autorización**: grupo `web` sin middleware `auth`. Expone costos y porcentajes a cualquiera con la URL.
- `ProductsExport` (`app/Exports/ProductsExport.php`) implementa `FromQuery` sin chunking; excluye soft-deleted (scope global); sin filtros.

Columnas: encabezado vs dato real:

| # | Encabezado | Columna exportada |
|---|---|---|
| 1 | ID | `products.id` |
| 2 | Nombre | `name` |
| 3 | ID Categoria | `id_category` (id, no nombre) |
| 4 | Codigo | `code_miyi` |
| 5 | Stock Actual | `stock` |
| 6 | Precio de compra | `price_purchase` |
| 7 | % May | `percentage_may` |
| 8 | % Min | `percentage_min` |
| 9 | **Precio Min** | **`price_unit` (mayorista)** |
| 10 | **Precio May** | **`price_min` (minorista)** |
| 11 | Venta por | `interval_quantity` |
| 12 | Producto propio | `own_product` |
| 13 | Bulto | `bulto` |

⚠️ Bug: columnas 9 y 10 están cruzadas. Según `ProductFormRequest`, `price_unit` sale de `percentage_may` (mayorista) y `price_min` de `percentage_min` (minorista), pero el exporte las rotula al revés. Ejemplo: costo 1000, may 20 %, min 45 % → la planilla muestra "Precio Min = 1200" y "Precio May = 1450".

No hay importación de productos por Excel.
