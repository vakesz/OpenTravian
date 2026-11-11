<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Game\Mail\EventMail;

use function miliseconds;
use function strtolower;

class OptionModel
{
    public const NAME_SHORT = 1;
    public const NAME_LONG = 2;
    public const NAME_EXISTS = 4;
    public const NAME_BLACKLISTED = 8;

    public function updateGame(
        int $uid,
        string $reportFilter,
        string $allianceSettings,
        int $allianceNotificationEnabled,
        string $autoComplete,
        string $display,
        string $timezone
    ): void {
        DB::getInstance()->run(
            'UPDATE users SET reportFilters=?, allianceSettings=?, autoComplete=?, display=?, timezone=?, allianceNotificationEnabled=? WHERE id=?',
            [$reportFilter, $allianceSettings, $autoComplete, $display, $timezone, $allianceNotificationEnabled, $uid]
        );
    }

    public function abortVacation(int $uid): void
    {
        DB::getInstance()->run('UPDATE users SET vacationActiveTil=0 WHERE id=?', [$uid]);
        (new InfoBoxModel())->deleteInfoByType($uid, 13);
    }

    public function enterVacationMode(int $uid, int $days): void
    {
        $till = time() + 86400 * $days;
        $db = DB::getInstance();
        $db->run('UPDATE users SET vacationUsedDays=vacationUsedDays+?, vacationActiveTil=? WHERE id=?', [$days, $till, $uid]);
        (new InfoBoxModel())->addInfo($uid, false, 13, '', time(), $till);
        EventMail::vacationStarted($uid, $till);
        $miliseconds = (int) miliseconds();
        foreach ($this->getPlayerVillagesAsArray($uid) as $kid) {
            // Spies in flight turn around and head home. Integer literals keep the arithmetic exact BIGINT.
            $db->run(
                "UPDATE movement SET to_kid=kid, kid=?, mode=1, end_time=(2*$miliseconds-start_time), start_time=$miliseconds WHERE to_kid=? AND mode=0 AND attack_type=?",
                [$kid, $kid, MovementsModel::ATTACKTYPE_SPY]
            );
        }
    }

    /**
     * @return list<int>
     */
    private function getPlayerVillagesAsArray(int $uid): array
    {
        $statement = DB::getInstance()->run('SELECT kid FROM vdata WHERE owner=?', [$uid]);
        $kids = [];
        $result = $statement === false ? false : $statement->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $kids[] = (int) $row['kid'];
        }

