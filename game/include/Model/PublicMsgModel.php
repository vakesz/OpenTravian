<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class PublicMsgModel
{
    public function haveNewMessage(string $message): void
    {
        $db = DB::getInstance();
        $db->run('UPDATE config SET message=?', [$message]);
        $db->query('UPDATE users SET ok=1 WHERE id>=2');
    }
}
