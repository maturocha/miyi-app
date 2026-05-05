<?php

namespace App\Models;

class AccountEntryValidationStatus
{
    public const PENDING = 'pending';

    /** Listo para validar desde la UI (no depende de cierre de reparto). */
    public const NOT_VALIDATED = 'not_validated';

    public const VALIDATED = 'validated';

    public static function all(): array
    {
        return [self::PENDING, self::NOT_VALIDATED, self::VALIDATED];
    }
}
