<?php

namespace App;

class OrderStatus
{
    /**
     * Estado cuando se crea el pedido y todavía se está cargando / procesando.
     */
    const IN_PROCESS = 'in_process';

    /**
     * Pedido listo para salir (equivale al antiguo "pending").
     */
    const READY_TO_SHIP = 'ready_to_ship';

    /**
     * Pedido asignado a un reparto concreto.
     */
    const ASSIGNED_TO_DELIVERY = 'assigned_to_delivery';

    /**
     * Pedido en preparación (picking/armado previo a estar listo para salir).
     */
    const IN_PREPARATION = 'in_preparation';

    /**
     * Pedido en reparto (en camino al cliente).
     */
    const OUT_FOR_DELIVERY = 'out_for_delivery';

    /**
     * Pedido entregado con éxito.
     */
    const DELIVERED = 'delivered';

    /**
     * Pedido fallido (no se pudo entregar).
     */
    const FAILED = 'failed';

    /**
     * Pedido cancelado.
     */
    const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::IN_PROCESS,
            self::IN_PREPARATION,
            self::READY_TO_SHIP,
            self::ASSIGNED_TO_DELIVERY,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
        ];
    }
}
