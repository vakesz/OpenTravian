<?php

declare(strict_types=1);

namespace Model;

use Core\AI;
use Core\Config;
use Core\Database\DB;
use Core\Helper\Notification;
use Game\Buildings\AutoUpgrade;
use Game\Formulas;
use Game\Installer\InstallInput;
use Game\WorldSpeedProfile;
use Game\ResourcesHelper;
use function getGameElapsedSeconds;
use function getGame;
use function getGameSpeed;
use function isInstantFinishEnabled;
use function make_seed;
use const MAP_SIZE;
use function miliseconds;

class NatarsModel
{
    public function runJobs(): void
    {
        $this->releaseArtifacts();
        $this->releaseWWConstructionPlans();
    }

    public function releaseArtifacts(): void
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        if ($config->dynamic->ArtifactsReleased) {
            return;
        }
        if ((int) $db->fetchScalar('SELECT ArtifactsReleased FROM config') !== 1 && time() >= $config->timers->ArtifactsReleaseTime) {
            $arts = new ArtefactsModel();
            $arts->releaseArtifacts();
            Notification::notify("Artifacts Released", "Game artifacts are now released.");
        }
    }

    public function releaseWWConstructionPlans(): void
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        if ($config->dynamic->WWPlansReleased) {
            return;
        }
        if ((int) $db->fetchScalar('SELECT WWPlansReleased FROM config') !== 1 && time() >= $config->timers->wwPlansReleaseTime) {
            $db->checkConnection(true);
            $arts = new ArtefactsModel();
            $arts->releaseWWPlans();
            Notification::notify("WWPlans Released", "Game WWPlans are now released.");
        }
    }

    public function attackNewVillage(int $kid): void
    {
        if (!getGame('attackNewVillageInGreyArea')) {
            return;
        }
        $miliseconds = miliseconds();
        $multiplier = WorldSpeedProfile::natarNewVillageStrengthMultiplier(
            (int)getGameSpeed(),
            (bool)isInstantFinishEnabled()
        );
        $move = new MovementsModel();
        $cap_kid = Formulas::xy2kid(0, 0);
        $wave_arr = [
            1  => [1 => 1000, 1250, 250, 0, 900, 600, 1000, 100, 0, 0, 0],
            2  => [1 => 3, 250, 19, 0, 4, 115, 226, 0, 0, 0, 0],
            3  => [1 => 75, 134, 35, 0, 143, 54, 159, 77, 0, 0, 0],
            4  => [1 => 211, 177, 18, 0, 137, 106, 205, 90, 0, 0, 0],
            5  => [1 => 13, 153, 45, 0, 97, 102, 187, 2, 0, 0, 0],
            6  => [1 => 186, 126, 8, 0, 17, 130, 181, 98, 0, 0, 0],
            7  => [1 => 59, 78, 11, 0, 24, 13, 172, 99, 0, 0, 0],
            8  => [1 => 139, 186, 57, 0, 85, 140, 115, 48, 0, 0, 0],
            9  => [1 => 137, 120, 59, 0, 33, 138, 99, 79, 0, 0, 0],
            10 => [1 => 170, 193, 9, 0, 77, 20, 150, 12, 0, 0, 0],
            11 => [1 => 138, 146, 8, 0, 209, 90, 236, 22, 0, 0, 0],
            12 => [1 => 122, 5, 47, 0, 93, 81, 235, 9, 0, 0, 0],
            13 => [1 => 183, 145, 5, 0, 23, 31, 129, 31, 0, 0, 0],
            14 => [1 => 25, 197, 46, 0, 138, 143, 60, 18, 0, 0, 0],
        ];
        foreach ($wave_arr as $waveNumber => $units) {
            if ($waveNumber > 11) {
                continue;
            }
            $units = array_map(static fn (int $x): int => (int) round($x * $multiplier), $units);
            $time = WorldSpeedProfile::natarNewVillageTravelTime((int) getGameSpeed());
            $increase = intdiv($waveNumber - 1, 3);
            $move->addMovement($cap_kid,
                $kid,
                5,
                $units,
                99,
                99,
                0,
                0,
                0,
                MovementsModel::ATTACKTYPE_NORMAL,
                $miliseconds,
                $miliseconds + (1000 * $time) + $increase);
        }
    }

    public function createFarmVillages(): void
    {
        mt_srand(make_seed());
        $register = new RegisterModel();
        $db = DB::getInstance();
        $count = (int) ceil(Config::getProperty('farms', 'bigFarmsCount') / 4);
        $sides = [1 => 'se', 'ne', 'sw', 'nw'];
        $r = 65;
        for ($z = 1; $z <= 4; ++$z) {
            $find = $register->generateNatarsVillageInSide($sides[$z], $count, $r);
            if (!empty($find)) {
                $db->query("UPDATE available_villages SET occupied=1 WHERE kid IN($find)");
                $kids_array = explode(",", $find);
                foreach ($kids_array as $kid) {
                    $register->createNewFarmVillage((int) $kid, true);
                }
            }
        }
        $count = (int) ceil(Config::getProperty('farms', 'smallFarmsCount') / 4);
        for ($z = 1; $z <= 4; ++$z) {
            $find = $register->generateNatarsVillageInSide($sides[$z], $count, $r);
            if (!empty($find)) {
                $db->query("UPDATE available_villages SET occupied=1 WHERE kid IN($find)");
                $kids_array = explode(",", $find);
                foreach ($kids_array as $kid) {
                    $register->createNewFarmVillage((int) $kid, false);
                }
            }
        }
    }

    /** Farm villages stay out of the starting area: nothing nearer to the map centre than this. */
    private const FARM_MIN_DISTANCE = 30;

    /** Shares of the farm villages by distance band, as [from, to, share] of the span from the minimum distance to the edge of the map. */
    private const FARM_BANDS = [[0.0, 0.3, 0.4], [0.3, 0.6, 0.35], [0.6, 1.0, 0.25]];

    /**
     * Seeds Natar farm villages: spread evenly over the four quarters of the map and over three distance
     * bands (more of them near the middle), each of 50 to 300 inhabitants (the target is 50 to 260, and the level that reaches it adds a few dozen at most)
     * with a small Natar garrison.
     * Returns how many were placed; fewer than asked when the map has no free slot left.
     */
    public function seedFarmVillages(int $count): int
    {
        $count = max(0, min($count, InstallInput::MAX_FARM_VILLAGES));
        if ($count === 0) {
            return 0;
        }
        mt_srand(make_seed());
        $register = new RegisterModel();
        $db = DB::getInstance();
        $edge = (int) MAP_SIZE;
        $span = max(1, $edge - self::FARM_MIN_DISTANCE);
        $sides = ['se', 'ne', 'sw', 'nw'];
        $placed = 0;
        foreach (self::farmCells($count, count($sides)) as $cell => $amount) {
            if ($amount === 0) {
                continue;
            }
            [$from, $to] = self::FARM_BANDS[intdiv($cell, count($sides))];
            $find = $register->generateNatarsVillageInSide(
                $sides[$cell % count($sides)],
                $amount,
                self::FARM_MIN_DISTANCE + (int) round($span * $to),
                self::FARM_MIN_DISTANCE + (int) round($span * $from)
            );
            if ($find === '') {
                continue;
            }
            $db->query("UPDATE available_villages SET occupied=1 WHERE kid IN($find)");
            foreach (explode(',', $find) as $kid) {
                if ($register->createNewFarmVillage((int) $kid, false, mt_rand(50, 260))) {
                    ++$placed;
                }
            }
        }

        return $placed;
    }

    /**
     * How many villages go into each distance-band-by-quarter cell, bands first: the shares of
     * FARM_BANDS split evenly over the quarters, the rounding remainder going to the first cells.
     *
     * @return list<int>
     */
    public static function farmCells(int $count, int $quarters = 4): array
    {
        $cells = [];
        foreach (self::FARM_BANDS as [, , $share]) {
            $band = (int) round($count * $share);
            for ($quarter = 0; $quarter < $quarters; ++$quarter) {
                $cells[] = intdiv($band, $quarters) + ($quarter < $band % $quarters ? 1 : 0);
            }
        }
        // Rounding the three shares can miss the total by one or two; the nearest band absorbs it.
        $cells[0] += $count - array_sum($cells);

        return $cells;
    }

    public function VillageToPOP(int $kid, int $targetPop): void
    {
        $db = DB::getInstance();
        $try = 0;
        $vRow = $db->query("SELECT pop, capital, owner, isWW FROM vdata WHERE kid=$kid")->fetch_assoc();
        if ($vRow['isWW']) {
            return;
        }
        $race = $db->fetchScalar("SELECT race FROM users WHERE id={$vRow['owner']}");
        $AutoUpgrade = new AutoUpgrade($kid, (int) $race);
        if ($vRow['capital']) {
            $AutoUpgrade->isCapital();
        }
        $pop = (int) $vRow['pop'];
        while ($pop <= $targetPop && $try <= 20) {
            ++$try;
            $count = $targetPop - $pop;
            if ($count >= 500) {
                $count = 50;
            } else if ($count >= 250) {
                $count = 35;
            } else if ($count >= 100) {
                $count = 25;
            } else {
                $count = 15;
            }
            for ($i = 1; $i <= $count; ++$i) {
                $AutoUpgrade->upgrade();
            }
            $pop = (int) $db->fetchScalar("SELECT pop FROM vdata WHERE kid=$kid");
        }
        ResourcesHelper::updateVillageResources($kid);
    }

    private function canRunNatars(): bool
    {
        if (getGameElapsedSeconds() <= 0) {
            return false;
        }
        make_seed();

        return true;
    }

    public function handleNatarVillages(): void
    {
        if (!$this->canRunNatars()) {
            return;
        }
        $db = DB::getInstance();
        $interval = match (true) {
            getGameSpeed() <= 100 => 300,
            getGameSpeed() <= 1000 => 200,
            default => 100,
        };
        $now = time();
        $time = $now - 900;
        $result = $db->query("SELECT kid, lastVillageCheck FROM vdata WHERE owner=1 AND isWW=0 AND isFarm=0 AND isArtifact=0 AND lastVillageCheck > 0 AND lastVillageCheck <= $time LIMIT 10");
        while ($row = $result->fetch_assoc()) {
            $kid = (int) $row['kid'];
            $lastCheck = (int) $row['lastVillageCheck'];
            $db->query("UPDATE vdata SET lastVillageCheck=$now WHERE kid=$kid");
            if ($lastCheck <= 10) {
                continue;
            }
            AI::doSomethingRandom($kid, (int) ceil(($now - $lastCheck) / $interval));
        }
    }

    public function handleNatarExpansion(): void
    {
        if (!$this->canRunNatars() || getGameSpeed() > 50 || getGameSpeed() == 1) {
            return;
        }
        $db = DB::getInstance();
        $register = new RegisterModel();
        $sides = [
            'ne' => [0, 90],
            'nw' => [90, 180],
            'se' => [270, 360],
            'sw' => [180, 270],
        ];
        foreach ($sides as $side => $angle) {
            $countNormalVillages = $db->query("SELECT COUNT(kid) FROM available_villages av WHERE occupied=1 AND (angle >= {$angle[0]} AND angle <= {$angle[1]}) AND (SELECT owner FROM vdata v WHERE v.kid=av.kid)>2")->fetch_row()[0];
            $countNatarsVillages = $db->query("SELECT COUNT(av.kid) FROM available_villages av, vdata v WHERE av.occupied=1 AND (av.angle >= {$angle[0]} AND av.angle <= {$angle[1]}) AND v.kid=av.kid AND v.owner=1 AND v.isWW=0 AND v.isFarm=0 AND (SELECT COUNT(id) FROM artefacts WHERE kid=v.kid)=0")->fetch_row()[0];

            $natarsShouldBe = (int) round($countNormalVillages / 2);
            $percent = max(0, getGameElapsedSeconds()) / (getGame('round_length_real') * 86400);
            $pop = $percent * 900;
            $twentyPercent = $pop * 0.15;
            if ($natarsShouldBe > $countNatarsVillages) {
                $newVillageCount = min($natarsShouldBe - (int) $countNatarsVillages, 500);
                for ($i = 1; $i <= $newVillageCount; ++$i) {
                    make_seed();
                    $r = [];

                    $r[0] = 10 + mt_rand(4, 25) * (MAP_SIZE / 400);
                    $r[1] = $r[0] + 5 + mt_rand(15, 200) * (MAP_SIZE / 400);

                    $conditions = [];
                    $conditions[] = 'occupied=0';
                    $conditions[] = "(angle >= {$angle[0]} AND angle <= {$angle[1]})";
                    $conditions[] = "(r >= {$r[0]} AND r <= {$r[1]})";

                    $nearby = '(SELECT COUNT(av2.kid) FROM available_villages av2, vdata v2 WHERE av2.occupied=1 AND ABS(av2.r-av.r) <= 8 AND ABS(av2.angle-av.angle) <= 8 AND v2.kid=av2.kid AND v2.owner>1)';
                    $nearbyNatars = '(SELECT COUNT(av2.kid) FROM available_villages av2, vdata v2 WHERE av2.occupied=1 AND ABS(av2.r-av.r) <= 10 AND ABS(av2.angle-av.angle) <= 8 AND v2.kid=av2.kid AND v2.owner=1 AND v2.isWW=0 AND (SELECT COUNT(id) FROM artefacts WHERE kid=v2.kid)=0)';

                    $conditions[] = "$nearby > 1";
                    $conditions[] = "$nearbyNatars < 3";

                    $village = $db->query("SELECT kid FROM available_villages av WHERE ".implode(" AND ", $conditions)." ORDER BY rand LIMIT 1");

                    if ($village->num_rows) {
                        $kid = (int) $village->fetch_row()[0];
                        $register->createNewNatarVillage($kid);
                        // mt_rand() accepts integer bounds. The expansion curve is
                        // continuous, but population is persisted as an integer;
                        // retain PHP's historical truncation semantics explicitly
                        // instead of relying on an implicit float conversion.
                        $this->VillageToPOP(
                            $kid,
                            mt_rand(
                                (int) ($pop - $twentyPercent),
                                (int) ($pop + $twentyPercent)
                            )
                        );
                    }
                }
            }
        }
    }

}
