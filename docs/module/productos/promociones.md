# Promociones

Tipos de promoción, validaciones del ABM y cómo se aplican a las líneas de pedido (`order_details`), incluido el snapshot.

Última revisión: 2026-09-27 (basado en el código actual)

## Modelo

`promotions` (`app/Models/Promotion.php`, migración `2025_08_23_105706_create_promotions_table.php`):

| Columna | Tipo | Nota |
|---|---|---|
| `name` | string(120) | |
| `type` | string(50) | Ver tipos. |
| `params` | JSON (cast `array`) | Forma depende del tipo. |
| `starts_at` | date (cast `date`) | Obligatoria en DB. |
| `ends_at` | date nullable | `null` = sin vencimiento. |
| `is_active` | bool, default `true` | |
| `priority` | int, default 100 | Solo ordena `activePromotions`. |
| `exclusive` | bool, default `false` | **No se usa** en ninguna lógica. |

- `promotion_products` (pivot N:M): PK compuesta, FK `cascade` a `promotions` y `products`.
- `order_details.promotion_id`: FK `ON DELETE SET NULL`. `order_details.promotion_snapshot`: JSON.
- No usa `SoftDeletes`: `DELETE /promotions/{id}` borra la fila, el pivot cae en cascada y las líneas de pedido quedan con `promotion_id = null` pero conservan el snapshot.

"Activa" (`Promotion::scopeActive`): `is_active = 1 AND starts_at <= hoy AND (ends_at IS NULL OR ends_at >= hoy)`, con `hoy = now()->toDateString()` en el timezone de la app (default `UTC`). ⚠️ A confirmar `APP_TIMEZONE` de producción: con UTC, una promo cambia de estado a las 21:00 de Argentina.

## Tipos

| Tipo | `params` | Descuento resultante sobre la línea | Quién calcula el `discount` |
|---|---|---|---|
| `LINE_PERCENT` | `{ "percent": 10 }` | `percent` | Frontend; backend valida igualdad |
| `BUY_X_TOTAL_DISCOUNT` | `{ "discounts": [ {"x": 10, "percent": 5}, {"x": 20, "percent": 10} ] }` | `percent` del mayor tramo con `cantidad >= x` | Frontend; backend valida igualdad |
| `NTH_PERCENT` | `{ "n": 3, "percent": 50 }` | `floor(cant/n) / cant * percent` | Backend (frontend debe mandar 0) |
| `BUY_X_GET_Y` | `{ "x": 8, "y": 7 }` ("lleva 8, paga 7") | `floor(cant/x) * (x - y) / cant * 100` | Backend (frontend debe mandar 0) |

`cant` = `weight` si el producto es `w` y `weight > 0`; si no, `quantity`. Descuentos calculados por backend se redondean a 2 decimales.

### Ejemplos

| Tipo / params | `cant` | `discount` | `price_final` con `price_unit = 100` |
|---|---|---|---|
| `LINE_PERCENT {percent:10}` | 5 | 10 | 450.00 |
| `BUY_X_TOTAL_DISCOUNT` tramos 10→5 %, 20→10 % | 25 | 10 | 2250.00 |
| idem | 15 | 5 | 1425.00 |
| idem | 5 | 0 | 500.00 |
| `NTH_PERCENT {n:3, percent:50}` | 7 | `2/7*50 = 14.29` | 600.03 |
| `NTH_PERCENT {n:3, percent:50}` | 2 | 0 | 200.00 |
| `BUY_X_GET_Y {x:8, y:7}` | 8 | `1/8*100 = 12.5` | 700.00 |
| `BUY_X_GET_Y {x:8, y:7}` | 17 | `2/17*100 = 11.76` | 1500.08 |

La promoción siempre se traduce a un **porcentaje de descuento de línea**; no hay ítems gratis como líneas separadas ni precio fijo.

## ABM de promociones

`PromotionController` + `StorePromotionRequest` (mismo request para store y update).

| Campo | Regla |
|---|---|
| `name` | `sometimes\|required\|string\|max:120` |
| `type` | `sometimes\|required\|in:BUY_X_GET_Y,NTH_PERCENT,LINE_PERCENT,BUY_X_TOTAL_DISCOUNT` |
| `params` | `sometimes\|required\|array` |
| `starts_at` | `sometimes\|required\|date` (normalizada a `Y-m-d`) |
| `ends_at` | `nullable\|date\|after_or_equal:starts_at` |
| `is_active`, `exclusive` | `sometimes\|boolean` |
| `priority` | `sometimes\|integer` |
| `product_ids` / `.*` | `nullable\|array` / `integer\|exists:products,id` |

Reglas de `params` según `type` **enviado en el request**:

| Tipo | Reglas |
|---|---|
| `BUY_X_GET_Y` | `x`, `y`: `required\|integer\|min:1` |
| `NTH_PERCENT` | `n`: `integer\|min:2`; `percent`: `0..100` |
| `LINE_PERCENT` | `percent`: `0..100` |
| `BUY_X_TOTAL_DISCOUNT` | `discounts`: array ≥ 1; cada `x` `integer\|min:1`, `percent` `0..100` |

