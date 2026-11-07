<?php

namespace App\Models\Enums;

/**
 * Allowed customers.type string values stored in the database.
 * Native PHP enums require PHP 8.1+; this project targets PHP 7.4.7.
 */
class CustomerType
{
    public const MAYORISTA = 'mayorista';
    public const PARTICULAR = 'particular';

    /** Códigos de una letra que usaban los clientes viejos durante el despliegue. */
    private const LEGACY = [
        'm' => self::MAYORISTA,
        'p' => self::PARTICULAR,
    ];

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::MAYORISTA,
            self::PARTICULAR,
        ];
    }

    /**
     * Traduce el código viejo ('m' / 'p') al valor nuevo; cualquier otro valor se devuelve tal cual.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function fromLegacy($value)
    {
        return is_string($value) && isset(self::LEGACY[$value]) ? self::LEGACY[$value] : $value;
    }
}
