# Documentación de módulos (backend)

Lógica de negocio del API, organizada por módulo. Cada módulo tiene un `index.md` (modelo de datos, archivos, endpoints, riesgos) y un documento por cada lógica.

| Módulo | Qué cubre |
|---|---|
| [Repartos](repartos/index.md) | Armado, operación en ruta, cierre y cuenta corriente |
| [Pedidos](orders/index.md) | Estados, alta y edición, totales, reglas según el reparto |
| [Productos](productos/index.md) | Catálogo, precios, stock y promociones |

## Convenciones

- Documentar lo que **hace** el código, no lo que debería hacer. Si algo no está claro: `⚠️ A confirmar`.
- Cada archivo empieza con un resumen de una línea y `Última revisión: AAAA-MM-DD`.
- Citar código como `app/Services/DeliveryService.php` (con `:línea` si ayuda).
- Tablas para estados, endpoints y reglas; un ejemplo con números para cada fórmula.
- Al cambiar una regla de negocio, actualizar el doc del módulo en el mismo PR.

## Roles

| id | key | Nombre |
|---|---|---|
| 1 | `admin` | Administrador |
| 2 | `ventas` | Ventas |
| 3 | `repartidor` | Repartidor |
| 4 | `administracion` | Administración |
| 5 | `farmacia` | Farmacia |

Los IDs están hardcodeados en policies y controllers. Si cambian en la tabla `roles`, hay que revisar todos los chequeos.

## Otros documentos

- `docs/audits/19-08-2026.md`: auditoría de arquitectura y datos.
