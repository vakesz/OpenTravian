<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Game\Helpers\CulturePointsHelper;
use Game\Hero\HeroFace;
use Game\Map\Map;
use Game\ResourcesHelper;
use Game\Tribes;

use function get_random_string;
use function getCustom;
use function getGame;
use function getGameElapsedSeconds;
use function miliseconds;

class RegisterModel
{
    /**
     * @param array<string>|string $names player names, as an array or a comma-separated string
     * @return int how many players were created
     */
    public function addFakeUser(array|string $names): int
    {
        $db = DB::getInstance();
        $m = new OptionModel();
        $k = 0;
        $array = is_array($names) ? $names : explode(',', $names);
        foreach ($array as $name) {
            $name = mb_substr(trim($name), 0, 15);
            if (empty($name)) {
                continue;
            }
            if ($m->nameExists($name) || $m->isNameBlackListed($name)) {
                continue;
            }
            $kid = $this->generateBase('rand', 3);
            if (empty($kid)) {
                continue;
            }
            $playable = \Game\Tribes::playable();
            $race = $playable[mt_rand(0, count($playable) - 1)];
            $uid = $this->addUser($name, sha1(get_random_string(mt_rand(1, 11))), '', $race, $kid, 3);
            if (!$uid) {
                $db->query('UPDATE available_villages SET occupied=0 WHERE kid=' . $kid);
                continue;
            }
            $this->createBaseVillage($uid, $name, $race, $kid);
            $db->run('UPDATE users SET last_login_time=? WHERE id=?', [time() - mt_rand(1, 7200), $uid]);
            $db->run('UPDATE users SET countryFlag=?, lastCountryFlagCheck=? WHERE id=?', [AutomationModel::getRandomCountryFlag(), time(), $uid]);
            $this->populateUserRank($uid);
            ++$k;
        }

        return $k;
    }

    /**
     * Picks (and, unless $occupy is false, claims) a free village slot in the given quarter of the map.
     *
     * @return int|false the village id, false when no slot is free
     */
    public function generateBase(string $sector, int $fieldType = 3, bool $occupy = true, int $tries = 0): int|false
    {
        $minDistance = Formulas::getMapMinDistanceFromCenter();
        $maxDistance = $minDistance + 10 * ($tries + 3);
        if (getGameElapsedSeconds() <= 7200) {
            $minDistance = max($minDistance, 23);
            $maxDistance = ($minDistance + 10) + 10 * $tries;
        }
        $db = DB::getInstance();
        $angle = $this->sectorAngles($sector);
        $conditions = [];
        $conditions[] = 'occupied=0';
        $conditions[] = "fieldtype=$fieldType";
        $conditions[] = "(angle >= {$angle[0]} AND angle <= {$angle[1]})";
        $conditions[] = "(r > 25 AND r >= $minDistance AND r <= $maxDistance)";
        $nearby = '(SELECT COUNT(av.kid) FROM available_villages av WHERE av.occupied=1 AND av.r BETWEEN a.r-6 AND a.r+6 AND av.angle BETWEEN a.angle-8 AND a.angle+8)';
        $conditions[] = "$nearby < 3";
        $conditions = implode(' AND ', $conditions);
        $q = 'SELECT a.kid FROM available_villages a WHERE ' . $conditions . ' ORDER BY a.rand LIMIT 1';
        $kid = $db->fetchScalar($q);
        if ($kid !== false) {
            $kid = (int) $kid;
            // Claim atomically: a concurrent registration that took this slot first leaves 0 rows here.
            if ($occupy) {
                $db->query("UPDATE available_villages SET occupied=1 WHERE kid=$kid AND occupied=0");
                if ($db->affectedRows() !== 1) {
                    return $tries < 16 ? $this->generateBase($sector, $fieldType, $occupy, ++$tries) : false;
                }
            }

            return $kid;
        }
        if ($tries < 16) {
            return $this->generateBase($sector, $fieldType, $occupy, ++$tries);
        }

        return false;
    }

    /**
     * Angle range [from, to] of a map quarter; any other value means the whole map.
     *
     * @return array{int, int}
     */
    private function sectorAngles(string $sector): array
    {
        return match ($sector) {
            'ne', 'no' => [0, 90],
            'se', 'so' => [270, 360],
            'nw' => [90, 180],
            'sw' => [180, 270],
            default => [0, 360],
        };
    }