        return $kids;
    }

    /**
     * @return array<int, bool> condition number => satisfied
     */
    public function getVacationConditions(int $uid, bool $hasProtection): array
    {
        $kids = $this->getPlayerVillagesAsArray($uid);

        // Keys follow the reference's display order.
        return [
            1 => $this->countByKids('SELECT COUNT(id) FROM movement WHERE kid IN(%s) AND mode=0', $kids) === 0,
            2 => $this->getInComingMovementsNum($kids) === 0,
            3 => $this->count('SELECT COUNT(id) FROM enforcement WHERE uid=?', [$uid]) === 0,
            4 => $this->countByKids('SELECT COUNT(id) FROM enforcement WHERE to_kid IN(%s) AND race!=4', $kids) === 0,
            5 => !$this->hasRecentlyAnnexedOasis($uid),
            6 => $this->hasPlayerWWVillage($uid) === 0,
            7 => $this->hasPlayerArtifact($uid) === 0,
            8 => !$hasProtection,
            9 => $this->countByKids('SELECT COUNT(id) FROM trapped WHERE to_kid IN(%s)', $kids) === 0,
            10 => !$this->isDeletion($uid),
        ];
    }

    /** True when the player annexed an oasis within the last 48 hours (odata.conquered_time). */
    private function hasRecentlyAnnexedOasis(int $uid): bool
    {
        return $this->count(
            'SELECT COUNT(kid) FROM odata WHERE owner=? AND conquered_time>=?',
            [$uid, time() - 48 * 3600]
        ) > 0;
    }

    public function doesNameMeetRequirements(string $currentName, mixed $name): int
    {
        $newName = strip_tags(is_string($name) ? $name : '');
        $error = 0;
        if ($this->nameExists($newName) && strtolower($currentName) !== strtolower($newName)) {
            $error |= self::NAME_EXISTS;
        } elseif ($this->isNameBlackListed($newName)) {
            $error |= self::NAME_BLACKLISTED;
        }
        if (strlen($newName) < 3) {
            $error |= self::NAME_SHORT;
        } elseif (strlen($newName) > 15) {
            $error |= self::NAME_LONG;
        }

        return $error;
    }

    /**
     * @param list<int> $kids
     */
    private function getInComingMovementsNum(array $kids): int
    {
        return $this->countByKids('SELECT COUNT(id) FROM movement WHERE to_kid IN(%s) AND mode=0 AND attack_type!=' . MovementsModel::ATTACKTYPE_SPY, $kids)
            + $this->countByKids('SELECT COUNT(id) FROM movement WHERE to_kid IN(%s) AND mode=1', $kids);
    }

    public function hasPlayerWWVillage(int $uid): int
    {
        return $this->count('SELECT COUNT(kid) FROM vdata WHERE isWW=1 AND owner=?', [$uid]);
    }

    public function hasPlayerArtifact(int $uid): int
    {
        return $this->count('SELECT COUNT(id) FROM artefacts WHERE uid=?', [$uid]);
    }

    public function addDeleting(int $uid): void
    {
        $timestamp = time() + (int) getGame('deletionTime');
        DB::getInstance()->run('INSERT INTO deleting (uid, time) VALUES (?, ?)', [$uid, $timestamp]);
        (new InfoBoxModel())->addInfo($uid, 0, 11, '', time(), $timestamp);
    }

    public function getPlayerName(int $uid): string
    {
        if (!$uid) {
            return '';
        }

        return (string) DB::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
    }

    /**
     * @return array<int, array{uid: int, name: string, perm: int|string, num: int}>
     */
    public function getSitters(int $uid): array
    {
        $assoc = [1 => ['uid' => 0, 'name' => '', 'perm' => 0, 'num' => 0], 2 => ['uid' => 0, 'name' => '', 'perm' => 0, 'num' => 0]];
        $statement = DB::getInstance()->run(
            'SELECT id, name, sit1Uid, sit2Uid, sit1Permissions, sit2Permissions FROM users WHERE sit1Uid=? OR sit2Uid=?',
            [$uid, $uid]
        );
        $sitters = $statement === false ? false : $statement->get_result();
        $rank = 0;
        while ($sitters && ($row = $sitters->fetch_assoc())) {
            $rank++;
            $num = (int) $row['sit1Uid'] === $uid ? 1 : 2;
            $assoc[$rank] = ['uid' => (int) $row['id'], 'name' => (string) $row['name'], 'perm' => (string) $row['sit' . $num . 'Permissions'], 'num' => $num];
        }

        return $assoc;
    }

    public function getUserByName(string $name): int|false
    {
        $id = DB::getInstance()->fetchScalar('SELECT id FROM users WHERE name=?', [$name]);

        return $id === false ? false : (int) $id;
    }

    /**
     * @return array<int, array{uid: int, name: string, perm: int|string, num: int}>
     */
    public function getMySitters(mixed $sit1Uid, int|string $sit1Permissions, mixed $sit2Uid, int|string $sit2Permissions): array
    {
        $sit1Uid = (int) $sit1Uid;
        $sit2Uid = (int) $sit2Uid;

        return [
            1 => ['uid' => $sit1Uid, 'name' => $this->getPlayerName($sit1Uid), 'perm' => $sit1Permissions, 'num' => 1],
            2 => ['uid' => $sit2Uid, 'name' => $this->getPlayerName($sit2Uid), 'perm' => $sit2Permissions, 'num' => 2],
        ];
    }

    public function getDeletionTimestamp(int $uid): int|false
    {
        $time = DB::getInstance()->fetchScalar('SELECT time FROM deleting WHERE uid=?', [$uid]);

        return $time === false ? false : (int) $time;
    }

    public function cancelDeletion(int $uid): void
    {
        (new InfoBoxModel())->deleteInfoByType($uid, 11);
        DB::getInstance()->run('DELETE FROM deleting WHERE uid=?', [$uid]);
    }

    public function isDeletion(int $uid): bool
    {
        return $this->count('SELECT COUNT(uid) FROM deleting WHERE uid=?', [$uid]) > 0;
    }

    public function nameExists(string $name): bool
    {
        return $this->count('SELECT COUNT(id) FROM users WHERE name=?', [$name])
            + $this->count('SELECT COUNT(id) FROM activation WHERE name=?', [$name]) > 0;
    }

    public function emailExists(string $email): bool
    {
        return $this->count('SELECT COUNT(id) FROM users WHERE email=?', [$email])
            + $this->count('SELECT COUNT(id) FROM activation WHERE email=?', [$email])
            + $this->count('SELECT COUNT(uid) FROM changeEmail WHERE email=?', [$email]) > 0;
    }

    public function changeName(int $uid, string $name): void
    {
        DB::getInstance()->run(
            'UPDATE users SET name=?, total_name_changes=total_name_changes+1 WHERE id=?',
            [htmlspecialchars($name, ENT_QUOTES), $uid]
        );
    }

    public function isNameBlackListed(string $name): bool
    {
        $cache = Caching::getInstance();
        if (!($invalidNames = $cache->get('blackListedNames'))) {
            $invalidNames = explode(',', (string) file_get_contents(FILTERING_PATH . 'blackListedNames.txt'));
            $cache->set('blackListedNames', $invalidNames, 2 * 86400);
        }
        foreach ($invalidNames as $blackListed) {
            similar_text($name, (string) $blackListed, $percent);
            if ($percent > 80.5) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int|string> $parameters
     */
    private function count(string $query, array $parameters): int
    {
        return (int) DB::getInstance()->fetchScalar($query, $parameters, 0);
    }

    /**
     * Runs a COUNT query whose IN(%s) list takes the (integer) village ids; no villages means nothing to count.
     *
     * @param list<int> $kids
     */
    private function countByKids(string $query, array $kids): int
    {
        if ($kids === []) {
            return 0;
        }

        return $this->count(sprintf($query, implode(',', $kids)), []);
    }
}
