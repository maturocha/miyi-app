# Pedidos — alta y edición

Cómo se arma un pedido desde el dashboard, cómo se validan las líneas y cómo se calculan los totales.

Última revisión: 2026-09-27 (basado en el código actual).

## Flujo desde el front (`pedidos/_components/OrderForm.tsx`)

Cada acción se guarda **en el momento** contra la API; no hay "borrador" local:

| Paso en la UI | Request |
|---|---|
| Elegir cliente (pedido nuevo) | `POST /orders { id_customer }` → crea la cabecera |
| Cambiar cliente (pedido existente) | `PUT /orders/{id} { id_customer }` |
| Agregar producto | `POST /details` |
| Cambiar peso (productos por kg) | `PUT /details/{id} { weight, discount, price_unit }` |
| Quitar producto | `DELETE /details/{id}` |
| Confirmar | `PUT /orders/{id} { notes, delivery_cost, discount, payment_method, date, status? }` |

La cantidad de una línea existente no se edita: se quita y se vuelve a agregar.

⚠️ Si el usuario elige un cliente y después lo borra del selector, la cabecera ya creada queda en la DB sin líneas.

## Líneas (`order_details`)

### Validación (`OrderDetailsStoreRequest`)

| Campo | Regla |
|---|---|
| `id_order`, `id_product` | Requeridos, existentes |
| `quantity` | > 0 |
| `weight` | Opcional, > 0, hasta 2 decimales (productos `type_product = 'w'`) |
| `price_unit` | Requerido, > 0. ⚠️ Lo envía el cliente |
| `discount` | 0–100 (% de línea) |
| `promotion_id` | Opcional; si viene, debe aplicar al producto y el descuento debe coincidir con la promoción |

`OrderDetailsUpdateRequest`: los campos que no se envían (`quantity`, `price_unit`, `weight`, `discount`) toman el valor guardado de la línea.

### Stock e intervalo (`OrderDetailQuantityValidator`)

Sobre la cantidad, o el peso si el producto es por kg:
- Debe ser > 0.
- **Producto propio** (`own_product = 1`): no puede superar `products.stock`. Si el stock remanente es menor al intervalo de venta, solo se puede cargar exactamente el remanente.
- Debe ser múltiplo de `interval_quantity` (default 1). Ejemplo: con intervalo 10, son válidos 10, 20, 30.

### Precio final de la línea (`Order_details::calculateFinalPrice`)

```
price_final = cantidad × price_unit × (1 − discount / 100)
```
`cantidad` = `weight` para productos por peso (si viene y es > 0), si no `quantity`.

Ejemplo: 20 u × $1.519,35 con 0 % → **$30.387**.

Si la línea tiene promoción, `price_final` y `discount` los define la promoción y se guarda un `promotion_snapshot` con la promoción del momento. Detalle en [Productos](../productos/index.md).

## Totales de la cabecera (`Order::recalculateTotals`)

Única fórmula, usada al tocar líneas y al editar la cabecera:

```
total_bruto = Σ order_details.price_final
total       = total_bruto + delivery_cost − total_bruto × discount / 100
```

Ejemplo: líneas $30.387 + $35.443,20 = $65.830,20; con 10 % de descuento y $2.000 de envío → $65.830,20 + $2.000 − $6.583,02 = **$61.247,18**.

Se recalcula:
- después de agregar, editar o quitar una línea (`OrdersDetailsController::updateOrderTotal`);
- **siempre** después de editar la cabecera (`PUT /orders/{id}`), con el pedido bloqueado.

## Edición de la cabecera (`PUT /orders/{id}`)

`OrderUpdateRequest`:
- Campos: `id_customer`, `notes`, `delivery_cost` (0–999.999,99), `discount`, `payment_method`, `date`, `status`.
- **Si un campo no viene en el request, se conserva el valor guardado** (`notes`, `delivery_cost`, `discount`). Antes caían a 0 y, por ejemplo, cambiar el cliente borraba el descuento y el envío.

Todo corre en una transacción con `Order::lockForEdit`: si el pedido está bloqueado responde `422` (ver [reglas-con-reparto.md](reglas-con-reparto.md)). Después de recalcular, se sincroniza el cargo pendiente en la cuenta corriente, si existe.
