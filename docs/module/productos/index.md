# Módulo Productos

Catálogo de productos, precios mayorista/minorista, movimientos de stock, promociones y los historiales/exportes asociados.

Última revisión: 2026-09-27 (basado en el código actual)

## Qué cubre

- ABM de productos (`products`) con imagen, categoría y parámetros de venta → [abm.md](abm.md)
- Cálculo de precios a partir del costo y dos porcentajes → [precios.md](precios.md)
- Movimientos de stock (`stocks` + `stock_details`) y validación de cantidades al cargar pedidos → [stock.md](stock.md)
- Promociones (`promotions` + `promotion_products`) y su aplicación a líneas de pedido → [promociones.md](promociones.md)
- Historial de stock / ventas por producto y exporte Excel → [historial-y-exportes.md](historial-y-exportes.md)

Categorías y proveedores se documentan solo en lo que tocan a productos.

## Modelo de datos

⚠️ A confirmar: no hay migración `create` para `products`, `stocks`, `stock_details`, `categories`, `providers` ni `images` (el schema del core no es reconstruible desde `database/migrations`). Las columnas se infieren de `$fillable` y de las queries.

### `products` (`app/Models/Product.php`, `SoftDeletes`)

| Columna | Uso |
|---|---|
| `name`, `description`, `code_miyi` | Identificación. `search` busca en `name` y `code_miyi`. |
| `id_category` | FK a `categories` (requerida). |
| `price_purchase` | Costo. Base del cálculo de precios. |
| `percentage_may`, `percentage_min` | Recargo % (0–100) para mayorista y minorista. |
| `price_unit` | Precio **mayorista** = costo + `percentage_may` %. |
| `price_min` | Precio **minorista** = costo + `percentage_min` %. |
| `stock` | Stock actual. **Ninguna línea de `app/` lo escribe** (ver [stock.md](stock.md)). |
| `min_stock` | Umbral de stock bajo. |
| `interval_quantity` | Múltiplo de venta (ej. 6 → se vende de a 6). |
| `bulto` | Unidades por bulto. `float(4,2)` desde `2022_10_08_181843_change_bulto_type_number_table_products.php` (máx. 99,99). |
| `type_product` | `u` (unidad) o `w` (peso, kg). |
| `own_product` | `1` = stock controlado (se valida contra `stock`); `0` = no se valida stock. ⚠️ A confirmar semántica de negocio ("propio" vs reventa/a pedido). |
| `show_store` | Visible en la tienda. Solo se usa en queries de tienda que hoy son código muerto. |
| `barcode`, `no_stock` | Leídos por `ProductResource`, pero **no** están en `$fillable`: `barcode` se valida y se descarta. ⚠️ A confirmar si `no_stock` existe como columna. |
| `deleted_at` | Soft delete. |

### Tablas relacionadas

| Tabla | Modelo | Relación con producto |
|---|---|---|
| `categories` | `app/Models/Category.php` (SoftDeletes) | `Product::category()` belongsTo por `id_category`. |
| `images` | `app/Models/Image.php` | Una imagen por producto (`images.id_product`), leída con `Product::getImages()`. |
| `stocks` | `app/Models/Stock.php` | Cabecera de movimiento: `date`, `id_user`, `type` (`in`/`out`), `notes`. |
| `stock_details` | `app/Models/Stock_details.php` | Línea: `id_stock`, `id_product`, `quantity` (con signo), `id_provider`, `price_purchase`, `due_date`, `bulto_reference`. |
| `providers` | `app/Models/Provider.php` (SoftDeletes) | Solo vía `stock_details.id_provider` (movimientos `in`). |
| `promotions` | `app/Models/Promotion.php` | N:M vía `promotion_products` (cascade en ambos FK). |
| `order_details` | `app/Models/Order_details.php` | `id_product`, `promotion_id` (FK `set null`), `promotion_snapshot` (JSON). |

## Mapa de archivos

| Archivo | Rol |
|---|---|
| `app/Models/Product.php` | Modelo, relaciones, queries de historial. Contiene ~10 métodos estáticos muertos (`inStock`, `inStockStore`, `listStock`, `sales`, `getByID`, `getStockByProductID`, `getProductSoldByDate`, …) que joinean tablas inexistentes (`sales`, `colors`, `product_in_stock`). |
| `app/Http/Controllers/Api/V1/ProductsController.php` | CRUD, listado filtrado, `salesHistory`, `stockHistory`, `export`. |
| `app/Http/Requests/ProductFormRequest.php` | Validación + cálculo de precios (`prepareForValidation`). |
| `app/Http/Resources/ProductResource.php` | Serialización; campos según rol y ruta. |
| `app/Http/Controllers/Api/V1/ImageController.php` + `app/Traits/ImageTraitController.php` | Upload de imagen del producto. |
| `app/Http/Controllers/Api/V1/StockController.php` | Movimientos de stock. |
| `app/Http/Requests/StockFormRequest.php` | Validación de movimientos. |
| `app/Support/OrderDetailQuantityValidator.php` | Stock + intervalo al cargar líneas de pedido. |
| `app/Models/Promotion.php`, `app/Http/Controllers/Api/V1/PromotionController.php`, `app/Http/Requests/StorePromotionRequest.php`, `app/Http/Resources/PromotionResource.php` | Promociones. |
| `app/Http/Requests/OrderDetailsStoreRequest.php`, `app/Http/Requests/OrderDetailsUpdateRequest.php` | Motor de promociones aplicado a líneas (duplicado en ambos). |
| `app/Exports/ProductsExport.php` | Exporte `products.xlsx`. |
| `routes/api.php:93-107`, `routes/web.php:27` | Rutas. |

