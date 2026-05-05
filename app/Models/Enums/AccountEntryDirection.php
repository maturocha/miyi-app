<?php

namespace App\Models\Enums;

class AccountEntryDirection
{
    const DEBIT = 'debit';
    const CREDIT = 'credit';

    public static function all(): array
    {
        return [
            self::DEBIT,
            self::CREDIT,
        ];
    }
}
