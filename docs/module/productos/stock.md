# Stock

Movimientos de stock (ingresos/egresos), quién mantiene `products.stock` y cómo se valida el stock al cargar líneas de pedido.

Última revisión: 2026-09-27 (basado en el código actual)

## Modelo

- `stocks` (cabecera): `id`, `date`, `id_user`, `type` (`in` | `out`), `notes`, timestamps.
- `stock_details` (líneas): `id_stock`, `id_product`, `quantity`, `id_provider`, `price_purchase`, `due_date`, `bulto_reference`.
- `products.stock`: stock actual que leen el listado, el resource y el validador de pedidos.

`quantity` se guarda **con signo**: positivo en `in`, negativo en `out` (`StockController::buildStockDetails`, `app/Http/Controllers/Api/V1/StockController.php:67-83`).

## Quién escribe `products.stock`

Nadie en `app/`. No hay observers de `Stock`/`Stock_details`/`Order_details`, ni `increment`/`decrement`/`update` sobre la columna.

⚠️ A confirmar: según `docs/audits/19-08-2026.md` (NV-01) lo mantiene casi con certeza un **trigger de base de datos no versionado**. Se desconoce:

- Si reacciona a `stock_details` (ingresos/egresos) y/o a `order_details` (ventas).
- Si revierte al editar/borrar una línea de pedido o al cancelar un pedido.
- Si también actualiza `products.price_purchase`.

Verificación sugerida: `SHOW TRIGGERS;` y `SHOW CREATE TABLE products;` en producción.

## Endpoints

### `POST /api/v1/stock` — cargar movimiento

Body (`app/Http/Requests/StockFormRequest.php`):

| Campo | Regla |
|---|---|
| `type` | `required\|in:in,out` |
| `notes` | `nullable\|string` |
| `items` | `required\|array\|min:1` |
| `items.*.id_product` | `required\|integer\|exists:products,id` |
| `items.*.quantity` | `required\|integer\|min:1` (siempre positivo; el signo lo pone el controller) |
| `items.*.id_provider` | `required\|exists:providers,id` **solo si `type=in`** |
| `items.*.price_purchase` | `required\|numeric\|min:0` **solo si `type=in`** |

Ejemplo:

```json
{ "type": "in", "notes": "Remito 123",
  "items": [ { "id_product": 10, "quantity": 24, "id_provider": 3, "price_purchase": 850 } ] }
```

→ `stocks(type=in, id_user=<auth>, date=now AR)` + `stock_details(id_product=10, quantity=24, id_provider=3, price_purchase=850)`.

Con `type=out` y `quantity: 5` se guarda `quantity = -5`, sin proveedor ni costo.

Comportamiento a tener en cuenta:

- `date` = `Carbon::now()` en `America/Argentina/Buenos_Aires`; `created_at` usa el timezone de la app (`APP_TIMEZONE`, default `UTC`). Los historiales ordenan por `created_at`.
- Sin transacción: `Stock::create()` y luego `Stock_details::insert()`. Si falla el insert queda una cabecera sin líneas.
- `insert()` masivo: `stock_details` queda sin `created_at`/`updated_at`.
- `quantity` entero: **no se pueden cargar fracciones de kg** para productos `type_product = w`.
- Un `out` no valida contra el stock disponible (puede dejarlo negativo, si el trigger lo permite).
- `due_date` y `bulto_reference` nunca se escriben desde la API.
- Cualquier rol autenticado puede cargar movimientos.

### `GET /api/v1/stock` — listado

`Stock::getAll()`: join con `users`, `stock_details` y `products`, agrupado por `stocks.id`, con `details` = nombres de productos concatenados. Filtro opcional `?type=in|out`, `perPage` (default 40), orden `id DESC`.

### `GET /api/v1/stock/{id}` — detalle

Cabecera (`Stock::getByID`) + `details` (`Stock::getDetailsByID`). ⚠️ Posible bug: `getDetailsByID` hace **inner join** con `providers`; las líneas de un `out` tienen `id_provider` nulo, así que el detalle de un egreso probablemente vuelve vacío.

### `PUT` / `DELETE /api/v1/stock/{id}` — no soportado

Ambos devuelven siempre `405` (`StockController.php:115-127`):

```json
{ "message": "No se puede editar un movimiento de stock. Cargá un movimiento inverso." }
```

Motivo: no está definido si el trigger revierte `products.stock` al editar/borrar `stock_details`. Borrar un movimiento podría dejar el stock desalineado sin rastro. Para corregir se carga un movimiento de tipo contrario (ej. se cargó `in` 24 por error → cargar `out` 24 con nota).

`StockController::restore` sigue referenciando `Order` sin importar; no tiene ruta (código muerto).

## Validación al cargar líneas de pedido

`OrderDetailQuantityValidator::validateAmountAgainstProduct` (`app/Support/OrderDetailQuantityValidator.php`), invocado desde `OrderDetailsStoreRequest` y `OrderDetailsUpdateRequest` en un `after()` del validator.

`amount` = `weight` si el producto es `w` y `weight > 0`; si no, `quantity`. El error se asigna a `weight` o `quantity` según el tipo.

Reglas, en orden:

| # | Condición | Resultado |
|---|---|---|
| 1 | `amount <= 0` | "La cantidad debe ser mayor a 0" |
| 2 | `own_product = 1` y `amount > stock` | "Stock insuficiente. Disponible: N unidades/kg" |
| 3 | `own_product = 1`, `0 < stock < interval` | Solo se acepta `amount == stock` (vender el remanente) |
| 4 | `amount` no es múltiplo de `interval_quantity` | "La cantidad debe ser múltiplo de …" |

- `interval_quantity <= 0` se trata como 1.
- Comparaciones con epsilon relativo `1e-9`.
- `own_product = 0` **no valida stock**, solo el múltiplo.

Ejemplos (`own_product = 1`, `interval_quantity = 6`):

| `stock` | `amount` | Resultado |
|---|---|---|
| 30 | 12 | OK |
| 30 | 10 | Error: múltiplo de 6 |
| 30 | 36 | Error: stock insuficiente (30) |
| 4 | 4 | OK (remanente) |
| 4 | 2 | Error: remanente menor al intervalo, solo se puede agregar 4 |

Producto `w`, `interval_quantity = 0.5`, `stock = 3.2`: `weight = 1.5` OK; `weight = 1.2` error de múltiplo.

### Limitaciones

- Lee `products.stock` sin lock: dos pedidos concurrentes pueden tomar el mismo último stock (sobreventa).
- No descuenta otras líneas del mismo producto en el mismo pedido ni pedidos pendientes. ⚠️ A confirmar si el trigger descuenta al insertar la línea (en ese caso el `stock` leído ya refleja pedidos previos).
- En update se valida la **cantidad nueva completa** contra `stock`, sin sumar la cantidad que la línea ya tenía. Si el trigger descuenta al guardar, subir una línea de 8 a 10 con 3 disponibles se rechaza aunque solo falten 2.
- `stock` en la respuesta de `ProductResource` es el mismo valor, visible para todos los roles.

## Stock bajo

`min_stock` se guarda y se devuelve a admin/administracion. `Product::inLowStock()` (stock ≤ `min_stock`, `own_product = 1`) no tiene call sites: no hay endpoint de alertas de stock bajo en esta API.
