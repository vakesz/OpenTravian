<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\WorldSpeedProfile;

use mysqli_result;

use function getCustom;
use function getGameSpeed;
use function make_seed;

class AdventureModel
{
    /*
     * Adventures spawn randomly around capital and the villages that have an hero's mansion. It is not bound to the hero's home village or to the location where hero is currently situated.
     * */
    /** Adventures never expire: an open adventure stays until the hero has been on it. */
    public function getAdventureCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM adventure WHERE uid=$uid AND end=0");
    }

    public function getHeroVillageRallyPoint(?int $kid): bool
    {
        $kid = (int) $kid;

        return (int) DB::getInstance()->fetchScalar("SELECT f39 FROM fdata WHERE kid=$kid") > 0;
    }

    public function getAdventures(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM adventure WHERE uid=$uid AND end=0 ORDER BY id ASC");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAdventure(int $uid, int $kid): ?array
    {
        $result = DB::getInstance()->query("SELECT * FROM adventure WHERE uid=$uid AND kid=$kid AND end=0");
        $row = $result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }

    public function getHeroVillageTsq(?int $kid): int
    {
        return VillageModel::getTournamentSquireLevel((int) $kid);
    }

    public function checkForNewAdventures(): void
    {
        $db = DB::getInstance();
        $interval = 1 * 3600;
        $now = time();
        $find = $db->query(
            'SELECT id, total_adventures, last_adventure_time, signupTime FROM users'
            . ' WHERE id>1 AND access=1 AND last_adventure_time <= ' . ($now - $interval)
            . ' ORDER BY last_adventure_time ASC LIMIT 100'
        );
        while ($find && ($row = $find->fetch_assoc())) {
            $uid = (int)$row['id'];
            // The lifetime counter can sit above the current entitlement curve after a
            // world-speed/profile change or an earlier over-grant: rebase it, then
            // resume the normal spawn rate.
            $entitled = (int)$this->calcAdventuresUntilNow($now - (int)$row['signupTime']);
            $totalAdventures = (int)$row['total_adventures'];
            if ($totalAdventures > $entitled) {
                $db->query("UPDATE users SET total_adventures={$entitled} WHERE id={$uid}");
                $totalAdventures = $entitled;
            }

            $toGrant = (int)min(max(0, $entitled - $totalAdventures), 10);
            $openCount = (int)$db->fetchScalar("SELECT COUNT(id) FROM adventure WHERE end=0 AND uid={$uid}");

            $db->query("UPDATE users SET last_adventure_time={$now} WHERE id={$uid}");

            if ($openCount > 15) {
                continue;
            }

            $adventureId = $totalAdventures;
            while ($toGrant > 0) {
                $this->addAdventure($uid, $adventureId, $now);
                --$toGrant;
            }
        }
    }

    private function calcAdventuresUntilNow(int|float $seconds_past): float
    {
        $adventures = 0;
        $intervals = $this->getAdventureIntervals();
        $size = sizeof($intervals);
        for ($i = $size; $i >= 1; --$i) {
            $previous = isset($intervals[$i - 1]['time']) ? $intervals[$i - 1]['time'] : 0;
            if ($seconds_past > $previous) {
                $duration = $seconds_past - $previous;
                $days = $duration / 86400;
                $adventures += $days * $intervals[$i]['rate'];
                $seconds_past -= $duration;
            }
        }
        return (int) Config::getProperty('custom', 'startAdventures') + floor($adventures);
    }

    /**
     * @return array<int, array{time: float, rate: int|float}>
     */
    private function getAdventureIntervals(): array
    {
        // Travian Wiki "Adventure": 3 per day on days 0-3, 2 on days 4-16, 1.5 on days 17-62, 1 from day 63. Day counts
        // are 1x days; per-day rates scale with speed through x20, and so do the stage lengths.
        $speed = (int)getGameSpeed();
        $scale = min($speed, 20);
        $base = $scale * WorldSpeedProfile::adventureSpawnMultiplier($speed);
        return [
            1 => ['time' => 4 * 86400 / $scale, 'rate' => 3 * $base],
            2 => ['time' => 17 * 86400 / $scale, 'rate' => 2 * $base],
            3 => ['time' => 63 * 86400 / $scale, 'rate' => 1.5 * $base],
            4 => ['time' => 1000 * 86400, 'rate' => 1 * $base],
        ];
    }

    public function addAdventure(int $uid, int &$adventure_id, int $created, bool $bought = false, ?bool $hard = null): bool
    {
        $db = DB::getInstance();
        $mainKid = (int) $db->fetchScalar("SELECT kid FROM hero WHERE uid=$uid");
        if ((int) $db->fetchScalar("SELECT heroMansion FROM fdata WHERE kid=$mainKid") <= 0) {
            $mainKid = (int) $db->fetchScalar("SELECT kid FROM vdata WHERE owner=$uid AND (capital=1 OR ((SELECT heroMansion FROM fdata WHERE kid=vdata.kid) > 0)) ORDER BY RAND() LIMIT 1");
        }
        if (!$mainKid) {
            return false;
        }
        make_seed();
        $xy = Formulas::kid2xy($mainKid);
        $r = mt_rand(1, 6);
        $max_distance = $r * mt_rand(0, 7);
        $locationKID = 0;
        $adventureFound = false;
        for ($tries = 0; !$adventureFound && $tries < 45; ++$tries) {
            $max_distance += mt_rand(4, 5);
            $location = Formulas::getNearbyXY($xy['x'], $xy['y'], $max_distance);
            $locationKID = (int) Formulas::xy2kid($location['x'], $location['y']);
            if ((int) $db->fetchScalar("SELECT occupied FROM wdata WHERE id=$locationKID") === 1) {
                continue;
            }
            $existing = (int) $db->fetchScalar("SELECT COUNT(id) FROM adventure WHERE end=0 AND uid=$uid AND kid=$locationKID");
            $adventureFound = $existing === 0;
        }
        if (!$adventureFound) {
            return false;
        }
        $dif = $hard === true || ($hard === null && (++$adventure_id) % 4 === 1) ? 1 : 0;
        $inserted = $db->run(
            'INSERT INTO adventure (`uid`, `kid`, `dif`, `created`, `end`) VALUES (?, ?, ?, ?, 0)',
            [$uid, $locationKID, $dif, $created]
        );
        if ($inserted === false) {
            return false;
        }

        return $bought || $db->run('UPDATE users SET total_adventures=total_adventures+1 WHERE id=?', [$uid]) !== false;
    }

    public function checkForAdventure(int $uid, int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM adventure WHERE end=0 AND uid=$uid AND kid=$kid");
    }

    public function addNewUserAdventures(int $uid): void
    {
        $total = (int) getCustom('startAdventures');
        $time = time();
        $adventure_id = 0;
        for ($i = 1; $total > 0; --$total, ++$i) {
            $this->addAdventure($uid, $adventure_id, $time, false, $i === 2);
        }
    }
}
