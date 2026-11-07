# Clientes — cumpleaños

Campo `customers.birthday` para acciones comerciales (ticket #55) y los lugares donde se muestra.

Última revisión: 2026-11-08 (basado en el código actual).

## Dato

| Aspecto | Detalle |
|---|---|
| Columna | `customers.birthday`, `DATE` nullable (migración `2026_11_07_100000_add_birthday_to_customers_table`, idempotente) |
| Modelo | `fillable` + cast `date:Y-m-d` |
| API | `CustomerResource` lo devuelve como `YYYY-MM-DD` o `null`. `store` y `update` devuelven el modelo con el mismo cast |
| Validación | `sometimes\|nullable\|date_format:Y-m-d\|before_or_equal:today` en `store` y `update` |
| Orden del listado | `sortBy=birthday` (ordena por la fecha completa, año incluido) |

Es una fecha sin hora: se guarda y se transporta como `YYYY-MM-DD`, sin timezone.

## Frontend

| Dónde | Comportamiento |
|---|---|
| Form (`CustomerForm.tsx`) | Input `date`, opcional, con `max` = hoy. Vacío se envía como `null` |
| Detalle | `dd/mm/yyyy` con `formatDate` (`helpers/date.ts`, formatea en UTC) |
| Listado | Columna "Cumpleaños". Si el día y mes coinciden con hoy, ícono de torta que titila (también en mobile, junto al nombre) |

Regla de "hoy es su cumpleaños": se compara día y mes sobre el string `YYYY-MM-DD` contra la fecha **local del navegador**. No se usa `new Date('YYYY-MM-DD')`, que en Argentina (UTC-3) corre el día.

## Próximos cumpleaños

El widget de la home (`birthdays`) lo calcula `HomeDashboardService`; reglas y ejemplos en [Inicio](../inicio.md#reglas).

## Limitaciones

- El año de nacimiento se guarda, pero ninguna regla lo usa (no hay edad).
- No existen filtros por mes ni acciones automáticas (mensajes, descuentos).
- El orden por `birthday` en el listado es por fecha completa, no por proximidad en el año.
