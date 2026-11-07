# Clientes (customers)

Ficha del cliente: datos de contacto y de visita, tipo (mayorista/particular) y saldo de cuenta corriente.

Última revisión: 2026-11-08 (basado en el código actual).

## Contenido

| Documento | Qué cubre |
|---|---|
| [cumpleanos.md](cumpleanos.md) | Campo `birthday`: validación, formato, listado y dónde se consume |

Relacionado: [Repartos — cierre y ledger](../repartos/cierre-y-ledger.md) (cómo se mueve el saldo), [Inicio](../inicio.md) (widget de próximos cumpleaños).

## Modelo de datos

| Columna (`customers`) | Notas |
|---|---|
| `name`, `fullname`, `cuit`, `email`, `address`, `cellphone`, `telephone` | Datos de contacto |
| `time_visit` | Horario de visita (texto libre) |
| `id_neighborhood` | Barrio (de ahí sale la zona) |
| `lat`, `long` | Ubicación |
| `type` | `mayorista` o `particular` (`App\Models\Enums\CustomerType`; `fromLegacy` normaliza valores viejos) |
| `birthday` | Fecha de cumpleaños, `DATE` nullable. Ver [cumpleanos.md](cumpleanos.md) |
| `current_balance` | Saldo. Positivo = el cliente debe. **No es asignable en masa**: solo lo mueve `UpdateCustomerBalanceService` |
| `deleted_at` | Soft delete |

Modelo: `app/Models/Customer.php`. Los datos viejos de `type` se convirtieron con `2026_10_18_100000_convert_customers_type_to_explicit_values`.

## Endpoints (`/api/v1`, `auth:api`)

| Método y ruta | Qué hace |
|---|---|
| `GET /customers` | Listado paginado. Filtros: `search` (name/fullname), `id_neighborhood`, `id_zone`, `balance_status` (`debtor`/`creditor`). Orden por `id`, `name`, `fullname`, `email`, `address`, `cellphone`, `telephone`, `cuit`, `type`, `current_balance`, `time_visit`, `birthday`, `created_at`, `updated_at` (default `name`) |
| `GET /customers/options` | Búsqueda liviana para selects (máx. 30; `ids=` hidrata elegidos) |
| `POST /customers` / `PUT /customers/{id}` | Alta y edición. Valida `type` y `birthday`; el resto se asigna con `fillable` |
| `GET /customers/{id}` | Detalle (`CustomerResource`): incluye últimos 5 pedidos y movimientos |
| `GET /customers/{id}/account-entries` | Movimientos de cuenta corriente, ordenados por `occurred_at` y `id` descendente |
| `GET /customers/{id}/account-summary` | Saldo actual |
| `GET /customers/{id}/orders` | Pedidos del cliente |

Archivos: `app/Http/Controllers/Api/V1/CustomersController.php`, `app/Http/Resources/CustomerResource.php`.

## Reglas

| Concepto | Regla |
|---|---|
| Fecha de los movimientos | Los generados por un reparto llevan la fecha del reparto (`delivery_date`), no la del cierre. Ver [cierre-y-ledger.md](../repartos/cierre-y-ledger.md) |
| Saldo | Cambia solo cuando un movimiento pasa a `validated`; no depende de `occurred_at` |
| Edición | `fill($request->all())` solo toma campos `fillable`: un form con un saldo viejo no pisa `current_balance` |

## Frontend

`miyi-dashboard-frontapp`: modelo `models/Customer.ts`, form `app/(dashboard)/clientes/_components/CustomerForm.tsx`, listado `clientes/page.tsx`, detalle `clientes/[id]/page.tsx`.

## Riesgos

- ⚠️ A confirmar: al editar un cliente mayorista, el ticket #30 reporta que el form preselecciona `particular` y lo pisa al guardar. Sin resolver en este documento.
