<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use mysqli_result;

use function array_filter_units;
use function getCustom;
use function getGame;
use function getGameSpeed;
use function logError;

/**
 * Battle helpers: the combat formulas and the village, hero and artifact reads a battle needs. Only ints are
 * interpolated into SQL and rows are read over the text protocol so columns stay strings for BattleModel.
 *
 * @phpstan-import-type DefenderState from BattleModel
 * @phpstan-import-type Row from BattleModel
 * @phpstan-import-type Wave from BattleModel
 * @phpstan-import-type BattleUnits from BattleModel
 * @phpstan-import-type AttackerUnits from BattleModel
 */
class BattleSetter
{
    /**
     * Troops stationed in a village, per unit slot 1-11.
     *
     * @return array<int, int>
     */
    public function getUnits(int|string $kid, bool $oasis = false): array
    {
        $kid = (int) $kid;
        $row = $this->fetchRow("SELECT * FROM units WHERE kid=$kid");

        return $row === null ? array_fill(1, 11, 0) : array_map('intval', array_filter_units($row));
    }

    public function getOwner(int|string $kid): int
    {
        $kid = (int) $kid;

        return (int) DB::getInstance()->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
    }

    /**
     * @return array{id: int, aid: int, goldclub: int, alliance_join_time: int, race: int, cp: int, name: string, escape: int, total_pop: int} the player's battle-relevant columns, numbers as ints; zeros for a missing player
     */
    public function getUser(int|string $uid): array
    {
        $uid = (int) $uid;
        $find = $this->fetchRow("SELECT id, aid, goldclub, alliance_join_time, race, cp, name, escape, total_pop FROM users WHERE id=$uid");
        if ($find === null) {
            return ['id' => 0, 'aid' => 0, 'goldclub' => 0, 'alliance_join_time' => 0, 'race' => 0, 'cp' => 0, 'name' => '', 'escape' => 0, 'total_pop' => 0];
        }

        return [
            'id' => (int) $find['id'],
            'aid' => (int) $find['aid'],
            'goldclub' => (int) $find['goldclub'],
            'alliance_join_time' => (int) $find['alliance_join_time'],
            'race' => (int) $find['race'],
            'cp' => (int) $find['cp'],
            'name' => (string) $find['name'],
            'escape' => (int) $find['escape'],
            'total_pop' => (int) $find['total_pop'],
        ];
    }

    /**
     * @return array<string, mixed>|false false when nothing exists at the map position
     */
    public function getVillageState(int|string $kid): array|false
    {
        $kid = (int) $kid;

        return $this->fetchRow("SELECT fieldtype, oasistype, landscape, occupied FROM wdata WHERE id={$kid} AND (occupied=1 OR oasistype>0)") ?? false;
    }

    public function is_great_celebration_running(int|string $kid): bool
    {
        $kid = (int) $kid;
        $village = $this->fetchRow("SELECT celebration, type FROM vdata WHERE kid=$kid");

        return $village !== null && (int) $village['celebration'] > time() && (int) $village['type'] === 2;
    }

    public function demolish(int|float $lvl, int|float $units, int|float $upg_lvl, int|float $race_durab, int|float $durability_other): int|float
    {
        if ($durability_other != 1) {
            $units = floor($units / $durability_other);
        }
        $units *= $this->bonusCatsRams($upg_lvl);
        $effective_number = (8 * $units - 1) / $race_durab;
        if ($effective_number <= 0) {
            return $lvl;
        }
        if ($effective_number > $lvl * ($lvl + 1)) {
            return 0;
        }

        return round(sqrt(pow($lvl + 0.5, 2) - $effective_number));
    }

    public function bonusCatsRams(int|float $lvl): int|float
    {
        return round(2 * pow(1.0205, $lvl), 2) / 2;
    }

    public function early_ramming(int|float $lvl, int|float $units, int|float $percent, int|float $upg_lvl, int|float $durability, int|float $race_dur): int|float
    {
        if ($durability != 1) {
            $units = floor($units / $durability);
        } // crazy stone
        $points = $units * $this->bonusCatsRams($upg_lvl) * 4 * $percent - 0.5;
        $lvl2 = floor($lvl / 2);
        $pt = [0];
        for ($i = 1; $i <= $lvl2; $i++) {
            $pt[$i] = $pt[$i - 1] + 3 + $lvl * 2 - $i * 4;
        }
        $pt[(int)$lvl2 + 1] = $pt[(int)$lvl2] + 20 + $lvl - 2 * $lvl2;
        $delta = 51;
        for ($i = $lvl2 + 2; $i <= $lvl; $i++) {
            $pt[(int)$i] = $pt[(int)$i - 1] + $delta;
            $delta += 2.5;
        }
        for ($i = 1; $i <= $lvl; $i++) {
            $pt[$i] = floor($pt[$i] * $race_dur);
        }
        $pt [] = 1E99; // some very large number
        $idx = 1;
        while ($points >= $pt[$idx]) {
            $idx++;
        }

        return $lvl - $idx + 1;
    }

