<?php

namespace App\Constants;

final class Status
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const STUDYING = 'กำลังศึกษา';

    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const FAILED = 'failed';

    public static function activeStatuses(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }

    public static function syncStatuses(): array
    {
        return [
            self::RUNNING,
            self::SUCCESS,
            self::FAILED,
        ];
    }

    public static function importStatuses(): array
    {
        return [
            self::PROCESSING,
            self::COMPLETED,
            self::COMPLETED_WITH_ERRORS,
            self::FAILED,
        ];
    }
}
