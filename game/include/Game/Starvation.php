<?php

namespace Game;

use Core\Database\DB;
use Core\Time;
use Model\Movements\ReturnProcessor;
use Model\MovementsModel;
use Model\VillageModel;
use const SORT_DESC;

use function getGame;
use function getGameSpeed;
use function nrToUnitId;

class Starvation
{
    /** @var mixed */
    private $villageRow = [];
    /** @var mixed */
    private $userRow = [];
    /** @var mixed */
    private $villageChildren = [];
    /** @var mixed */
    private $db;
    /** @var mixed */
    private $kid;
    /** @var (float|int)|int */
    private $currentCrop = 0;
    /** @var mixed */
    private $maxCrop = 0;
    /** @var int */
    private $rowLimit = 20;

    /**
     * @param mixed $kid
     */
    public function __construct($kid)
    {
        if (!getGame("starvation")) {
            return;
        }
        $this->kid = $kid;
        $this->db = DB::getInstance();
        $this->villageRow = $this->getVillage();
        if ($this->villageRow === FALSE) {
            return;
        }
        //  Disable for Natars
        if (!($this->villageRow['owner'] > UserId::LAST_SYSTEM)) {
            return;
        }
        $this->userRow = $this->getUser($this->villageRow['owner']);
        if ($this->userRow === FALSE) {
            return;
        }
        $this->maxCrop = $this->villageRow['maxcrop'];
        $now = miliseconds();
        $elapsedTime = ($now - $this->villageRow['lastmupdate']);
        $cropProduction = $this->villageRow['cropp'] - $this->villageRow['pop'] - $this->villageRow['upkeep'];
        $this->currentCrop = $this->villageRow['crop'] + $elapsedTime * ($cropProduction / Time::HOUR_MS);
        if ($this->currentCrop >= 0) {
            return;
        }
        if($this->userRow['vacationActiveTil'] > time()){
            // vacation
            $crop = -$this->currentCrop + 1000;
            $this->db->query("UPDATE vdata SET crop=crop+$crop WHERE kid={$this->kid}");
            return;
        }
        $truce = TruceDay::isActive() || (TruceDay::getTo() + Time::DAY) > time();
        if ($truce || $this->userRow['access'] == AccessLevel::BANNED || $this->userRow['id'] == UserId::NATARS) {
            if ($truce || $this->userRow['access'] == AccessLevel::BANNED) {
                //no starvation while banned
                $this->db->query("UPDATE vdata SET crop=0 WHERE kid={$this->kid}");
            } else {
                $crop = -$this->currentCrop + 1000;
                $this->db->query("UPDATE vdata SET crop=crop+$crop WHERE kid={$this->kid}");
            }
            return;
        }
        $this->villageChildren = $this->getVillageChildren();
        $this->starveByLegendsOrder(false);
        $this->starveByLegendsOrder(true);

        $crop = $this->currentCrop - $this->villageRow['crop'];

        $wood = $elapsedTime * ($this->villageRow['woodp'] / Time::HOUR_MS);
        $clay = $elapsedTime * ($this->villageRow['clayp'] / Time::HOUR_MS);
        $iron = $elapsedTime * ($this->villageRow['ironp'] / Time::HOUR_MS);

        // Guarded by the lastmupdate we read, so the interval is credited once (see ResourcesHelper::updateVillageResources).
        // On a miss another writer has already accrued it. The crop that the starved troops gave back is
        // still credited: those troops are gone either way. affected_rows is also 0 when the row matched but
        // nothing changed. The fallback then adds the same refund, which is zero or capped at maxcrop, so that is harmless.
        $this->db->query("UPDATE vdata SET lastmupdate=$now, wood=LEAST(maxstore, wood+$wood), clay=LEAST(maxstore, clay+$clay), iron=LEAST(maxstore, iron+$iron), crop=LEAST(maxcrop, crop+$crop) WHERE kid={$this->kid} AND lastmupdate=" . (int)$this->villageRow['lastmupdate']);
        if ((int)$this->db->affectedRows() === 0) {
            $starvedRefund = $crop - $elapsedTime * ($cropProduction / Time::HOUR_MS);
            if ($starvedRefund > 0) {
                $this->db->query("UPDATE vdata SET crop=LEAST(maxcrop, crop+$starvedRefund) WHERE kid={$this->kid}");
            }
        }

        $hdp = VillageModel::getHDP($this->kid);
        ResourcesHelper::updateVillageUpkeep($this->villageRow['owner'], $this->kid, $this->villageRow['isWW'] == 1, $hdp);

        unset($this->userRow);
        unset($this->villageRow);
        unset($this->currentCrop);
        unset($this->villageChildren);
        unset($this->db);
    }