    /**
     * The start sector Travian marks "recommended" on the activation map: the
     * quarter of the world with the fewest occupied villages. Sectors with no
     * free village left are skipped, and ties are broken at random.
     *
     * @return string one of nw, no, sw, so
     */
    public function getRecommendedSector(): string
    {
        $sectors = ['nw' => [0, 0], 'no' => [0, 0], 'sw' => [0, 0], 'so' => [0, 0]];
        $result = DB::getInstance()->query(
            "SELECT CASE WHEN angle >= 270 THEN 'so' WHEN angle >= 180 THEN 'sw' WHEN angle >= 90 THEN 'nw' ELSE 'no' END AS sector,
                    SUM(occupied = 1) AS taken, SUM(occupied = 0 AND fieldtype = 3) AS free
             FROM available_villages GROUP BY sector"
        );
        while ($result && ($row = $result->fetch_assoc())) {
            $sectors[$row['sector']] = [(int) $row['taken'], (int) $row['free']];
        }
        $open = array_filter($sectors, static fn(array $counts): bool => $counts[1] > 0) ?: $sectors;
        $fewest = min(array_column($open, 0));
        $candidates = array_keys(array_filter($open, static fn(array $counts): bool => $counts[0] === $fewest));

        return $candidates[array_rand($candidates)];
    }

    /**
     * @param bool|int $access account level (1: player, 3: bot; true is the installer's system accounts)
     * @param int|null $uid fixed player id (a round reset keeps its accounts' ids); null takes the next one
     * @return int|false the new player id, false when the (UNIQUE) name is taken
     */
    public function addUser(
        string $name,
        string $password,
        string $email,
        int $race,
        int $kid,
        bool|int $access = 1,
        bool $addHero = true,
        bool $installation = false,
        bool $newsletter = false,
        ?int $uid = null
    ): int|false {
        $name = mb_substr($name, 0, 15);
        $lastupdate = time();
        $cp = Formulas::startingCulturePoints();
        $protection = (int) ($lastupdate + Formulas::getProtectionBasicTime($lastupdate));
        $db = DB::getInstance();
        $gold = (int) Config::getInstance()->gold->startGold;
        $uuid = (string) $db->fetchScalar('SELECT UUID()');
        // users.name is UNIQUE: a concurrent registration with the same name makes this fail.
        try {
            $inserted = $db->run(
                "INSERT INTO users (id, uuid, name, password, email, newsletter, access, gift_gold, signupTime, protection, race, kid, cp, lastupdate, last_adventure_time, location, desc1, desc2, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', '')",
                [$uid, $uuid, $name, $password, $email, $newsletter ? 1 : 0, (int) $access, $gold, $lastupdate, $protection, $race, $kid, $cp, $lastupdate, $lastupdate]
            );
        } catch (\mysqli_sql_exception $exception) {
            if ($exception->getCode() !== 1062) {
                throw $exception;
            }
            $inserted = false;
        }
        if (!$inserted) {
            return false;
        }
        $uid = $uid ?? (int) $db->lastInsertId();
        if (Tribes::summaryColumn($race) !== null) {
            (new SummaryModel())->addPlayerToSummary($race);
        }
        if (!$installation) {
            (new InfoBoxModel())->addInfo($uid, false, 6, '', 0, $protection);
            (new DailyQuestModel())->addDailyQuestRow($uid);
        }
        if ($addHero && $uid) {
            $this->addHero($uid, $kid);
            $this->addHeroFace($uid);
            $this->addHeroInventory($uid);
        }

        return $uid;
    }

    public function addHero(int $uid, int $kid): void
    {
        DB::getInstance()->run('INSERT INTO hero (uid, kid, lastupdate) VALUES (?, ?, ?)', [$uid, $kid, time()]);
    }