## Endpoints

Prefijo `api/v1`, middleware `auth:api` + `cors`, salvo el exporte.

| Método | Ruta | Controller@método | Quién puede |
|---|---|---|---|
| GET | `/products` | `ProductsController@index` | Cualquier usuario autenticado |
| POST | `/products` | `ProductsController@store` | Cualquier usuario autenticado |
| GET | `/products/{product}` | `ProductsController@show` | Cualquier usuario autenticado |
| PUT/PATCH | `/products/{product}` | `ProductsController@update` | Cualquier usuario autenticado |
| DELETE | `/products/{product}` | `ProductsController@destroy` | Cualquier usuario autenticado |
| GET | `/products/{id}/sales-history` | `ProductsController@salesHistory` | Cualquier usuario autenticado |
| GET | `/products/{id}/stock-history` | `ProductsController@stockHistory` | Cualquier usuario autenticado |
| GET | `/products/export` (**`routes/web.php`, sin `/api/v1`**) | `ProductsController@export` | **Cualquiera, sin autenticación** |
| GET/POST/GET/PUT/DELETE | `/promotions[/{promotion}]` | `PromotionController@index/store/show/update/destroy` | Cualquier usuario autenticado |
| GET | `/stock` | `StockController@index` | Cualquier usuario autenticado |
| POST | `/stock` | `StockController@store` | Cualquier usuario autenticado |
| GET | `/stock/{id}` | `StockController@show` | Cualquier usuario autenticado |
| PUT/DELETE | `/stock/{id}` | `StockController@update/destroy` | Nadie: siempre `405` |
| `*` | `/categories`, `/providers` (resource) | `CategoriesController`, `ProvidersController` | Cualquier usuario autenticado |

`Route::resource` además registra `create`/`edit` (GET `/products/create`, `/products/{product}/edit`, idem promotions/stock) que no existen en los controllers → 500. `restore` existe en `ProductsController` y `StockController` pero **no tiene ruta**.

### Autorización

- Todos los FormRequests del módulo (`ProductFormRequest`, `StockFormRequest`, `StorePromotionRequest`, `OrderDetails*Request`) devuelven `authorize() { return true; }`. No hay policies ni chequeos de `role_id` en ninguno de estos controllers.
- Único control por rol: **visibilidad de campos** en `ProductResource` (ver [abm.md](abm.md#campos-visibles-por-rol)), vía `User::hasRole()` que compara `roles.key` (`admin`, `administracion`). ⚠️ A confirmar que las `key` en producción sean exactamente esas (se generan con `Str::slug(name)` en `2024_05_05_000000_add_key_to_roles_table.php`).
- Roles: 1 admin, 2 ventas, 3 repartidor, 4 administracion, 5 farmacia. Ventas, repartidor y farmacia tienen el mismo acceso de escritura que admin.

## Riesgos / deuda conocida

| # | Riesgo | Detalle |
|---|---|---|
| 1 | Sin autorización por rol | Cualquier token puede editar precios/costos, borrar productos, cargar stock y crear promociones. Ver auditoría `docs/audits/19-08-2026.md` (SEC-06). |
| 2 | Exporte público | `GET /products/export` sin auth expone costos y márgenes (SEC-05). |
| 3 | `products.stock` sin dueño en el código | Lo mantiene probablemente un trigger de DB no versionado (NV-01). Sin locks: sobreventa posible. |
| 4 | Upload sin validar | `ProductFormRequest` no tiene regla para `file`; extensión del cliente (SEC-04). |
| 5 | Filtro `in_stock` rompe otros filtros | `orWhere` en la raíz (`ProductsController.php:214-229`). Ver [abm.md](abm.md#listado). |
| 6 | Precio de línea libre | `order_details.price_unit` lo manda el frontend; no se compara con `price_unit`/`price_min` (DAT-09). |
| 7 | Motor de promociones duplicado | ~350 líneas idénticas en `OrderDetailsStoreRequest` y `OrderDetailsUpdateRequest`. |
| 8 | Exporte con columnas cruzadas | "Precio Min" / "Precio May" invertidos. Ver [historial-y-exportes.md](historial-y-exportes.md). |
| 9 | Stock en kg no cargable | `StockFormRequest` exige `quantity` entero; productos `w` no pueden cargar fracciones. |
| 10 | Categoría borrada rompe el listado | `ProductResource` hace `$this->category->name` sin null-safe. ⚠️ A confirmar. |
| 11 | N+1 en el listado | `category` e imagen se consultan por producto (PRF-01). |

Detalle de cada punto en el archivo correspondiente.
