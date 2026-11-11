<?php

declare(strict_types=1);

namespace Core;

use Core\Database\DB;

class Log
{
    public static function addLog(int $uid, string $type, string $info): void
    {
        DB::getInstance()->run(
            'INSERT INTO `general_log`(`uid`, `type`, `log_info`, `time`) VALUES (?, ?, ?, ?)',
            [$uid, $type, $info, time()]
        );
    }
}