    public function addHeroFace(int $uid): void
    {
        $f = new HeroFace();
        $face = $f->decodeAttribute($f->getRandomHeroFace('male'));
        DB::getInstance()->run(
            'INSERT INTO face (uid, headProfile, hairColor, hairStyle, ears, eyebrow, eyes, nose, mouth, beard, scar, tattoo, skinColor, eyeColor, gender, lastupdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uid,
                $face['headProfile'],
                $face['hairColor'],
                $face['hairStyle'],
                $face['ears'],
                $face['eyebrow'],
                $face['eyes'],
                $face['nose'],
                $face['mouth'],
                $face['beard'],
                $face['scar'],
                $face['tattoo'],
                $face['skinColor'],
                $face['eyeColor'],
                $face['gender'],
                time(),
            ]
        );
    }

    public function addHeroInventory(int $uid): void
    {
        DB::getInstance()->run('INSERT INTO inventory (uid, lastupdate) VALUES (?, ?)', [$uid, time()]);
    }

    public function createBaseVillage(int $uid, string $playerName, int $race, int $kid): bool
    {
        $village_name = sprintf(T('Global', 'playerVillageName'), $playerName);
        $result = $this->_createVillage($uid, $race, $kid, $village_name, true, 0, false, false);
        if (!$result) {
            return false;
        }
        $db = DB::getInstance();
        $db->query("UPDATE units SET u11=1 WHERE kid=$kid");
        $this->addResourceFields($kid, true);
        if ($uid <> 2) {
            $this->populateBuildingLevels($kid, (int) getGame('firstVillageCreationFieldsLevel'));
        }
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, false);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP($uid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        (new AdventureModel())->addNewUserAdventures($uid);
        ResourcesHelper::updateVillageResources($kid, false);

        return true;
    }

    private function _createVillage(int $uid, int $race, int $kid, string $village_name, bool $capital, int $expandedFrom, bool $isWW, bool $isArtifact): bool
    {
        if ($kid <= 0) {
            return false;
        }
        $db = DB::getInstance();
        $db->query("UPDATE available_villages SET occupied=1 WHERE kid=$kid");
        CulturePointsHelper::updateUserCP($uid);
        $village_name = str_replace("'", '`', $village_name);
        $maxRes = 800 * getGame('storage_multiplier');
        $minRes = ceil($maxRes * 0.9375);
        $fieldType = (int) $db->fetchScalar("SELECT fieldtype FROM wdata WHERE id=$kid");
        $db->query("UPDATE wdata SET occupied=1 WHERE id=$kid");
        $db->begin_transaction();
        try {
            $good = $db->run(
                'INSERT INTO vdata (kid, owner, fieldtype, name, capital, pop, cp, wood, clay, iron, crop, maxstore, maxcrop, last_loyalty_update, lastmupdate, created, isWW, expandedfrom, lastVillageCheck) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $kid, $uid, $fieldType, $village_name, $capital ? 1 : 0,
                    $minRes, $minRes, $minRes, $minRes, $maxRes, $maxRes,
                    time(), miliseconds(), time(), $isWW ? 1 : 0, $expandedFrom, $isArtifact ? 1 : 0,
                ]
            );
        } catch (\mysqli_sql_exception) {
            $good = false;
        }
        if (!$good || !$good->affected_rows) {
            $db->query("UPDATE available_villages SET occupied=0 WHERE kid=$kid");
            $db->query("UPDATE wdata SET occupied=0 WHERE id=$kid");
            $db->rollback();

            return false;
        }
        $db->commit();
        $this->addUnits($kid, $race);
        $this->addTech($kid);
        $this->addSmithy($kid);
        Map::villageDestroyOrCaptureOrNewVillageUpdate($kid);

        return true;
    }

    public function addUnits(int $kid, int $race, bool $hasHero = false): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM units WHERE kid=?', [$kid]);
        $db->run('INSERT INTO units (kid, race, u11) VALUES (?, ?, ?)', [$kid, $race, $hasHero ? 1 : 0]);
    }

    public function addTech(int $kid): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM tdata WHERE kid=?', [$kid]);
        $db->run('INSERT INTO tdata (kid) VALUES (?)', [$kid]);
    }

    public function addSmithy(int $kid): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM smithy WHERE kid=?', [$kid]);
        $db->run('INSERT INTO smithy (kid) VALUES (?)', [$kid]);
    }

    public function addResourceFields(int $kid, bool $includeRallyPoint): bool
    {
        $fieldsArr = Formulas::getFieldTypeResourceMap($this->getVillageFieldType($kid));
        $queryArr = ['kid' => $kid];
        for ($i = 1; $i <= 18; ++$i) {
            $queryArr["f{$i}t"] = $fieldsArr[$i - 1];
        }
        // All villages begin with a Main Building. Only the registration village
        // receives a Rally Point; settled villages must leave that slot empty.
        $queryArr['f26'] = 1;
        $queryArr['f26t'] = 15;
        if ($includeRallyPoint) {
            $queryArr['f39'] = 1;
            $queryArr['f39t'] = 16;
        }
        DB::getInstance()->query("DELETE FROM fdata WHERE kid=$kid");

        return $this->insertFdata($queryArr);
    }

    /**
     * Inserts the village's building row; keys are fixed column names, values are integers.
     *
     * @param array<string, int> $queryArr
     */
    private function insertFdata(array $queryArr): bool
    {
        $db = DB::getInstance();
        $state = $db->query('INSERT INTO fdata (' . implode(',', array_keys($queryArr)) . ') VALUES (' . implode(',', array_map('intval', array_values($queryArr))) . ')');

        return $state && $db->affectedRows() > 0;
    }

    private function getVillageFieldType(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT fieldtype FROM wdata WHERE id=$kid");
    }

    private function populateBuildingLevels(int $kid, int $level): void
    {
        if ($level > 0) {
            for ($i = 1; $i <= 18; ++$i) {
                BuildingAction::upgrade($kid, $i, $level, false, false);
            }
        }
    }

    private function setVillageCPPOP(int $kid, int|float $pop, int|float $cp): void
    {
        DB::getInstance()->run('UPDATE vdata SET cp=?, pop=? WHERE kid=?', [$cp, $pop, $kid]);
    }

    private function increaseUserPopCP(int $uid, int|float $pop, int|float $cp): void
    {
        $db = DB::getInstance();
        $db->run('UPDATE users SET profileCacheVersion=profileCacheVersion+1, total_villages=total_villages+1, total_pop=total_pop+?, cp_prod=cp_prod+? WHERE id=?', [$pop, $cp, $uid]);
        $aid = (int) $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        if ($aid) {
            $db->run('UPDATE alidata SET week_pop_changes=week_pop_changes+? WHERE id=?', [$pop, $aid]);
        }
    }

    public function populateUserRank(int $id): void
    {
        $db = DB::getInstance();
        if (getCustom('usePopulationAsClimbersRank')) {
            $db->query("UPDATE users SET oldRank=cast(total_pop as SIGNED) WHERE id=$id");

            return;
        }
        $rank = (new StatisticsModel())->getPlayerRankById($id);
        $db->run('UPDATE users SET oldRank=? WHERE id=?', [$rank, $id]);
    }

    /** Closest free village to $kid (any free field type when $fieldType is 0), false when none is free. */
    public function getBestPosition(int $kid, int $fieldType = 3): int|false
    {
        $xy = Formulas::kid2xy($kid);
        $x = (int) $xy['x'];
        $y = (int) $xy['y'];
        $distance = "(SQRT(POW(w.x-$x, 2)+POW(w.y-$y, 2)))";
        $field = $fieldType ? "w.fieldtype=$fieldType" : 'w.fieldtype>0';
        $result = DB::getInstance()->fetchScalar("SELECT kid FROM wdata w, available_villages a WHERE w.occupied=0 AND $field AND a.kid=w.id ORDER BY $distance ASC, a.rand LIMIT 1");

        return $result === false || $result === null ? false : (int) $result;
    }

    /** Comma-separated ids of $count random free villages (bot expansion). */
    public function generateFakeUserVillage(int $count = 1, bool $isMain = false): string
    {
        $fieldType = $isMain ? 'fieldtype=3' : 'fieldtype>0';
        $result = DB::getInstance()->fetchScalar("SELECT GROUP_CONCAT(kid) FROM (SELECT kid FROM available_villages WHERE occupied=0 AND $fieldType AND r>25 ORDER BY rand ASC, r ASC LIMIT $count) as x");

        return rtrim((string) $result, ',');
    }

    public function createNewVillage(int $uid, int $race, int $kid, int $expandedFrom = 0): bool
    {
        $result = $this->_createVillage($uid, $race, $kid, T('Global', 'newVillageName'), false, $expandedFrom, false, false);
        if (!$result) {
            return false;
        }
        $this->addResourceFields($kid, false);
        $this->populateBuildingLevels($kid, (int) getGame('otherVillageCreationFieldsLevel'));
        if (Formulas::isGrayArea($kid)) {
            (new NatarsModel())->attackNewVillage($kid);
        }
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, false);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP($uid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        ResourcesHelper::updateVillageResources($kid, false);
        DB::getInstance()->query("UPDATE users SET kid=$kid WHERE id=$uid");

        return true;
    }

    public function createNewNatarVillage(int $kid, bool $isCapital = false): bool
    {
        $xy = Formulas::kid2xy($kid);
        $village_name = T('Global', 'NatarsName') . ' ' . (implode('|', $xy));
        $result = $this->_createVillage(1, 5, $kid, $village_name, $isCapital, 0, false, false);
        if (!$result) {
            return false;
        }
        $this->addResourceFields($kid, true);
        $this->populateBuildingLevels($kid, (int) getGame('otherVillageCreationFieldsLevel'));
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, false);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP(1, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        ResourcesHelper::updateVillageResources($kid, false);

        return true;
    }

    /**
     * A Natar farm village: resource fields and buildings at the configured small or big farm level, or, with
     * $targetPop, at the lowest level that reaches that population (a level above 10 makes it a capital, the
     * only village whose fields go higher), extra storage, and a small Natar garrison. Conquering it turns it
     * into an ordinary village.
     */
    public function createNewFarmVillage(int $kid, bool $isCapital = false, ?int $targetPop = null): bool
    {
        $db = DB::getInstance();
        $level = null;
        if ($targetPop !== null) {
            $level = self::farmLevelForPopulation($targetPop, Formulas::getFieldTypeResourceMap($this->getVillageFieldType($kid)));
            $isCapital = $level > 10;
        }
        $village_name = sprintf('Farm %03d', 1 + (int) $db->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE isFarm=1'));
        $result = $this->_createVillage(1, 5, $kid, $village_name, $isCapital, 0, false, false);
        if (!$result) {
            return false;
        }
        $level ??= (int) Config::getProperty('farms', $isCapital ? 'bigFarmsResourcesLevel' : 'smallFarmsResourcesLevel');
        $this->addResourceFields($kid, true);
        $this->populateBuildingLevels($kid, $level);
        $countStorage = (int) Config::getProperty('farms', $isCapital ? 'bigFarmsStorageCount' : 'smallFarmsStorageCount');
        $storage = Formulas::storeCAP(20) * $countStorage;
        $db->query("UPDATE vdata SET isFarm=1, extraMaxcrop=extraMaxcrop+$countStorage, maxcrop=maxcrop+$storage, extraMaxstore=extraMaxstore+$countStorage, maxstore=maxstore+$storage WHERE kid=$kid");
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, false);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP(1, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->garrisonFarmVillage($kid, (int) $calculatedCPPOP['pop']);
        ResourcesHelper::updateVillageResources($kid, false);

        return true;
    }

    /**
     * The lowest level of all eighteen resource fields (of these field types) whose population reaches
     * $targetPop, at most 20.
     *
     * @param list<int> $fieldTypes
     */
    public static function farmLevelForPopulation(int $targetPop, array $fieldTypes): int
    {
        for ($level = 1; $level < 20; ++$level) {
            $pop = 0;
            foreach ($fieldTypes as $gid) {
                for ($step = 1; $step <= $level; ++$step) {
                    $pop += Formulas::buildingCropConsumption($gid, $step);
                }
            }
            if ($pop >= $targetPop) {
                return $level;
            }
        }

        return 20;
    }

    /**
     * A few Natar defenders, about one for every eight inhabitants: Pikemen and Guardsmen, six in ten
     * and four in ten. Farms get no Natar AI (it skips isFarm villages), so the garrison never grows.
     */
    private function garrisonFarmVillage(int $kid, int $pop): void
    {
        $defenders = max(5, intdiv($pop, 8));
        $pikemen = intdiv($defenders * 6, 10);
        DB::getInstance()->run('UPDATE units SET u1=?, u3=? WHERE kid=?', [$pikemen, $defenders - $pikemen, $kid]);
    }

    public function createNatarsBaseVillage(int $kid): bool
    {
        $result = $this->_createVillage(1, 5, $kid, T('Global', 'NatarsName'), true, 0, true, false);
        if (!$result) {
            return false;
        }
        $this->addWWVillageFields($kid);
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, true);
        $this->setVillageCPPOP($kid, 781, $calculatedCPPOP['cp']);
        $this->increaseUserPopCP(1, 781, $calculatedCPPOP['cp']);
        DB::getInstance()->query("UPDATE units SET u11=1 WHERE kid=$kid");
        ResourcesHelper::updateVillageResources($kid, false);

        return true;
    }

    private function addWWVillageFields(int $kid): void
    {
        $fieldsArr = Formulas::getFieldTypeResourceMap($this->getVillageFieldType($kid));
        $queryArr = ['kid' => $kid];
        for ($i = 1; $i <= 18; ++$i) {
            $type = $fieldsArr[$i - 1];
            $level = $type <= 3 ? 5 : 6;
            $queryArr["f{$i}"] = $level;
            $queryArr["f{$i}t"] = $type;
        }
        $queryArr = array_merge($queryArr,
            [
                'f99t' => 40,
                'f19'  => 20,
                'f19t' => 10,
                'f20'  => 10,
                'f20t' => 10,
                'f22'  => 15,
                'f22t' => 15,
                'f23'  => 20,
                'f23t' => 11,
                'f27'  => 10,
                'f27t' => 11,
                'f36'  => 1,
                'f36t' => 17,
                'f38'  => 5,
                'f38t' => 23,
                'f39'  => 20,
                'f39t' => 16,
            ]);
        $store = Formulas::storeCAP(20) + Formulas::storeCAP(10);
        $this->insertFdata($queryArr);
        DB::getInstance()->query("UPDATE vdata SET maxstore=$store, maxcrop=$store WHERE kid=$kid");
    }

    /**
     * @param array<int, int|float> $units garrison per unit slot (1-10)
     */
    public function createArtifactVillage(int $kid, int $type, int $size, array $units): bool
    {
        if ($type === 12) {
            $name = T('Artefacts', $type . '.name');
        } elseif ($size === 3) {
            $name = T('Artefacts', $type . '.names.' . $size);
        } else {
            $name = sprintf(T('Artefacts', $type . '.names.' . $size), '');
        }
        $db = DB::getInstance();
        $result = $this->_createVillage(1, 5, $kid, $name, false, 0, false, true);
        if (!$result) {
            return false;
        }
        $this->addArtifactVillageFields($kid, $size);
        $modify = [];
        for ($i = 1; $i <= 10; ++$i) {
            if (!isset($units[$i])) {
                continue;
            }
            $modify[] = "u{$i}=" . (int) $units[$i];
        }
        $store = Formulas::storeCAP(10);
        if ($modify !== []) {
            $db->query('UPDATE units SET ' . implode(',', $modify) . " WHERE kid=$kid");
        }
        $db->query("UPDATE vdata SET maxstore=$store, maxcrop=$store WHERE kid=$kid");
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, false);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP(1, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        ResourcesHelper::updateVillageResources($kid, false);

        return $result;
    }

    private function addArtifactVillageFields(int $kid, int $size): void
    {
        $fieldsArr = Formulas::getFieldTypeResourceMap($this->getVillageFieldType($kid));
        // Regardless of the artifact size the Natar Treasury is level 20 (Artifacts wiki).
        $queryArr = ['kid' => $kid, 'f22' => 20];
        for ($i = 1; $i <= 18; ++$i) {
            $queryArr["f{$i}t"] = $fieldsArr[$i - 1];
        }
        if ($size === 1) {
            $queryArr = array_merge($queryArr,
                [
                    'f1'   => 3,
                    'f2'   => 0,
                    'f3'   => 3,
                    'f4'   => 3,
                    'f5'   => 1,
                    'f6'   => 1,
                    'f7'   => 4,
                    'f8'   => 0,
                    'f9'   => 0,
                    'f10'  => 3,
                    'f11'  => 3,
                    'f12'  => 1,
                    'f13'  => 1,
                    'f14'  => 2,
                    'f15'  => 0,
                    'f16'  => 1,
                    'f17'  => 3,
                    'f18'  => 1,
                    'f22t' => 27,
                    'f24'  => 10,
                    'f24t' => 17,
                    'f26'  => 10,
                    'f26t' => 15,
                    'f27'  => 10,
                    'f27t' => 23,
                    'f28'  => 10,
                    'f28t' => 23,
                    'f30'  => 10,
                    'f30t' => 11,
                    'f31'  => 10,
                    'f31t' => 10,
                    'f39'  => 10,
                    'f39t' => 16,
                ]);
        } elseif ($size === 2 || $size === 3) {
            $queryArr = array_merge($queryArr,
                [
                    'f1'   => 0,
                    'f2'   => 0,
                    'f3'   => 1,
                    'f4'   => 3,
                    'f5'   => 1,
                    'f6'   => 1, //
                    'f7'   => 3,
                    'f8'   => 0,
                    'f9'   => 0, //
                    'f10'  => 3,
                    'f11'  => 3,
                    'f12'  => 1,
                    'f13'  => 1, //
                    'f14'  => 2,
                    'f15'  => 0, //
                    'f16'  => 1, //
                    'f17'  => 3, //
                    'f18'  => 1, //
                    'f22t' => 27,
                    'f24'  => 10,
                    'f24t' => 17,
                    'f26'  => 10,
                    'f26t' => 15,
                    'f27'  => 10,
                    'f27t' => 23,
                    'f28'  => 10,
                    'f28t' => 23,
                    'f30'  => 10,
                    'f30t' => 11,
                    'f31'  => 10,
                    'f31t' => 10,
                    'f39'  => 10,
                    'f39t' => 16,
                ]);
        }
        $this->insertFdata($queryArr);
        $store = Formulas::storeCAP(10);
        DB::getInstance()->query("UPDATE vdata SET maxstore=$store, maxcrop=$store WHERE kid=$kid");
    }

    public function createWWVillage(int $kid): bool
    {
        $db = DB::getInstance();
        $result = $this->_createVillage(1, 5, $kid, T('Global', 'wwName'), false, 0, true, false);
        if (!$result) {
            return false;
        }
        $this->addWWVillageFields($kid);
        $units = WonderOfTheWorldModel::getWWTroops(Formulas::isGrayArea($kid));
        $modify = [];
        for ($i = 1; $i <= 10; ++$i) {
            $modify[] = "u{$i}=" . (int) $units[$i];
        }
        $db->query('UPDATE units SET ' . implode(',', $modify) . " WHERE kid=$kid");
        $calculatedCPPOP = VillageModel::calculateVillageCulturePointsAndPopulation($kid, true);
        $this->setVillageCPPOP($kid, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $this->increaseUserPopCP(1, $calculatedCPPOP['pop'], $calculatedCPPOP['cp']);
        $store = Formulas::storeCAP(20) + Formulas::storeCAP(10);
        $db->query("UPDATE vdata SET maxstore=$store, maxcrop=$store WHERE kid=$kid");
        ResourcesHelper::updateVillageResources($kid, false);

        return $result;
    }

    /** Comma-separated ids of $count random free villages of one map quarter, for the Natar villages. */
    public function generateNatarsVillageInSide(string $sector, int $count = 1, int $distance = 9000, int $minDistance = 15): string
    {
        $angle = $this->sectorAngles($sector);
        $result = DB::getInstance()->fetchScalar("SELECT GROUP_CONCAT(kid) FROM (SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype>0 AND (angle BETWEEN {$angle[0]} AND {$angle[1]}) AND r > $minDistance AND r <= $distance ORDER BY rand ASC LIMIT $count) as villages");

        return rtrim((string)$result, ',');
    }

    public function generateWWNatarsVillage(): int|false
    {
        $kid = DB::getInstance()->fetchScalar('SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype=3 ORDER BY rand ASC, r ASC LIMIT 1');

        return $kid === false || $kid === null ? false : (int) $kid;
    }
}