    public function sigma(int|float $x): int|float
    {
        return $x >= 1 ? 1 - 0.5 / $x : 0.5 * $x;
    }

    public function remorale(int|float $off, int|float $def, int|float $pop, int|float $wall): int|float
    {
        $pts = round($off) / round($def * $wall);
        $morale = round(pow($pop, -0.2 * min($pts, 1)), 3);
        return $pts * $this->limit(0.667, $morale, 1.0);
    }

    public function limit(int|float $low, int|float $value, int|float $high): int|float
    {
        if ($value < $low) {
            return $low;
        }
        if ($value > $high) {
            return $high;
        }

        return $value;
    }

    public function calc_diffusion(int|float $troops_amount): int|float
    {
        $n = 2 * round(1.8592 - pow($troops_amount, 0.015), 4);
        return $this->limit(1.2578, $n, 1.5);
    }

    public function adduced_def(int|float $def_i, int|float $def_c, int|float $off_i, int|float $off_c): int|float
    {
        $off = $off_i + $off_c;
        if ($off) {
            $ip = round($off_i / $off, 4);
            $cp = round($off_c / $off, 4);
        } else {
            $ip = 0.5;
            $cp = 0.5;
        }
        return ($def_i * $ip + $def_c * $cp);
    }

    /**
     * @return Row|null
     */
    public function getHero(int|string $uid): ?array
    {
        $uid = (int) $uid;
        $hero = $this->fetchRow("SELECT * FROM hero WHERE uid=$uid LIMIT 1");

        return $hero === null ? null : $this->numbersAsNumbers($hero);
    }

    /**
     * The row with numeric strings turned into ints and floats, for the battle formulas.
     *
     * @param array<string, string|int|float|null> $row every column numeric
     * @return Row
     */
    private function numbersAsNumbers(array $row): array
    {
        return array_map(static fn (string|int|float|null $value): int|float => is_numeric($value) ? $value + 0 : 0, $row);
    }

    /**
     * Level of the first building of type $gid in slots 19-38, 0 when absent. Reads the village unless the caller
     * already holds its buildings (slot => ['item_id', 'level']).
     *
     * @param array<int, array{item_id: int|string, level: int|string}>|null $buildings
     */
    public function get_building_lvl(int|string $kid, int $gid, ?array $buildings = null): int
    {
        if (is_array($buildings)) {
            for ($i = 19; $i <= 38; ++$i) {
                if ((int) $buildings[$i]['item_id'] === $gid) {
                    return (int) $buildings[$i]['level'];
                }
            }

            return 0;
        }
        $kid = (int) $kid;
        $row = $this->fetchRow("SELECT * FROM fdata WHERE kid=$kid");
        for ($i = 19; $row !== null && $i <= 38; ++$i) {
            if ((int) $row['f' . $i . 't'] === $gid) {
                return (int) $row['f' . $i];
            }
        }

        return 0;
    }

    public function common_morale(int|float $pop): int|float
    {
        return $this->limit(0.667, round(pow($pop, -0.2), 3), 1.0);
    }

    /**
     * $arr[$name] clamped to $low..$high; $low when the key is missing or not numeric.
     *
     * @param array<int|string, mixed> $arr
     */
    public function int_check_limit(array $arr, int|string $name, int $low, int|float $high): int|float
    {
        if (!array_key_exists($name, $arr)) {
            return $low;
        }

        $value = $arr[$name];

        return $this->limit($low, is_numeric($value) ? (int) $value : $low, $high);
    }

