<?php

declare(strict_types=1);

namespace Model;

use Core\AI;
use Core\Config;
use Core\Database\DB;
use Game\Hero\HeroExperience;
use function getGameElapsedSeconds;
use function getGameSpeed;
use function make_seed;

class FakeUserModel
{
    public function handleFakeUsers(): void
    {
        if (!$this->canRun()) {
            return;
        }
        $db = DB::getInstance();
        $speed = getGameSpeed();
        $now = time();
        $stmt = $db->query('SELECT id FROM users WHERE access=3 AND lastHeroExpCheck <= ' . ($now - mt_rand(3600, 10800)) . ' LIMIT 10');
        while ($row = $stmt->fetch_assoc()) {
            $uid = (int) $row['id'];
            $db->query("UPDATE users SET lastHeroExpCheck=$now WHERE id=$uid");
            $exp = mt_rand(10, 50) * (int) ceil($speed / 100);
            HeroExperience::add($uid, $exp);
        }
        $interval = match (true) {
            $speed <= 10 => mt_rand(600, 3600),
            $speed <= 100 => mt_rand(200, 400),
            $speed <= 1000 => mt_rand(100, 250),
            default => mt_rand(50, 150),
        };
        $time = $now - 200;
        $results = $db->query("SELECT kid, lastVillageCheck FROM vdata v WHERE lastVillageCheck < $time AND (SELECT access FROM users WHERE id=v.owner)=3 LIMIT 10");
        while ($row = $results->fetch_assoc()) {
            $kid = (int) $row['kid'];
            $lastCheck = (int) $row['lastVillageCheck'];
            $db->query("UPDATE vdata SET lastVillageCheck=$now WHERE kid=$kid");
            if ($lastCheck <= 10) {
                continue;
            }
            AI::doSomethingRandom($kid, (int) ceil(($now - $lastCheck) / $interval));
        }
    }

    private function canRun(): bool
    {
        if (getGameElapsedSeconds() <= 0 || !Config::getProperty('dynamic', 'fakeAccountProcess')) {
            return false;
        }
        make_seed();

        return true;
    }

    public function handleFakeUserExpands(): void
    {
        if (!$this->canRun()) {
            return;
        }
        if (getGameSpeed() <= 10) {
            $elapsedSeconds = getGameElapsedSeconds();
            $interval = match (true) {
                $elapsedSeconds <= 4 * 86400 => 3 * 86400,
                $elapsedSeconds <= 15 * 86400 => 7 * 86400,
                $elapsedSeconds <= 45 * 86400 => 5 * 86400,
                default => 15 * 86400,
            };
        } else {
            $interval = (int) round(mt_rand(65, 165) / 100 * 86400);
        }
        $time = time() - $interval;
        $db = DB::getInstance();
        $register = new RegisterModel();
        $result = $db->query("SELECT id, race, lastVillageExpand FROM users WHERE access=3 AND lastVillageExpand<=$time LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            $uid = (int) $row['id'];
            $lastExpand = (int) $row['lastVillageExpand'];
            $db->query('UPDATE users SET lastVillageExpand=' . ($time + $interval) . " WHERE id=$uid");
            if ($lastExpand === 0) {
                continue;
            }
            $find = $register->generateFakeUserVillage((int) ceil((time() - $lastExpand) / $interval));
            if (empty($find)) {
                continue;
            }
            $db->query("UPDATE available_villages SET occupied=1 WHERE kid IN($find)");
            foreach (explode(',', $find) as $kid) {
                $register->createNewVillage($uid, (int) $row['race'], (int) $kid);
            }
        }
    }
}
