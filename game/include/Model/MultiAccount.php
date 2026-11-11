<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;

class MultiAccount
{
    private const LOG_TRADE = 1;
    private const LOG_REINFORCEMENT = 2;
    private const LOG_ATTACK = 3;

    public function runProgress(): void
    {
        $db = DB::getInstance();
        $time = time() - 3600;
        $db->query("DELETE FROM `multiaccount_users` WHERE time <= $time");
        $result = $db->query("SELECT id FROM users WHERE access=1 AND lastMultiAccountCheck < $time ORDER BY lastMultiAccountCheck ASC LIMIT 100");
        $batchQueries = [];
        $bannedIDs = [];
        $batchUIDs = [];
        while ($row = $result->fetch_assoc()) {
            $uid = (int) $row['id'];
            $batchUIDs[] = $uid;
            if (in_array($uid, $bannedIDs, true)) {
                continue;
            }
            $multiAccountResult = $this->checkForSameUIDs($uid);
            if ($multiAccountResult['total'] === 0) {
                continue;
            }
            $bannedIDs[] = $uid;
            $data = [];
            $priority = 0;
            foreach ($multiAccountResult['results'] as $r) {
                $bannedIDs[] = $r['uid'];
                $priority += $r['attacks'] + $r['reinforcements'] + $r['trades'];
                $data[] = implode(',', [$r['uid'], $r['attacks'], $r['reinforcements'], $r['trades']]);
            }
            $batchQueries[] = sprintf("(%d, '%s', %d, %d)", $uid, implode('|', $data), $priority, time());
        }
        if ($batchQueries !== []) {
            $db->query('INSERT IGNORE INTO `multiaccount_users`(`uid`, `data`, `priority`, `time`) VALUES ' . implode(',', $batchQueries));
        }
        if ($batchUIDs !== []) {
            $db->query('UPDATE users SET lastMultiAccountCheck=' . time() . ' WHERE id IN(' . implode(',', $batchUIDs) . ')');
        }
    }

    public function getMultiAccountUsers(int $page, int $pageSize): \mysqli_result|bool
    {
        return DB::getInstance()->query('SELECT * FROM multiaccount_users ORDER BY priority DESC LIMIT ' . (($page - 1) * $pageSize) . ", $pageSize");
    }

    public function getMultiAccountUsersTotal(): int
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM multiaccount_users');
    }

    public function getMultiAccountById(int $id): \mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM multiaccount_users WHERE id=$id");
    }

    /**
     * Other accounts that shared an IP with $uid and interacted with it in the last 14 days.
     *
     * @return array{results: list<array{trades: int, reinforcements: int, attacks: int, uid: int}>, total: int, totalActions: int}
     */
    private function checkForSameUIDs(int $uid): array
    {
        $db = DB::getInstance();
        $user_ips = $db->query("SELECT DISTINCT ip FROM log_ip WHERE uid=$uid ORDER BY id DESC");
        $results = [];
        $done = [];
        $totalActions = 0;
        while ($row = $user_ips->fetch_assoc()) {
            $other_ips = $db->run('SELECT DISTINCT uid FROM log_ip WHERE uid!=? AND ip=?', [$uid, $row['ip']])->get_result();
            while ($r = $other_ips->fetch_assoc()) {
                $other = (int) $r['uid'];
                if (in_array($other, $done, true)) {
                    continue;
                }
                $done[] = $other;
                $counts = [
                    'trades' => $this->countMultiLog($uid, $other, self::LOG_TRADE),
                    'reinforcements' => $this->countMultiLog($uid, $other, self::LOG_REINFORCEMENT),
                    'attacks' => $this->countMultiLog($uid, $other, self::LOG_ATTACK),
                ];
                $actions = array_sum($counts);
                $totalActions += $actions;
                if ($actions > 0) {
                    $results[] = $counts + ['uid' => $other];
                }
            }
        }

        return ['results' => $results, 'total' => count($results), 'totalActions' => $totalActions];
    }

    private function countMultiLog(int $uid, int $to_uid, int $type): int
    {
        return (int) DB::getInstance()->fetchScalar(
            "SELECT COUNT(id) FROM multiaccount_log WHERE ((uid=$uid AND to_uid=$to_uid) OR (to_uid=$uid AND uid=$to_uid)) AND type=$type AND time >= " . strtotime('now -14 days')
        );
    }

    public static function addMultiAccountLog(int $uid, int $to_uid, int $type): void
    {
        if ($uid <= 2 || $to_uid <= 2) {
            return;
        }
        DB::getInstance()->run('INSERT INTO `multiaccount_log`(`uid`, `to_uid`, `type`, `time`) VALUES (?, ?, ?, ?)', [$uid, $to_uid, $type, time()]);
    }
}
