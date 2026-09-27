# Repartos — permisos

Quién puede hacer qué con un reparto, según `app/Policies/DeliveryPolicy.php`, `AccountEntryPolicy` y los chequeos en controllers.

Última revisión: 2026-09-27 (basado en el código actual).

## Roles (`roles.id`)

| id | key | Nombre |
|---|---|---|
| 1 | `admin` | Administrador |
| 2 | `ventas` | Ventas |
| 3 | `repartidor` | Repartidor |
| 4 | `administracion` | Administración |
| 5 | `farmacia` | Farmacia |

"Privilegiados" en `DeliveryPolicy` y `AccountEntryPolicy`: **`[1, 3, 4]`** (admin, repartidor, administración). "Encargado" = `deliveries.owner_user_id`.

## Matriz

| Acción | Quién | Condición de estado |
|---|---|---|
| Ver listado | Todos | admin y administración ven todo; el resto, solo los repartos donde es encargado |
| Ver detalle | Todos | — |
| Crear | Privilegiados | — |
| Editar datos / pedidos | Privilegiados | `not_started` |
| Agregar pedido | Privilegiados | `not_started` |
| Iniciar | Encargado (si `not_started`) o privilegiados | El servicio rechaza `finished`/`closed` |
| Operar pedido | Encargado (si `in_progress`) o privilegiados | Nunca `closed` |
| Gastos | Encargado (si `in_progress`) o privilegiados | Privilegiados: cualquier estado |
| Finalizar | Encargado (si `in_progress`) o privilegiados | El servicio rechaza `closed` |
| Cerrar | Privilegiados | Solo `finished` |
| Borrar | Solo admin | Nunca `closed` |
| Cobro extra (crear) | Privilegiados | Reparto no `closed` |
| Cobro extra (editar) | Privilegiados o encargado | No validado y reparto no `closed` |

## Qué muestra la UI

El front (`app/(dashboard)/repartos/page.tsx`) es más restrictivo que la API:

| Acción | UI |
|---|---|
| Crear, editar, cerrar | admin, administración |
| Iniciar, operar, finalizar | admin, administración o el encargado |
| Borrar | admin |
| Filtro "Encargado" | admin, administración (usa `GET /users`) |

## ⚠️ A decidir

Por API, un **repartidor** (rol 3) puede, por ser privilegiado:
- finalizar, operar o cerrar repartos **de otros encargados**;
- crear o editar repartos;
- **validar** movimientos manuales `not_validated` (`AccountEntryPolicy::validate`), lo que impacta el saldo.

Incluirlo en privilegiados es necesario para que cargue cobros durante la ruta, pero conviene acotar las demás acciones al encargado o a admin/administración.
