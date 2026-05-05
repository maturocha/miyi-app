<?php

namespace App;

class AccountEntryType
{
    const CHARGE = 'charge';
    const PAYMENT = 'payment';
    const CREDIT_NOTE = 'credit_note';
    const DEBIT_ADJUSTMENT = 'debit_adjustment';
    const REFUND = 'refund';

    public static function all(): array
    {
        return [
            self::CHARGE,
            self::PAYMENT,
            self::CREDIT_NOTE,
            self::DEBIT_ADJUSTMENT,
            self::REFUND,
        ];
    }

    public static function manualAllowed(): array
    {
        return [
            self::CREDIT_NOTE,
            self::DEBIT_ADJUSTMENT,
            self::REFUND,
        ];
    }
}
