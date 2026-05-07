<?php

namespace App\Models\Enums;

/**
 * Allowed payment_method string values stored in the database.
 * Native PHP enums require PHP 8.1+; this project targets PHP 7.4.7.
 */
class PaymentMethod
{
    public const CASH = 'cash';
    public const TRANSFER = 'transfer';
    public const CARD = 'card';
    public const MERCADO_PAGO = 'mercado_pago';
    public const OTHER = 'other';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::CASH,
            self::TRANSFER,
            self::CARD,
            self::MERCADO_PAGO,
            self::OTHER,
        ];
    }
}
