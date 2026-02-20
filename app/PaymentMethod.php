<?php

namespace App;

class PaymentMethod
{
    const CASH = 'cash';
    const TRANSFER = 'transfer';
    const CARD = 'card';
    const MERCADO_PAGO = 'mercado_pago';
    const OTHER = 'other';

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