    /**
     * Moves as many of the attackers as there are traps into the traps. Works on any unit-slot keys: battles use
     * slots 1-11, the Rally Point simulator 0-10.
     *
     * @param array<int, int|float> $units attackers per unit slot, reduced by the trapped ones
     * @return array<int, int|float> trapped per unit slot
     */
    public function move_to_traps(array &$units, int|float &$traps): array
    {
        $atkrs_total = array_sum($units);
        $used_traps = min($traps, $atkrs_total);
        $trapped = [];
        $tr = 0;
        foreach ($units as $u => $count) {
            $tr += $trapped[$u] = floor($count * $used_traps / $atkrs_total);
        }
        foreach ($units as $u => $count) {
            if ($tr == $used_traps) {
                break;
            }
            if ($count) {
                $trapped[$u]++;
                $tr++;
            }
        }
        foreach (array_keys($units) as $u) {
            $units[$u] -= $trapped[$u];
        }
        $traps -= array_sum($trapped);

        return $trapped;
    }

    public function art_effect(int|float $value, int|float $limit): int|float
    {
        $value = $this->limit(-$limit, $value, $limit);
        if ($value == 0) {
            return 1;
        }

        return $value;
    }

    public function stat_with_upg(int|float $stat, int|float $upkeep, int|float $lvl): int|float
    {
        if ($lvl <= 0) {
            return $stat;
        }
        $upkeep /= 1.007;

        return round($stat + ($stat + 300 * $upkeep / 7) * (pow(1.007, $lvl) - 1) + $upkeep * 0.0021, 4);
    }

    /**
     * Local siege rule above 50x (docs/world-speed.md): rams and catapults do nothing when under 33% of the
     * defenders die, and a lost attack keeps 0.3-1.5% of its damage. Battles and the Rally Point simulator apply it.
     */
    public function fixSpeedCataRamEffect(int|float $value, int|float $off_losses, int|float $def_losses): int|float
    {
        if (getGameSpeed() <= 50) {
            return $value;
        }
        if ($def_losses > 0) {
            if ($off_losses == 1) {
                if ($def_losses < 0.5) {
                    $value *= 0.003;
                } else if ($def_losses < 0.6) {
                    $value *= 0.005;
                } else if ($def_losses < 0.7) {
                    $value *= 0.008;
                } else if ($def_losses < 0.8) {
                    $value *= 0.01;
                } else if ($def_losses < 0.9) {
                    $value *= 0.015;
                }
            }
            if ($def_losses < 0.33) {
                $value = 0;
            }
        }
        return $value;
    }

    /**
     * Spreads the cages over the animals, any unit-slot keys (battles 1-11, the simulator 0-9).
     *
     * @param array<int, int|float> $units
     * @param array<int, int|float> $caged cage totals per unit slot, increased in place
     */
    public function move_to_cages(array $units, int|float $cages, array &$caged): void
    {
        $total = array_sum($units);
        if ($cages >= $total) {
            $caged = $units;

            return;
        }
        $remainder = array_filter($units);
        $len = count($remainder);
        $min = $remainder === [] ? 0 : min($remainder);
        while ($len and $len * $min < $cages) {
            $cages -= $len * $min;
            foreach (array_keys($remainder) as $idx) {
                $remainder[$idx] -= $min;
                $caged[$idx] += $min;
            }
            $key = array_search(0, $remainder);
            do {
                unset($remainder[$key]);
                $len--;
                $key = array_search(0, $remainder);
            } while ($key !== FALSE);
            $min = $remainder === [] ? 0 : min($remainder);
        }
        if ($cages > 0 and $min != 0) {
            $n = floor($cages / $len);
            $d = $cages - $len * $n;
            foreach ($remainder as $idx => $value) {
                $nn = $n;
                if (--$d >= 0) {
                    $nn++;
                }
                $remainder[$idx] -= $nn;
                $caged[$idx] += $nn;
            }
        }
    }

