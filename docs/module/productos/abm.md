# ABM de productos

Alta, edición, baja y listado de productos; validaciones y payload de respuesta según rol.

Última revisión: 2026-09-27 (basado en el código actual)

## Alta y edición

`POST /api/v1/products` y `PUT /api/v1/products/{product}` usan el mismo `ProductFormRequest` (`app/Http/Requests/ProductFormRequest.php`).

Flujo (`ProductsController.php:54-82`):

1. `prepareForValidation` recalcula `price_unit` y `price_min` desde el costo (ver [precios.md](precios.md)).
2. Se valida con las reglas de abajo.
3. `Product::create($request->validated())` / `$product->update($request->validated())`.
4. Si viene `file`, `ImageController::updateImage()` guarda la imagen como `img/products/{slug(name)}_{time()}.{ext}` en el disco `public` y crea/actualiza la fila en `images`.
5. Responde `ProductResource`.

No hay transacción: si falla el upload, el producto queda creado/actualizado sin imagen.

### Reglas de validación

Todas son `required` también en `PUT`: una edición parcial falla con 422. No hay `sometimes`.

| Campo | Regla | Nota |
|---|---|---|
| `name` | `required\|string\|max:191` | |
| `description` | `nullable\|string\|max:191` | |
| `code_miyi` | `required\|string\|max:191` | **No** es `unique`. |
| `barcode` | `nullable\|string\|max:191` | No está en `$fillable`: se descarta en silencio. |
| `id_category` | `required\|exists:categories,id` | `exists` no excluye categorías soft-deleted. |
| `price_purchase` | `required\|numeric` | Sin `min`: acepta negativos. |
| `percentage_may`, `percentage_min` | `required\|numeric\|min:0\|max:100` | Recargo máximo 100 %. |
| `price_unit`, `price_min` | `required\|numeric` | Siempre sobreescritos por el cálculo. |
| `interval_quantity` | `required\|numeric` | Sin `min`; `<= 0` se trata como 1 al validar pedidos. |
| `bulto` | `required\|numeric` | Columna `float(4,2)`: valores ≥ 100 ⚠️ A confirmar comportamiento (error o truncado según `sql_mode`). |
| `own_product`, `show_store` | `required\|boolean` | |
| `min_stock` | `required\|integer` | |
| `type_product` | `required\|string\|in:u,w` | |
| `file` | **sin regla** | Cualquier archivo, extensión tomada del cliente. |

`stock` está en `$fillable` pero no en las reglas: no se puede setear por este endpoint (queda en el default de la columna al crear). ⚠️ A confirmar el default de `products.stock`.

## Baja

`DELETE /api/v1/products/{product}` → `$product->delete()` (soft delete, `ProductsController.php:153-160`). Devuelve el producto borrado.

- No verifica pedidos, stock ni promociones asociadas. La fila en `promotion_products` sigue (el `cascade` solo actúa en hard delete).
- El producto deja de aparecer en `GET /products` (scope global de `SoftDeletes`), pero **`exists:products,id` en `OrderDetailsStoreRequest` no excluye borrados**: se puede seguir cargando en pedidos si se conoce el id.
- `ProductsController::restore` existe (`:170-184`) pero no tiene ruta en `routes/api.php`. No hay forma de restaurar por API.

## Listado

`GET /api/v1/products` (`ProductsController::paginatedQuery`, `:194-248`). Siempre eager-loadea `activePromotions`.

| Query param | Efecto |
|---|---|
| `search` | Divide por espacios; cada término debe matchear `code_miyi LIKE` o `name LIKE` (AND entre términos). |
| `in_stock=1` | `(stock > 0 AND own_product = 1) OR (stock <> 0 AND own_product = 0)` |
| `in_stock=<otro>` | `stock = 0` |
| `id_category` | Filtra por categoría. |
| `id_provider` | Join con `stock_details`: productos que tuvieron **algún** movimiento con ese proveedor. |
| `sortBy`, `sortType` | Orden (default `name ASC`). Columna libre: una inexistente da 500; `sortType` inválido también. |
| `perPage` | Default 40. Sin tope. |

⚠️ Bug: en `in_stock=1` el `orWhere` queda en la raíz del query (`:218-225`), así que `?in_stock=1&id_category=5` devuelve también productos de otras categorías que cumplan `stock <> 0 AND own_product = 0`. Lo mismo con `search` e `id_provider`.

## Campos visibles por rol

`ProductResource` (`app/Http/Resources/ProductResource.php`) arma el payload según rol y "tipo de ruta".

| Campo | Todos | admin (1) | administracion (4) |
|---|---|---|---|
| `id, barcode, name, description, interval_quantity, own_product, no_stock, category, id_category, code_miyi, price_min, price_unit, stock, bulto, type_product, created_at, updated_at, image_path, active_promotions` | Sí | Sí | Sí |
| `price_purchase, percentage_may, percentage_min, show_store` | No | Sí | No |
| `min_stock` | No | Sí | Sí |
| `history_prices`, `history_stock` (5 últimos + total), `history_sales` (5 últimos + total) | No | Solo "show" | Solo "show" |

Ventas (2), repartidor (3) y farmacia (5) ven lo mismo: incluye `stock` y ambos precios de venta, no el costo.

Cómo decide "show" vs "index" (`:19-22`): es "show" si el nombre de ruta contiene `show` **o** la ruta tiene parámetro `{product}`. Consecuencias:

- `PUT /products/{product}` cuenta como "show": la respuesta del update trae los historiales.
- `POST /products` cuenta como "index".
- `history_prices` no tiene límite (todas las entradas con `price_purchase > 0`).

Nota: la ocultación de costo es solo en la respuesta. Cualquier rol puede **escribir** `price_purchase` y los porcentajes vía `PUT`.

## Imagen

- Se guarda en `storage/app/public/img/products/`; `images.path` guarda `/img/products/...` sin el prefijo `/storage`. ⚠️ A confirmar cómo arma la URL el frontend.
- Al reemplazar, `deleteOne()` borra en `public_path()` (otro árbol), así que el archivo viejo queda huérfano (auditoría QUE-02).
- `GET /api/v1/images/{id}` (`ImageController::showImage`) devuelve el mismo `$id` recibido, no la imagen.

## Categorías y proveedores (lo que toca a productos)

- `CategoriesController::destroy` hace soft delete sin chequear productos. Un producto con categoría borrada hace que `ProductResource` evalúe `$this->category->name` sobre `null` → ⚠️ probable 500 en listado y detalle.
- `CategoriesController::store` solo valida `name`; genera `slug` con `_clean_string`.
- Proveedores no se asocian al producto directamente; solo a cada línea de ingreso de stock (`stock_details.id_provider`).
