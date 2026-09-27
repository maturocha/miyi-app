# Precios

Cómo se calculan `price_unit` (mayorista) y `price_min` (minorista), cuándo se recalculan y cómo se usan en las líneas de pedido.

Última revisión: 2026-09-27 (basado en el código actual)

## Fórmula

Único lugar donde se calculan: `ProductFormRequest::prepareForValidation` (`app/Http/Requests/ProductFormRequest.php:14-29`).

```
price_unit = price_purchase + price_purchase * percentage_may / 100    // mayorista
price_min  = price_purchase + price_purchase * percentage_min / 100    // minorista
```

- Se formatea con `number_format(x, 2, '.', '')` → redondeo a 2 decimales (half-up de PHP).
- Se ejecuta solo si llegan los tres (`price_purchase`, `percentage_may`, `percentage_min`) no nulos. Como los tres son `required`, en la práctica siempre se recalcula y **los `price_unit`/`price_min` que mande el frontend se ignoran**.
- Los porcentajes son **recargo sobre costo** (markup), no margen sobre precio de venta. Máximo 100 % por validación.

### Ejemplos

| `price_purchase` | `percentage_may` | `percentage_min` | `price_unit` (may.) | `price_min` (min.) |
|---|---|---|---|---|
| 1000 | 20 | 45 | 1200.00 | 1450.00 |
| 833.33 | 15 | 30 | 958.33 (958.3295) | 1083.33 (1083.329) |
| 500 | 0 | 100 | 500.00 | 1000.00 |

Un margen de venta del 60 % sobre precio (costo 400, venta 1000 = recargo 150 %) no es representable: excede `max:100`.

## Cuándo se recalcula

| Evento | ¿Recalcula precios? |
|---|---|
| `POST /products` | Sí |
| `PUT/PATCH /products/{id}` | Sí (con los valores enviados; el request exige todos los campos) |
| Ingreso de stock con nuevo `price_purchase` (`POST /stock`, `type=in`) | **No** desde el código. Se guarda en `stock_details.price_purchase` y aparece en `history_prices`, pero no toca `products.price_purchase` ni los precios de venta. ⚠️ A confirmar si el trigger de DB de stock también actualiza el costo. |
| Cambio de promociones | No. Las promociones actúan como `discount` de la línea, no sobre el precio del producto. |
| Job/command programado | No existe. |

No se guarda historial de precios de venta. El único "historial de precios" es el de costos de ingreso (`Product::historyPrices()`, ver [historial-y-exportes.md](historial-y-exportes.md)).

## Uso en líneas de pedido

- `order_details.price_unit` **lo envía el frontend** (`OrderDetailsStoreRequest.php:26`: `required|numeric|min:0.01`). El backend no lo compara con `products.price_unit` ni `price_min`, ni decide qué lista corresponde al cliente.
- ⚠️ A confirmar: qué lista (mayorista/minorista) elige el frontend y si depende de `customers.type`. En el backend no hay ninguna referencia a `customers.type` para precios.
- Importe de la línea (`Order_details::calculateFinalPrice`, `app/Models/Order_details.php:33-46`):

```
price_final = round(cantidad * price_unit * (1 - discount / 100), 2)
cantidad    = weight   si type_product = 'w' y weight > 0
            = quantity en cualquier otro caso
```

Ejemplo: producto `w`, `weight = 2.35` kg, `price_unit = 1200`, `discount = 10` → `2.35 * 1200 = 2820` → `2820 * 0.9 = 2538.00`.

El `discount` puede venir del frontend o de una promoción (ver [promociones.md](promociones.md)).

## Otros campos que afectan la venta

| Campo | Efecto |
|---|---|
| `interval_quantity` | La cantidad/peso de la línea debe ser múltiplo (ver [stock.md](stock.md#validación-al-cargar-líneas-de-pedido)). No afecta el precio. |
| `bulto` | Solo informativo en backend (listados y exporte). No hay precio por bulto. |
| `type_product` | `w`: el precio se multiplica por `weight` (kg). `u`: por `quantity`. |
| `show_store` / `price_min` | Las queries de tienda (`Product::inStockStore`, `Category::BySlug`) exponen `price_min` como `price`, pero no tienen call sites en esta API (código muerto). |

## Puntos a tener en cuenta

- Precio de venta y costo los puede reescribir cualquier rol autenticado (no hay autorización en `ProductFormRequest`).
- `price_purchase` acepta negativos (`numeric` sin `min`).
- Si `price_purchase` llega como texto no numérico, la suma en `prepareForValidation` corre antes de la validación: en PHP 7.4 genera un warning "A non-numeric value" que Laravel convierte en excepción → ⚠️ probable 500 en vez de 422.
