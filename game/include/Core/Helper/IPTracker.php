<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Database\DB;
use Core\Database\GlobalDB;

class IPTracker
{
    /** A player's address row is rewritten at most this often; a request inside the window writes nothing. */
    private const REFRESH_SECONDS = 60;

    /**
     * The canonical text of an IPv4 or IPv6 address (lower-case, compressed IPv6, an IPv4-mapped IPv6 address
     * unwrapped to dotted IPv4), or null when the value is not an address.
     */
    public static function normalize(string|false|null $ip): ?string
    {
        if (!is_string($ip) || $ip === '') {
            return null;
        }
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $packed = substr($packed, 12);
        }
        $text = inet_ntop($packed);

        return $text === false ? null : $text;
    }

    public static function addCurrentIP(int $uid): void
    {
        $ip = self::normalize(WebService::ipAddress());
        if ($ip === null) {
            return;
        }
        $db = DB::getInstance();
        $now = time();
        $last = $db->fetchScalar('SELECT time FROM log_ip WHERE uid=? AND ip=?', [$uid, $ip]);
        if ($last === false) {
            $db->run('INSERT IGNORE INTO log_ip (uid, ip, time) VALUES (?, ?, ?)', [$uid, $ip, $now]);
        } elseif ($now - (int) $last >= self::REFRESH_SECONDS) {
            $db->run('UPDATE log_ip SET time=? WHERE uid=? AND ip=?', [$now, $uid, $ip]);
        }
    }

    public static function blockIP(string $ip, string $reason, int $till = 0): void
    {
        $db = GlobalDB::getInstance();
        $now = time();
        $blockTill = $till === 0 ? 0 : $now + $till;
        $exists = (int) $db->fetchScalar('SELECT COUNT(id) FROM banIP WHERE ip=?', [$ip]);
        if ($exists > 0) {
            $db->run('UPDATE banIP SET time=?, reason=?, blockTill=? WHERE ip=?', [$now, $reason, $blockTill, $ip]);
        } else {
            $db->run('INSERT INTO banIP (ip, reason, time, blockTill) VALUES (?, ?, ?, ?)', [$ip, $reason, $now, $blockTill]);
        }
    }

    public static function unblockIP(string $ip): void
    {
        GlobalDB::getInstance()->run('DELETE FROM banIP WHERE ip=?', [$ip]);
    }

    //checks if the ip is used within 14 days.
    public static function checkForSameIP(int $uid1, int $uid2): bool
    {
        $db = DB::getInstance();
        $ipsOfUid = static function (int $uid) use ($db): array {
            $ips = [];
            $result = $db->query("SELECT ip FROM log_ip WHERE uid=$uid ORDER BY id DESC");
            while ($result && ($row = $result->fetch_assoc())) {
                $ips[] = (string) $row['ip'];
            }

            return $ips;
        };

        return array_intersect($ipsOfUid($uid1), $ipsOfUid($uid2)) !== [];
    }
}
