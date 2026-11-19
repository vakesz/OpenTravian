<?php

namespace Game;

use Core\Database\DB;
use Model\Movements\ReturnProcessor;
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
        if (!($this->villageRow['owner'] > 2)) {
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
        $this->currentCrop = $this->villageRow['crop'] + $elapsedTime * ($cropProduction / 3600000);
        if ($this->currentCrop >= 0) {
            return;
        }
        if($this->userRow['vacationActiveTil'] > time()){
            // vacation
            $crop = -$this->currentCrop + 1000;
            $this->db->query("UPDATE vdata SET crop=crop+$crop WHERE kid={$this->kid}");
            return;
        }
        $truce = TruceDay::isActive() || (TruceDay::getTo() + 86400) > time();
        if ($truce || $this->userRow['access'] == 0 || $this->userRow['id'] == 1) {
            if ($truce || $this->userRow['access'] == 0) {
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

        $wood = $elapsedTime * ($this->villageRow['woodp'] / 3600000);
        $clay = $elapsedTime * ($this->villageRow['clayp'] / 3600000);
        $iron = $elapsedTime * ($this->villageRow['ironp'] / 3600000);

        $this->db->query("UPDATE vdata SET lastmupdate=$now, wood=LEAST(maxstore, wood+$wood), clay=LEAST(maxstore, clay+$clay), iron=LEAST(maxstore, iron+$iron), crop=LEAST(maxcrop, crop+$crop) WHERE kid={$this->kid}");

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
        $stmt = $this->db->query("SELECT kid, maxcrop, owner, woodp, clayp, ironp, cropp, crop, upkeep, pop, lastmupdate, isWW, isFarm FROM vdata WHERE kid={$this->kid} AND owner>1");
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
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM enforcement WHERE uid>0 AND uid != {$this->villageRow['owner']} AND to_kid IN(" . implode(",", $this->villageChildren) . ") ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
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
     * @return (float|int)
     */
    private function starveRow(array $row, $isEnforcement, $isTrapped, $isVillage, $isMovement)
    {
        $order = $this->getUnitKillOrder($row['race'], $row);
        $total_killed = 0;
        $sum = 0;
        for ($i = 1; $i <= 11; ++$i) {
            $sum += (int)($row['u' . $i] ?? 0);
        }
        foreach ($order as $nr) {
            if ($row['u' . $nr] <= 0) continue;
            if ($this->currentCrop > 0) break;
            if ($nr == 11) {
                $heroKid = $isMovement && (int)$row['mode'] === 1 ? $row['to_kid'] : $row['kid'];
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
                    if ($nr == 11) {
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
        for ($i = 1; $i <= 11; ++$i) {
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
        $slots = range(1, 11);
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
                for ($i = 1; $i <= 11; ++$i) {
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
            ["SELECT * FROM enforcement WHERE uid>0 AND uid!={$this->villageRow['owner']} AND to_kid IN($children)", 'enforcement'],
            ["SELECT * FROM enforcement WHERE uid>0 AND uid={$this->villageRow['owner']} AND to_kid IN($children)", 'enforcement'],
            ["SELECT * FROM units WHERE kid={$this->kid}", 'units'],
            ["SELECT * FROM movement WHERE race!=4 AND ((kid={$this->kid} AND mode=0) OR (to_kid={$this->kid} AND mode=1))", 'movement'],
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
            for ($i = 1; $i <= 11; ++$i) {
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
            $part = $row;
            $kind = $row['_kind'];
            if ($kind === 'home') {
                foreach ($row['_parts'] as $candidate) {
                    if ((int)$candidate['u' . $nr] > 0) {
                        $part = $candidate;
                        $kind = $candidate['_kind'];
                        break;
                    }
                }
            }
            $killed = $this->starveRow($part, $kind === 'enforcement', $kind === 'trapped', $kind === 'units', $kind === 'movement');
            if ($killed > 0) {
                $row['u' . $nr] = (int)$row['u' . $nr] - $killed;
                if ($kind === 'home') {
                    foreach ($row['_parts'] as &$candidate) {
                        if ((int)$candidate['id'] === (int)($part['id'] ?? 0) || ($kind === 'units' && $candidate['_kind'] === 'units')) {
                            $candidate['u' . $nr] = (int)$candidate['u' . $nr] - $killed;
                            break;
                        }
                    }
                    unset($candidate);
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
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM enforcement WHERE uid>0 AND uid={$this->villageRow['owner']} AND to_kid IN(" . implode(",", $this->villageChildren) . ") ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
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
        $stmt = $this->db->query("SELECT *, (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10+u11) as `total_sum` FROM movement WHERE race != 4 AND ((kid={$this->kid} AND mode=0) OR (to_kid={$this->kid} AND mode=1)) ORDER BY `total_sum` DESC LIMIT {$this->rowLimit}");
        while ($this->currentCrop < 0 && ($row = $stmt->fetch_assoc())) {
            $this->starveRow($row, false, false, false, true);
        }
    }
}