    /**
     * @return mixed
     */
    private function getVillage()
    {
        $stmt = $this->db->query("SELECT kid, maxcrop, owner, woodp, clayp, ironp, cropp, crop, upkeep, pop, lastmupdate, isWW, isFarm FROM vdata WHERE kid={$this->kid} AND owner>" . UserId::NATARS);
        if ($stmt->num_rows) {
            return $stmt->fetch_assoc();
        }
        return false;
    }

    /**
     * @param mixed $uid
     * @return mixed
     */
    private function getUser($uid)
    {
        $stmt = $this->db->query("SELECT id, race, access, vacationActiveTil FROM users WHERE id=$uid");
        if ($stmt->num_rows) {
            return $stmt->fetch_assoc();
        }
        return false;
    }

    /**
     * @return list<mixed>
     */
    private function getVillageChildren()
    {
        $oases = $this->db->query("SELECT kid FROM odata WHERE did={$this->kid} LIMIT 3");
        $oases_array = [];
        while ($row = $oases->fetch_assoc()) {
            $oases_array[] = $row['kid'];
        }
        $oases_array[] = $this->kid;
        return $oases_array;
    }

    private function starveForeignEnforcements(): void
    {
        if ($this->currentCrop >= 0) {
            return;
        }
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM enforcement WHERE uid>" . UserId::NATURE . " AND uid != {$this->villageRow['owner']} AND to_kid IN(" . implode(",", $this->villageChildren) . ") ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, true, false, false, false);
        }
    }

    /**
     * @param array<mixed> $row
     * @param bool $isEnforcement
     * @param bool $isTrapped
     * @param bool $isVillage
     * @param bool $isMovement
     * @param int|null $onlyNr starve only this unit slot (the caller has already chosen it)
     * @return (float|int)
     */
    private function starveRow(array $row, $isEnforcement, $isTrapped, $isVillage, $isMovement, ?int $onlyNr = null)
    {
        $order = $onlyNr !== null ? [$onlyNr] : $this->getUnitKillOrder($row['race'], $row);
        $total_killed = 0;
        $sum = 0;
        for ($i = 1; $i <= Unit::LAST; ++$i) {
            $sum += (int)($row['u' . $i] ?? 0);
        }
        foreach ($order as $nr) {
            if ($row['u' . $nr] <= 0) continue;
            if ($this->currentCrop > 0) break;
            if ($nr == Unit::HERO) {
                $heroKid = $isMovement && (int)$row['mode'] === MovementsModel::MODE_RETURN ? $row['to_kid'] : $row['kid'];
                $experience = (int)$this->db->fetchScalar(
                    'SELECT exp FROM hero WHERE kid=' . (int)$heroKid . ' LIMIT 1',
                    [],
                    0
                );
                $res = Formulas::heroRegenerateCost(Formulas::heroLevel($experience), $row['race']);
            } else {
                $res = Formulas::uTrainingCost(nrToUnitId($nr, $row['race']));
            }
            $maxNumByStorage = ceil(($this->maxCrop + abs($this->currentCrop)) / $res[3]);
            $killNum = 1;
            if ($killNum > $maxNumByStorage) {
                $killNum = $maxNumByStorage;
            }
            if ($killNum > 0) {
                $q = false;
                if ($isEnforcement) {
                    $q = $this->db->query("UPDATE enforcement SET u{$nr}=GREATEST(u{$nr}-$killNum, 0) WHERE id={$row['id']} AND u{$nr}>=$killNum");
                } else if ($isTrapped) {
                    $q = $this->db->query("UPDATE trapped SET u{$nr}=GREATEST(u{$nr}-$killNum, 0) WHERE id={$row['id']} AND u{$nr}>=$killNum");
                } else if ($isVillage) {
                    $q = $this->db->query("UPDATE units SET u{$nr}=GREATEST(u{$nr}-$killNum, 0) WHERE kid={$row['kid']} AND u{$nr}>=$killNum");
                } else if ($isMovement) {
                    $q = $this->db->query("UPDATE movement SET u{$nr}=GREATEST(u{$nr}-$killNum, 0) WHERE id={$row['id']} AND u{$nr}>=$killNum");
                }
                if ($q && $this->db->affectedRows()) {
                    if ($nr == Unit::HERO) {
                        // Returning movements are fed by their destination
                        // village. Their hero is already homed there, not at
                        // the movement's origin (`kid`).
                        $this->db->query("UPDATE hero SET health=0 WHERE kid=$heroKid");
                    }
                    $this->currentCrop += $res[3] * $killNum;
                    $total_killed += $killNum;
                    break;
                }
            }
        }
        if ($sum > 0 && $total_killed >= $sum) {
            if ($isEnforcement) {
                $this->db->query("DELETE FROM enforcement WHERE id={$row['id']}");
            } else if ($isTrapped) {
                $this->db->query("DELETE FROM trapped WHERE id={$row['id']}");
            } else if ($isMovement) {
                $this->settleStarvedMovement($row);
                $this->db->query("DELETE FROM movement WHERE id={$row['id']}");
            }
        }
        return $total_killed;
    }

    /**
     * A movement whose troops all starved never arrives, so its ship and any
     * cargo it was already carrying have to be settled here.
     *
     * @param array<mixed> $row
     */
    private function settleStarvedMovement(array $row): void
    {
        $type = (int) ($row['ship'] ?? 0);
        if (Ships::isShip($type) && Ships::hasHarbor((int) $this->kid)) {
            $column = Ships::column($type);
            $this->db->query('UPDATE units SET ' . $column . '=' . $column . '+1 WHERE kid=' . (int) $this->kid);
        }
        if ((int) ($row['mode'] ?? 0) !== 1) {
            return;
        }
        for ($i = 1; $i <= Unit::LAST; ++$i) {
            $row['u' . $i] = 0;
        }
        new ReturnProcessor($row);
    }

    /**
     * @param mixed $race
     * @param array<mixed> $unitsRow
     * @return list<int>
     */
    private function getUnitKillOrder($race, $unitsRow)
    {
        $slots = range(1, Unit::LAST);
        usort($slots, static function (int $a, int $b) use ($unitsRow): int {
            $count = (int)$unitsRow['u' . $b] <=> (int)$unitsRow['u' . $a];
            return $count !== 0 ? $count : $a <=> $b;
        });

        return $slots;
    }

    /**
     * Largest army first. Equal counts alternate one unit at a time. Inside an
     * army the most numerous type dies, and a tie goes left to right. Trapped
     * troops are a later pass.
     */
    private function starveByLegendsOrder(bool $trapped): void
    {
        $rows = $trapped ? $this->loadTrappedRows() : $this->loadFedRows();
        $turn = 0;
        while ($this->currentCrop < 0) {
            $best = 0;
            $tied = [];
            foreach ($rows as $index => $row) {
                $count = 0;
                for ($i = 1; $i <= Unit::LAST; ++$i) {
                    $count += (int)$row['u' . $i];
                }
                if ($count <= 0) {
                    continue;
                }
                if ($count > $best) {
                    $best = $count;
                    $tied = [$index];
                } elseif ($count === $best) {
                    $tied[] = $index;
                }
            }
            if ($tied === []) {
                return;
            }
            $index = $tied[$turn % count($tied)];
            ++$turn;
            if ($this->starveOne($rows[$index]) <= 0) {
                return;
            }
        }
    }

    /**
     * @return list<array<mixed>>
     */
    private function loadFedRows(): array
    {
        $rows = [];
        $children = implode(',', $this->villageChildren);
        $queries = [
            ["SELECT * FROM enforcement WHERE uid>" . UserId::NATURE . " AND uid!={$this->villageRow['owner']} AND to_kid IN($children)", 'enforcement'],
            ["SELECT * FROM enforcement WHERE uid>" . UserId::NATURE . " AND uid={$this->villageRow['owner']} AND to_kid IN($children)", 'enforcement'],
            ["SELECT * FROM units WHERE kid={$this->kid}", 'units'],
            ["SELECT * FROM movement WHERE race!=" . Tribes::NATURE . " AND ((kid={$this->kid} AND mode=" . MovementsModel::MODE_GOING . ") OR (to_kid={$this->kid} AND mode=" . MovementsModel::MODE_RETURN . "))", 'movement'],
        ];
        foreach ($queries as [$sql, $kind]) {
            $stmt = $this->db->query($sql);
            while ($stmt && ($row = $stmt->fetch_assoc())) {
                $row['_kind'] = $kind;
                $rows[] = $row;
            }
        }
        // Own oasis troops and the village garrison are one army.
        return $this->mergeHomeArmy($rows);
    }

    /**
     * @param list<array<mixed>> $rows
     * @return list<array<mixed>>
     */
    private function mergeHomeArmy(array $rows): array
    {
        $home = null;
        $kept = [];
        foreach ($rows as $row) {
            $ownOasis = $row['_kind'] === 'enforcement'
                && (int)$row['uid'] === (int)$this->villageRow['owner'];
            $garrison = $row['_kind'] === 'units';
            if (!$ownOasis && !$garrison) {
                $kept[] = $row;
                continue;
            }
            if ($home === null) {
                $home = $row;
                $home['_kind'] = 'home';
                $home['_parts'] = [$row];
                continue;
            }
            for ($i = 1; $i <= Unit::LAST; ++$i) {
                $home['u' . $i] = (int)$home['u' . $i] + (int)$row['u' . $i];
            }
            $home['_parts'][] = $row;
        }
        if ($home !== null) {
            $kept[] = $home;
        }

        return $kept;
    }

    /**
     * @return list<array<mixed>>
     */
    private function loadTrappedRows(): array
    {
        $rows = [];
        $stmt = $this->db->query("SELECT * FROM trapped WHERE kid={$this->kid}");
        while ($stmt && ($row = $stmt->fetch_assoc())) {
            $row['_kind'] = 'trapped';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<mixed> $row
     */
    private function starveOne(array &$row): int
    {
        $order = $this->getUnitKillOrder($row['race'] ?? $this->userRow['race'], $row);
        foreach ($order as $nr) {
            if ((int)$row['u' . $nr] <= 0) {
                continue;
            }
            // The home army is the garrison plus own-oasis troops. Kill from the
            // first part that has this unit, and keep that part's count in step.
            $partIndex = null;
            $part = $row;
            $partKind = $row['_kind'];
            if ($partKind === 'home') {
                foreach ($row['_parts'] as $idx => $candidate) {
                    if ((int)$candidate['u' . $nr] > 0) {
                        $part = $candidate;
                        $partKind = $candidate['_kind'];
                        $partIndex = $idx;
                        break;
                    }
                }
            }
            $killed = (int)$this->starveRow($part, $partKind === 'enforcement', $partKind === 'trapped', $partKind === 'units', $partKind === 'movement', $nr);
            if ($killed > 0) {
                $row['u' . $nr] = (int)$row['u' . $nr] - $killed;
                if ($partIndex !== null) {
                    $row['_parts'][$partIndex]['u' . $nr] = (int)$row['_parts'][$partIndex]['u' . $nr] - $killed;
                }
            }

            return $killed;
        }

        return 0;
    }

    private function getCropMultiplier(): float
    {
        return \Game\WorldSpeedProfile::starvationCropMultiplier((int)getGameSpeed());
    }

    private function starveLocalEnforcements(): void
    {
        if ($this->currentCrop >= 0) {
            return;
        }
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM enforcement WHERE uid>" . UserId::NATURE . " AND uid={$this->villageRow['owner']} AND to_kid IN(" . implode(",", $this->villageChildren) . ") ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, true, false, false, false);
        }
    }

    private function starveTrappedTroops(): void
    {
        if ($this->currentCrop >= 0) {
            return;
        }
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM trapped WHERE kid={$this->kid} ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, false, true, false, false);
        }
    }

    private function starveInactiveTroops(): void
    {
        if ($this->currentCrop >= 0) {
            return;
        }
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM units WHERE kid={$this->kid}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, false, false, true, false);
        }
    }

    private function starveAfootTroops(): void
    {
        if ($this->currentCrop >= 0) {
            return;
        }
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM movement WHERE race != " . Tribes::NATURE . " AND ((kid={$this->kid} AND mode=" . MovementsModel::MODE_GOING . ") OR (to_kid={$this->kid} AND mode=" . MovementsModel::MODE_RETURN . ")) ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, false, false, false, true);
        }
    }
}
