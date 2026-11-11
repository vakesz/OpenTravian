<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Exception;

/**
 * types =>
 * 1 => plus
 * 2 => boost 1
 * 3 => boost 2
 * 4 => boost 3
 * 5 => boost 4
 * 6 => Protection
 * 7 => Medals interval
 * 8 => Artifact
 * 9 => WW Plans Release
 * 10 => Game Auto finish Time.
 * 11 => Deleting...
 * 12 => Report Overflow
 * 13 => Vacation
 * 14 => Banned
 * 15 => Catapult
 * 16 => Email not verified
 * 17 => Truce Day
 */
class InfoBoxModel
{
    public function addInfo(int $uid, bool|int $forAll, int $type, string $params, int $showFrom, int $showTo): void
    {
        DB::getInstance()->run(
            'INSERT INTO infobox (uid, forAll, type, params, showFrom, showTo) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, (int) $forAll, $type, $params, $showFrom, $showTo]
        );
    }

    public function deleteInfoByTypeInServer(int $type): void
    {
        DB::getInstance()->run('DELETE FROM infobox WHERE type=?', [$type]);
    }

    public function deleteInfoByType(int $uid, int $type): void
    {
        DB::getInstance()->run('DELETE FROM infobox WHERE uid=? AND type=?', [$uid, $type]);
    }

    public function hasInfoByType(int $uid, int $type): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM infobox WHERE uid=? AND type=?', [$uid, $type]) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMyInfoBox(int $uid): array
    {
        $cache = Caching::getInstance();
        if ($_cache = $cache->get("InfoBox:Private:$uid")) {
            return $_cache;
        }
        $time = time();
        $ignore_ids = $this->getDeletedIds($uid);
        $result = DB::getInstance()->query(
            "SELECT * FROM infobox WHERE (uid=$uid OR forAll=1) AND del=0 AND showFrom <= $time AND (showTo >= $time OR showTo=0) "
            . $this->excludeIds('id', $ignore_ids) . ' ORDER BY type=14 DESC, type=16 DESC, showTo ASC, showFrom DESC'
        );
        $data = $result instanceof \mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $cache->add("InfoBox:Private:$uid", $data, 60);

        return $data;
    }

    /** Comma-separated ids of the public items this player deleted. */
    private function getDeletedIds(int $uid, bool $reCache = false): string
    {
        $memcache = Caching::getInstance();
        if (!$reCache && $_cache = $memcache->get("infoBox:deleted:$uid")) {
            return (string) $_cache;
        }
        $statement = DB::getInstance()->run('SELECT infoId FROM infobox_delete WHERE uid=?', [$uid]);
        $ids = [];
        $result = $statement === false ? false : $statement->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $ids[] = (int) $row['infoId'];
        }
        $return = implode(',', $ids);
        $memcache->set("infoBox:deleted:$uid", $return, 86400);

