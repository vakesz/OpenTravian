<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class LinksModel
{
    public function getPlayerLinks(int $uid): \mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM links WHERE uid=$uid ORDER BY pos");
    }

    public function getPlayerLinksCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM links WHERE uid=$uid");
    }

    public function deleteLink(int $uid, int $id): int
    {
        $db = DB::getInstance();
        $db->query("DELETE FROM links WHERE id=$id AND uid=$uid");

        return $db->affectedRows();
    }

    public function addLink(int $uid, mixed $name, mixed $url, int $pos): bool
    {
        $name = strip_tags(is_string($name) ? $name : '');
        $url = filter_var(is_string($url) ? $url : '', FILTER_SANITIZE_URL);
        if (strlen($name) > 30) {
            return false;
        }
        $db = DB::getInstance();
        $name = $db->real_escape_string($name);
        $url = $db->real_escape_string($url);
        $db->query("INSERT INTO links (uid, name, url, pos) VALUES ($uid,'$name','$url',$pos)");

        return true;
    }

    public function modifyLink(int $uid, int $id, mixed $name, mixed $url, int $pos): int|false
    {
        $name = strip_tags(is_string($name) ? $name : '');
        $url = filter_var(is_string($url) ? $url : '', FILTER_SANITIZE_URL);
        if (strlen($name) > 30) {
            return false;
        }
        $db = DB::getInstance();
        $name = $db->real_escape_string($name);
        $url = $db->real_escape_string($url);
        $db->query("UPDATE links set name='$name', url='$url', pos=$pos WHERE id=$id AND uid=$uid");

        return $db->affectedRows();
    }
}
