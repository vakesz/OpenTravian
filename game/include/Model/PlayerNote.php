<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class PlayerNote
{
    public static function getPlayerNote(int $my_uid, int $uid): string|false
    {
        $note = DB::getInstance()->fetchScalar('SELECT note_text FROM notes WHERE uid=? AND to_uid=?', [$my_uid, $uid]);

        return $note === false ? false : (string) $note;
    }

    public static function setPlayerNote(int $my_uid, int $uid, mixed $text): void
    {
        $db = DB::getInstance();
        $text = strip_tags(is_string($text) ? $text : '');
        $id = $db->fetchScalar('SELECT id FROM notes WHERE uid=? AND to_uid=?', [$my_uid, $uid]);
        if ($id !== false) {
            $db->run('UPDATE notes SET note_text=? WHERE id=?', [$text, (int) $id]);
        } else {
            $db->run('INSERT INTO notes (uid, to_uid, note_text) VALUES (?, ?, ?)', [$my_uid, $uid, $text]);
        }
    }
}