    /**
     * Sends the troops of a wave away when the village may evade.
     *
     * @param Wave $wave troop wave; its units are zeroed when it evades
     * @return int -1: no troops, 0: evaded, 1: not a capital, 2: troops returned within 10 seconds, 4: village has no
     *             population
     */
    public function escape_units(int|string $kid, int|float|string $end_time, array &$wave, bool $isZeroPop): int
    {
        $kid = (int) $kid;
        $end_time = (int) $end_time;
        $return = false;
        if (!array_sum($wave['units']['num'])) {
            return -1;
        }
        $info = $this->fetchRow("SELECT lastReturn, capital FROM vdata WHERE kid=$kid");
        if ($info === null) {
            return 1;
        }
        if (getGame('checkLastReturnForEvasion')) {
            //check for last troop return.
            $return = (int) $info['lastReturn'] !== 0 && abs(($end_time / 1000) - (int) $info['lastReturn']) <= 10;
        }
        if (!$info['capital']) {
            return 1; //no escape because it's not ur capital village!.
        }
        if ($return) {
            return 2; //you have returning troops within 10 seconds.
        }
        if ($isZeroPop && getCustom('disableEscapeOnVillageZeroPop')) {
            return 4; // your pop is 0
        }
        $seconds = (int) getGame('evasion_time');
        (new MovementsModel())->addMovement(
            $kid,
            0,
            (int) $wave['race'],
            $wave['units']['num'],
            0,
            0,
            0,
            0,
            0,
            MovementsModel::ATTACKTYPE_EVASION,
            $end_time,
            $end_time + $seconds * 1000
        );
        $wave['units']['num'] = array_fill(1, 11, 0);
        $modify = [];
        for ($i = 1; $i <= 11; ++$i) {
            $modify[] = "u{$i}=0";
        }
        DB::getInstance()->query('UPDATE units SET ' . implode(',', $modify) . " WHERE kid={$kid}");

        //no report!
        return 0;
    }

    public function is_capital(int|string $kid): bool
    {
        $kid = (int) $kid;

        return (int) DB::getInstance()->fetchScalar("SELECT capital FROM vdata WHERE kid=$kid") === 1;
    }

    /** Traps the village has built (units.u99). */
    public function getTraps(int|string $kid): int
    {
        $kid = (int) $kid;

        return (int) ($this->fetchRow("SELECT u99 FROM units WHERE kid=$kid")['u99'] ?? 0);
    }

    public function getFilledTrapCount(int|string $kid): int
    {
        $kid = (int) $kid;

        return (int) DB::getInstance()->fetchScalar("SELECT SUM(u1)+SUM(u2)+SUM(u3)+SUM(u4)+SUM(u5)+SUM(u6)+SUM(u7)+SUM(u8)+SUM(u9)+SUM(u10)+SUM(u11) FROM trapped WHERE to_kid=$kid");
    }

    /**
     * Fills $defender with the defending village's buildings (wall, residence level, total cranny capacity, trough,
     * rally point) and returns the traps its trappers allow.
     *
     * @param DefenderState $defender
     * @return int|float|false false when the village has no building row
     */
    public function getDefenderBuildings(int|string $kid, array &$defender, int|string $race): int|float|false
    {
        $kid = (int) $kid;
        $race = (int) $race;
        $totalTraps = 0;
        $defender['totalCranny'] = 0;
        $defender['hdp'] = 0;
        $defender['rallyPoint'] = 0;
        $defender['buildings'] = (new VillageModel())->getBuildingsAssoc($kid);
        if (!sizeof($defender['buildings'])) {
            logError('No defender buildings.');
        }
        for ($i = 19; $i <= 40; ++$i) {
            if (!isset($defender['buildings'][$i])) {
                return false;
            }
            $build = $defender['buildings'][$i];
            if ($build['item_id'] == 34) {
                $defender['stone'] = $build['level'];
            } elseif (in_array($build['item_id'], [25, 26, 44])) {
                $defender['rp'] = $build['level'];
            } elseif ($i == 40) {
                $defender['wall'] = $build['level'];
            } elseif ($race == 3 && $build['item_id'] == 36) {
                $totalTraps += Formulas::trapperValue($build['level']);
            } elseif ($build['item_id'] == 23) {
                $defender['totalCranny'] += Formulas::crannyCAP($build['level'], $race);
            } elseif ($build['item_id'] == 41) {
                $defender['hdp'] = $build['level'];
            } elseif ($build['item_id'] == 16) {
                $defender['rallyPoint'] = $build['level'];
            }
        }

        return $totalTraps;
    }

    /**
     * @param array<int, int|float> $cost wood, clay, iron, crop
     * @param int $mode 0 takes the resources from the village, anything else adds them
     */
    public function modifyVillageResources(int|string $kid, array $cost, int $mode = 0): void
    {
        $this->adjustStock('vdata', (int) $kid, $cost, $mode);
    }

    /**
     * @param array<int, int|float> $cost wood, clay, iron, crop
     * @param int $mode 0 takes the resources from the oasis, anything else adds them
     */
    public function modifyOasisResources(int|string $kid, array $cost, int $mode = 0): void
    {
        $this->adjustStock('odata', (int) $kid, $cost, $mode);
    }