        return $return;
    }

    public function getUnreadInfoBoxCount(int $uid): int
    {
        $time = time();
        $ignore_ids = $this->getDeletedIds($uid);
        $readIds = $this->getReadIds($uid);
        $ignore_ids = $ignore_ids === '' ? $readIds : $ignore_ids . ',' . $readIds;

        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM infobox WHERE uid=? AND readStatus=0 AND del=0 AND showFrom <= ? AND (showTo >= ? OR showTo=0)'
            . ($ignore_ids !== '' ? ' ' . $this->excludeIds('id', $ignore_ids) : ''),
            [$uid, $time, $time]
        );
    }

    /** Comma-separated ids of the public items this player read. */
    private function getReadIds(int $uid, bool $reCache = false): string
    {
        $memcache = Caching::getInstance();
        if (!$reCache && $_cache = $memcache->get("infoBox:read:$uid")) {
            return (string) $_cache;
        }
        $db = DB::getInstance();
        $return = trim((string)$db->fetchScalar('SELECT GROUP_CONCAT(infoId) FROM infobox_read WHERE uid=?', [$uid]));
        $memcache->set("infoBox:read:$uid", $return, 86400);

        return $return;
    }

    /**
     * Builds "AND column NOT IN(...)" from a comma-separated id list, or '' when the list is empty. Every id is cast
     * to int, so the result is safe to append to a query.
     */
    private function excludeIds(string $column, string $ids): string
    {
        $list = array_map('intval', array_filter(explode(',', $ids), static fn (string $id): bool => $id !== ''));
        if ($list === []) {
            return '';
        }

        return "AND $column NOT IN(" . implode(',', $list) . ')';
    }

    public function setInfoBoxItemAsDeleted(int $uid, int $id): bool
    {
        $db = DB::getInstance();
        $infoBox = $db->run('SELECT * FROM infobox WHERE id=?', [$id]);
        $infoBox = $infoBox === false ? null : $infoBox->get_result()->fetch_assoc();
        if (!is_array($infoBox)) {
            return false;
        }
        if ($infoBox['forAll']) {
            $this->setInfoboxItemsRead($uid, [$id]);
            if (!$this->haveIDeletedPublicOne($uid, $id)) {
                if (!$db->run('INSERT INTO infobox_delete (infoId, uid) VALUES (?, ?)', [$id, $uid])) {
                    return false;
                }
                $this->getDeletedIds($uid, true);
            }
            self::invalidateUserInfoBoxCache($uid);

            return true;
        }
        if ((int) $infoBox['uid'] !== $uid) {
            return false;
        }
        $update = $db->run('UPDATE infobox SET del=1, readStatus=1 WHERE id=? AND type not IN(6, 11, 13, 16) AND forAll=0', [$id]);
        if ($update === false || !$update->affected_rows) {
            return false;
        }
        self::invalidateUserInfoBoxCache($uid);

        return true;
    }

    /**
     * @param array<int|string, int|string> $ids
     */
    public function setInfoboxItemsRead(int $uid, array $ids): void
    {
        $db = DB::getInstance();
        $totalModified = 0;
        $modifiedPublic = false;
        foreach ($ids as $id) {
            $id = (int) $id;
            $find = $db->run('SELECT uid, forAll FROM infobox WHERE id=?', [$id]);
            $row = $find === false ? null : $find->get_result()->fetch_assoc();
            if (!is_array($row)) {
                continue;
            }
            if ($row['forAll'] && !$this->haveIReadPublicOne($uid, $id)) {
                if ($db->run('INSERT INTO infobox_read (infoId, uid) VALUES (?, ?)', [$id, $uid])) {
                    ++$totalModified;
                    $modifiedPublic = true;
                }
            } elseif (!$row['forAll'] && (int) $row['uid'] === $uid) {
                $update = $db->run('UPDATE infobox SET readStatus=1 WHERE uid=? AND id=?', [$uid, $id]);
                $totalModified += $update === false ? 0 : max(0, $update->affected_rows);
            }
        }
        if ($modifiedPublic) {
            $this->getReadIds($uid, true);
        }
        if ($totalModified) {
            self::invalidateUserInfoBoxCache($uid);
        }
    }

    public function haveIReadPublicOne(int $uid, int $activeId): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM infobox_read WHERE infoId=? AND uid=?', [$activeId, $uid]) > 0;
    }

    public static function invalidateUserInfoBoxCache(int $uid): void
    {
        Caching::getInstance()->delete("InfoBox:Private:$uid");
    }

    public static function invalidatePublicInfoBox(): void
    {
        $cache = Caching::getInstance();
        $cache->delete('paymentOffer');
        $cache->delete('InfoBox:Public');
    }

    public static function invalidateAllUsersPrivateInfoBox(): void
    {
        try {
            Caching::getInstance()->deleteByPattern('InfoBox:Private:*');
        } catch (Exception) {
            // The cache backend may be unavailable; stale entries expire on their own.
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublicInfoBox(): array
    {
        $cache = Caching::getInstance();
        if ($_cache = $cache->get('InfoBox:Public')) {
            return $_cache;
        }
        $data = GlobalDB::getInstance()->query('SELECT * FROM infobox WHERE showTo>' . time() . ' ORDER BY showTo DESC')->fetch_all(MYSQLI_ASSOC);
        $cache->add('InfoBox:Public', $data, 1800);

        return $data;
    }

    public function haveIDeletedPublicOne(int $uid, int $activeId): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM infobox_delete WHERE infoId=? AND uid=?', [$activeId, $uid]) > 0;
    }
}