Productos: `store` sincroniza si `product_ids` no está vacío; `update` sincroniza si `product_ids !== null` (un `[]` desasocia todos).

Listado (`GET /promotions`): `search` (name/type), `active=1` (scope activo) / `active=0` (`is_active = false`), `type`, `product_id`, `with_products=1`, `sortBy`/`sortType` (default `priority ASC`), `perPage` (40). `DELETE` devuelve el listado paginado.

Huecos de validación (comportamiento actual):

- Todo es `sometimes`: un `POST` sin `name`/`type`/`starts_at` pasa la validación y falla en la DB → ⚠️ probable 500.
- En `PUT` sin `type`, `params` no se valida por forma (se puede guardar cualquier array).
- `BUY_X_GET_Y` no exige `y < x`. Con `y > x` el descuento calculado es **negativo** (ej. x=2, y=3, cant=2 → −50 % → la línea cuesta más que el subtotal).
- `exists:products,id` acepta productos soft-deleted.

## Aplicación a líneas de pedido

Código: `OrderDetailsStoreRequest` (`app/Http/Requests/OrderDetailsStoreRequest.php`) y su copia `OrderDetailsUpdateRequest`. El pedido y la línea en sí se documentan en `docs/module/orders`.

El frontend elige la promoción y manda `promotion_id` (el backend **no** busca ni elige promociones automáticamente; `priority` y `exclusive` no intervienen). Una línea tiene como máximo una promoción.

Flujo en store (`OrderDetailsStoreRequest`):

1. `prepareForValidation`: `discount` default `0` (`:131-136`).
2. Si hay `promotion_id` se agregan dos reglas custom (`:31-34`):
   - `promotion_valid_for_product` (`:81-95`): promo existe, `is_active`, dentro de fechas y asociada al producto.
   - `discount_matches_promotion` (`:101-124`): para `BUY_X_GET_Y`/`NTH_PERCENT` exige `discount == 0`; para `LINE_PERCENT`/`BUY_X_TOTAL_DISCOUNT` exige `|discount − esperado| <= 0.01` (`calculateExpectedDiscount`, `:328-371`).
3. `validated()` (`:138-173`): guarda `promotion_snapshot = {id, name, type, params}` y llama `applyPromotion`, que re-chequea vigencia y pertenencia y, según tipo, **reemplaza** `discount` (NTH / BUY_X_GET_Y) o lo deja como vino (LINE / TOTAL).
4. `price_final = round(cant * price_unit * (1 − discount/100), 2)`.

Diferencias en update (`OrderDetailsUpdateRequest`):

| Situación | Comportamiento |
|---|---|
| Campo no enviado (`id_product`, `quantity`, `price_unit`, `weight`, `discount`) | Se toma de la línea guardada. |
| `promotion_id` con valor | Revalida, nuevo snapshot, recalcula. |
| `promotion_id: null` explícito | Limpia `promotion_id`, **conserva el snapshot**. El `discount` se mantiene (el de la línea, si no se manda otro). |
| `promotion_id` no enviado | La promo guardada no se revalida ni se reaplica; se usa el `discount` guardado. Si cambia `quantity` en una línea `NTH_PERCENT`/`BUY_X_GET_Y`, el descuento queda desactualizado (ej. `BUY_X_GET_Y 8x7`: 8 → 7 unidades mantiene 12,5 %). |
| Request con clave `price_final` | No se recalcula `price_final` (no tiene regla, así que el valor enviado tampoco se guarda): queda el `price_final` viejo aunque cambie la cantidad. |

## Snapshot

`promotion_snapshot` guarda `{id, name, type, params}` al momento de aplicar. Sirve para que la línea siga siendo explicable si la promoción se edita o borra. No guarda `starts_at`, `ends_at` ni el `discount` resultante (ese queda en `order_details.discount`).

## ⚠️ A confirmar: promo sin efecto el día de inicio

`applyPromotion` compara `$promotion->starts_at > now()->toDateString()` (`OrderDetailsStoreRequest.php:188`, `OrderDetailsUpdateRequest.php:249`). `starts_at` es un `Carbon`; en PHP 7.4 la comparación objeto-vs-string castea a string (`"2026-09-27 00:00:00"`), que es mayor que `"2026-09-27"`. Si esto se confirma, el día de inicio:

- La validación pasa (esa usa `toDateString()`), se guarda el snapshot y `promotion_id`,
- pero `applyPromotion` sale temprano: en `NTH_PERCENT`/`BUY_X_GET_Y` el `discount` queda en 0 (lo que exige la validación) y la promo no descuenta nada.
- `LINE_PERCENT`/`BUY_X_TOTAL_DISCOUNT` no se ven afectados (el descuento lo pone el frontend).

Verificar con un test de caracterización antes de tocar.