    /**
     * @param 'vdata'|'odata' $table
     * @param array<int, int|float> $cost
     */
    private function adjustStock(string $table, int $kid, array $cost, int $mode): void
    {
        $sign = $mode === 0 ? '-' : '+';
        DB::getInstance()->query("UPDATE $table SET wood=wood$sign{$cost[0]}, clay=clay$sign{$cost[1]}, iron=iron$sign{$cost[2]}, crop=crop$sign{$cost[3]} WHERE kid=$kid");
    }

    /**
     * Equipped hero items (inventory slot => item id).
     *
     * @return Row|null
     */
    public function getInventory(int|string $uid): ?array
    {
        $uid = (int) $uid;
        $inventory = $this->fetchRow("SELECT leftHand, rightHand, body, shoes, horse, bag, helmet FROM inventory WHERE uid=$uid");

        return $inventory === null ? null : $this->numbersAsNumbers($inventory);
    }

    /**
     * Levels of the Tournament Square (14), Great Barracks... (37), Treasury (27) and Trough (41) the attacker has.
     *
     * @return array<int, int>
     */
    public function getAttackerNeededBuildings(int|string $kid): array
    {
        $kid = (int) $kid;
        $result = [14 => 0, 37 => 0, 27 => 0, 41 => 0];
        $count = 4;
        $buildings = $this->fetchRow("SELECT * FROM fdata WHERE kid=$kid");
        for ($i = 19; $buildings !== null && $i <= 38; ++$i) {
            $item_id = (int) $buildings['f' . $i . 't'];
            if (isset($result[$item_id])) {
                $result[$item_id] = (int) $buildings['f' . $i];
                --$count;
            }
            if (!$count) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<int, int|float> $units
     * @return ($isAttacker is true ? AttackerUnits : BattleUnits)
     */
    public function getFullUnits(int|string $kid, array $units, bool $isAttacker = false): array
    {
        $tmp = [
            'num' => $units,
            'smithy' => $this->getSmithy($kid),
            'dead' => array_fill(1, 11, 0),
        ];
        if ($isAttacker) {
            $tmp['trapped'] = array_fill(1, 11, 0);
        }

        return $tmp;
    }

    /**
     * Smithy upgrade levels per unit slot.
     *
     * @return array<int, int>
     */
    public function getSmithy(int|string $kid): array
    {
        $kid = (int) $kid;
        $row = $this->fetchRow("SELECT * FROM smithy WHERE kid=$kid");

        return $row === null ? array_fill(1, 10, 0) : array_map('intval', array_filter_units($row));
    }

    public function getEnforcement(int|string $kid): mysqli_result
    {
        $kid = (int) $kid;
        $result = DB::getInstance()->query("SELECT * FROM enforcement WHERE to_kid=$kid");
        if (!$result instanceof mysqli_result) {
            throw new \RuntimeException("Could not read the reinforcements of village $kid.");
        }

        return $result;
    }

    /**
     * The player's active Cranny and Building-stability artifacts (at most one each).
     *
     * @return list<array{type: mixed, size: mixed, num: mixed}>
     */
    public function getProtectingArtifacts(int|string $uid, int|string $kid): array
    {
        $result = [];
        if (!ArtefactsModel::artifactsReleased()) {
            return $result;
        }
        $uid = (int) $uid;
        $kid = (int) $kid;
        foreach ([ArtefactsModel::ARTIFACT_CRANNY, ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY] as $effectType) {
            $artifact = $this->fetchRow("SELECT num, type, size FROM artefacts WHERE uid=$uid AND IF(size=1, kid=$kid, true) AND active=1 AND status=1 AND effecttype=" . $effectType . ' ORDER BY size ASC LIMIT 1');
            if ($artifact !== null) {
                $result[] = ['type' => $artifact['type'], 'size' => $artifact['size'], 'num' => $artifact['num']];
            }
        }

        return $result;
    }

    /**
     * Size of the player's active Cranny artifact, false when there is none.
     */
    public function randomTargetArtifact(int|string $uid, int|string $kid): string|false
    {
        if (!ArtefactsModel::artifactsReleased()) {
            return false;
        }
        $uid = (int) $uid;
        $kid = (int) $kid;
        $size = DB::getInstance()->fetchScalar("SELECT size FROM artefacts WHERE uid=$uid AND IF(size=1, kid=$kid, true) AND active=1 AND status=1 AND type=" . ArtefactsModel::ARTIFACT_CRANNY . ' ORDER BY size ASC LIMIT 1');

        return $size ? (string) $size : false;
    }

    /**
     * @return array<string, string|int|float|null>|null
     */
    public function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
