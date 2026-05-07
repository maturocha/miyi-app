<?php

namespace App\Models\Enums;

class DeliveryOrderStatus
{
    const DELIVERED = 'delivered';
    const FAILED = 'failed';
    const UNTOUCHED = 'untouched';
    const SKIPPED = 'skipped';

    public static function all(): array
    {
        return [
            self::DELIVERED,
            self::FAILED,
            self::UNTOUCHED,
            self::SKIPPED,
        ];
    }
}
