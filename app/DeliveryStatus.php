<?php

namespace App;

class DeliveryStatus
{
    const NOT_STARTED = 'not_started';
    const IN_PROGRESS = 'in_progress';
    const FINISHED = 'finished';
    const CLOSED = 'closed';

    public static function all(): array
    {
        return [
            self::NOT_STARTED,
            self::IN_PROGRESS,
            self::FINISHED,
            self::CLOSED,
        ];
    }
}
