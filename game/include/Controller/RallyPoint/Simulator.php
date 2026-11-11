<?php

namespace Controller\RallyPoint;

use Core\Config;
use Game\Formulas;
use Model\BattleModel;
use Model\BattleSetter;

/**
 * The Rally Point combat simulator. Tribes are 0-based here (race - 1); the combat formulas, the tribe tables and
 * the constants are BattleSetter's and BattleModel's, so a simulation follows the same rules as a battle.
 */
class Simulator
{
    private BattleSetter $battle;

    public function __construct()
    {
        $this->battle = new BattleSetter();
    }

    /**
     * @param array<string, array<int|string, mixed>|bool> $data
     * @return mixed
     */
    public function init($data)
    {
        /*$data = array(
            "R" => false, //is Raid
        "attacker" => array("kid" => "")
            "trapped2killed" => array(),
            "defender" => array(
                "r" => 1-1, //race - 1
                "t" => 86400, //traps
                "p" => 100, //pop
                "rpLevel" => 20, //residence palace level
                "wLevel" => 0, //wall level
                "stone" => 0, //stemez level
                "artifacts" => array(
                    "durability" => 0, //Durability artifact effect ;)
                    "scout" => 0, //Durability artifact effect ;)
                ),
            ),
            "waves" => array() //$no_morale isWW
        );*/
        $pob = $this->battle->int_check_limit($data['defender'], 'r', 0, 8);
        if ($pob == 3) {
            $data['defender']['t'] = $this->battle->int_check_limit($data['defender'],
                "t",
                0,
                8400 * Config::getProperty("game", "trap_multiplier"));
        } else {
            $data['defender']['t'] = 0;
        }
        $trapped2killed = isset($data['trapped2killed']) ? $data['trapped2killed'] : [];
        $def_pop = $this->battle->int_check_limit($data['defender'], "p", 1, 1e8);
        if ($pob == 4) $def_pop = 500;
        $rp_lvl = $this->battle->int_check_limit($data['defender'], 'rpLevel', 0, 20);
        $wl = $this->battle->int_check_limit($data['defender'], 'wLevel', 0, 20);
        $wall_dur = BattleModel::WALL_DURABILITY[$pob + 1];
        $wall_b = \Game\Formulas::WALL_BASE[$pob + 1];
        $stone = 1 + $this->battle->int_check_limit($data['defender'], 'stone', 0, 20) / 10;
        if (!isset($data['defender']['artifacts'])) {
            $data['defender']['artifacts'] = ["durability" => 0, 'scout' => 0];
        }
        $art_dur = $this->art_effect($data['defender']['artifacts'], 'durability', 10);
        $art_scout_def = $this->art_effect($data['defender']['artifacts'], 'scout', 10);
        $def_nature_only = TRUE;
        foreach ($data['waves'] as $idx => $wave) {
            if ($wave['r'] <> 3 && $wave['side'] == "def") {
                $def_nature_only = FALSE;
                break;
            }
        }
        $report = [];
        $def_armies = [];
        foreach ($data['waves'] as $idx => $wave) {
            $no_morale = isset($wave['isWW']);
            for ($u = 0; $u < 10; $u++) {
                $wave['u'][$u] = $this->battle->int_check_limit($wave['u'], $u, 0, 1e99);
            }
            if ($wave['side'] == "off") { // no more than
                $wave['u'][8] = min($wave['u'][8], 3); // 3 senators
                $wave['u'][9] = min($wave['u'][9], 9); // 9 settlers
            }
            // add hero to units
            if (!isset($wave['u'][10]) || !$wave['u'][10]) {//fixed.
                $wave['u'][10] = isset($wave['h']) && $wave['h'] ? 1 : 0;
            }
            $wave['r'] = $r = $this->battle->int_check_limit($wave, 'r', 0, 8);
            // no hero for Nature or Natars
            if ($r == 3 || $r == 4) {
                $wave['u'][10] = 0;
            }
            if ($wave['side'] == "def") {
                $def_armies[$idx] = $wave;
            }
            //last one is always offense units process over here
            if ($wave['side'] == "off") {
                $tr = array_fill(0, 11, 0);
                // cages
                //if is cage attack to an unoccupied oasis let's capture units and let the process free
                if ($data['defender']['r'] == 3 && $def_nature_only && $wave['u'][10] > 0 && $wave['hero']['cages']) {
                    $cages = max(0, (int)$wave['hero']['cages']);
                    $caged = array_fill(0, 10, 0);
                    $total = array_fill(0, 10, 0);
                    foreach ($def_armies as $army) {
                        for ($u = 0; $u < 10; $u++) {
                            $total[$u] += $army['u'][$u];
                        }
                    }
                    $this->battle->move_to_cages($total, $cages, $caged);
                    $this->finalize_losses($report, $def_armies, 0, 0, $wave, $tr, [], ['caged' => $caged]);
                    for ($u = 0; $u < 10; $u++) {
                        if (!$caged[$u]) {
                            continue;
                        }
                        foreach ($def_armies as $idxx => &$army) {
                            if ($caged[$u] < $army['u'][$u]) {
                                $army['u'][$u] -= $caged[$u];
                                $caged[$u] = 0;
                                break;
                            } else {
                                $caged[$u] -= $army['u'][$u];
                                $army['u'][$u] = 0;
                            }
                        }
                    }
                    continue;
                }
                $off_pop = $this->battle->int_check_limit($wave, 'p', 1, 199999);
                $def_pop = max($def_pop, 1);
                $pop_ratio = $off_pop / $def_pop;
                if (getGame("ignoreMoralPointsInAttacks")) {
                    $pop_ratio = 1;
                }
                // check for recon. operation
                $sui = Formulas::getSpyId($r + 1) - 1;
                if ($sui > 0 and array_sum($wave['u']) == $wave['u'][$sui]) {
                    $offense = $wave['u'][$sui] * $this->battle->stat_with_upg(BattleModel::SCOUT_OFF,
                            $this->unitStat($r, $sui, 'cu'),
                            $wave['U'][$sui]);
                    $offense *= $this->art_effect($wave, 'S', 10) * ($no_morale ? 1 : $this->battle->common_morale($pop_ratio));
                    $defense = 0;
                    foreach ($def_armies as $army) {
                        $r = $army['r'];
                        $sui = Formulas::getSpyId($r + 1) - 1;
                        if ($sui < 0) {
                            continue;
                        }
                        $defense += $army['u'][$sui] * $this->battle->stat_with_upg(BattleModel::SCOUT_DEF,
                                $this->unitStat($r, $sui, 'cu'),
                                $army['U'][$sui]);
                    }
                    $defense *= $art_scout_def * round(pow($wall_b, $wl), 3); // wall
                    if ($defense == 0) {
                        $off_losses = 0;
                    } else {
                        $x = pow($offense / $defense, 1.5);
                        $off_losses = $x ? min(1 / $x, 1) : 1;
                    }
                    $this->finalize_losses($report, $def_armies, $off_losses, 0, $wave, $tr, ["isSpy" => TRUE], [
                        'combatStrength' => ['attacker' => $offense, 'defender' => $defense],
                    ]);
                    continue;
                }
                // init
                $b_lvls = $this->target_levels($wave);
                $info = [];
                $lone_attacker = array_sum($wave['u']) == 1;
                // The attacking troops were trapped.
                if ($data['defender']['t']) {
                    $tr = $this->battle->move_to_traps($wave['u'], $data['defender']['t']);
                }
                if (array_sum($tr)) {
                    ///a little change here for unique IDs in freeing trapped
                    $trapped2killed[($data['attacker']['kid'])] = $tr;
                }
                $offense = $this->calc_offense($wave);
                if ($pob == 4) {
                    $offense['b'] *= 1 + ($offense['n'] ?? 0) / 100; // natarian horns
                }
                $this->finalize_stats($offense);
                if (!$offense['t']) {
                    // The attacking side has no offensive strength.
                    if (array_sum($tr)) {
                        $this->finalize_losses($report, $def_armies, 0, 0, $wave, $tr, ['none_return' => ['']]);
                        continue;
                    }
                }
                $defense = $this->calc_total_defense($def_armies);
                $this->finalize_stats($defense);
                $total = array_sum($wave['u']);
                foreach ($def_armies as $army) {
                    $total += array_sum($army['u']);
                }
                $immensity = $this->battle->calc_diffusion($total);
                $defense['t'] = $this->battle->adduced_def($defense['i'], $defense['c'], $offense['i'], $offense['c']);
                if ($pob != 3) {
                    $defense['t'] += 2 * pow($rp_lvl, 2);
                }
                $defense['t'] += BattleModel::BASE_VILLAGE_DEF;
                $defense['t'] += BattleModel::WALL_EXTRA[$pob + 1] * $wl;
                $wall_bonus = round(pow($wall_b, $wl), 3);
                $pts_ratio = $this->battle->remorale($offense['t'], $defense['t'], $no_morale ? 1 : $pop_ratio, $wall_bonus);
                $x = pow($pts_ratio, $immensity);
                // raid
                if ($data['R'] == TRUE) {
                    $off_losses = 1 / (1 + $x);
                    $def_losses = $x / (1 + $x);
                    if ($lone_attacker && $this->lone_dies($offense, $pop_ratio)) {
                        $off_losses = 1;
                    }
                    $this->finalize_losses($report, $def_armies, $off_losses, $def_losses, $wave, $tr, [], [
                        'combatStrength' => ['attacker' => $offense['t'], 'defender' => $defense['t']],
                    ]);
                    continue;
                }
                // normal losses
                $off_losses = $x ? min(1 / $x, 1) : 1;
                $def_losses = min($x, 1);

                $siege_percent = $this->battle->sigma(pow($pts_ratio, 1.5));
                $d = $stone * $art_dur;
                // using rams
                if ($wave['u'][6] && $wl) { //if attacker has ram and defender had wall
                    $up = $wave['U'][6];
                    $rams = $wave['u'][6];
                    $mwl = $this->battle->early_ramming($wl, $rams, $siege_percent, $up, $d, $wall_dur);
                    // recalc wall effect
                    $wall_bonus = round(pow($wall_b, $mwl), 3);
                    $pts_ratio = $this->battle->remorale($offense['t'],
                        $defense['t'],
                        $no_morale ? 1 : $pop_ratio,
                        $wall_bonus);
                    $siege_percent = $this->battle->sigma(pow($pts_ratio, 1.5));
                    $x = pow($pts_ratio, $immensity);
                    // get final wall level
                    $rams = $this->battle->sigma(pow($pts_ratio, $immensity)) * $wave['u'][6];
                    $rams = $this->battle->fixSpeedCataRamEffect($rams, $off_losses, $def_losses);
                    $off_losses = min(1 / $x, 1);
                    $def_losses = min($x, 1);
                    $info['wall'] = [$wl]; //from level
                    $wl = $this->battle->demolish($wl, $rams, $up, $wall_dur, $d);
                    $info['wall'][1] = $wl; //to level
                }
                // using cats
                if ($wave['u'][7] && $b_lvls[0]) { //if has ram and target is selected
                    $info['bl'] = [];
                    $up = $wave['U'][7];
                    $moral = $no_morale ? 1 : $this->battle->limit(0.333, pow($pop_ratio, -0.3), 1);
                    $cats = $wave['u'][7] * $siege_percent * $moral;
                    $cats = $this->battle->fixSpeedCataRamEffect($cats, $off_losses, $def_losses);
                    if ($b_lvls[1] and $wave['u'][7] >= 20) { // two targets
                        $cats /= 2;
                        $info['bl'] = [0, 0, $b_lvls[1], $this->battle->demolish($b_lvls[1], $cats, $up, 1, $d),];
                    }
                    // one target always
                    $info['bl'][0] = $b_lvls[0]; //from level
                    // WW ignores durability artifacts effect
                    if (isset($wave['isWW'])) {
                        $d /= $art_dur;
                    }
                    $info['bl'][1] = $this->battle->demolish($b_lvls[0], $cats, $up, 1, $d); //to level
                }
                $alive_admins = round($wave['u'][8] * (1 - $off_losses));
                // Every playable tribe has a chief unit; Nature (3) and Natars (4) do not conquer.
                if ($alive_admins && $r !== 3 && $r !== 4) {
                    $af = BattleModel::ADMINISTRATOR_EFFECT[$r + 1];
                    $moral_bonus = $no_morale ? 1 : $this->battle->common_morale($pop_ratio);
                    $c = $moral_bonus * $alive_admins / (empty($wave["B"]) || ($r != 1) ? 1 : 2);
                    $info['loy'] = [
                        round($af[0] * $c), //min
                        round($af[1] * $c) //max
                    ];
                }
                if ($lone_attacker and $this->lone_dies($offense, $pop_ratio)) {
                    $off_losses = 1;
                }
                // Free the owner's or alliance's trapped units.
                if (!empty($trapped2killed) and $off_losses < 1) {
                    $info['free'] = [];
                    foreach ($trapped2killed as $trapped_unique => $trapped_wave) {
                        $info['free'][$trapped_unique] = ["sum" => array_sum($trapped_wave), "free" => [],];
                        // Include the hero in the released troops.
                        for ($u = 0; $u < ($trapped_wave[10] ? 11 : 10); $u++) {
                            if (!$trapped_wave[$u]) {
                                continue;
                            }
                            //freed num
                            if (!isset($info['free'][$trapped_unique]['free'][$u])) {
                                $info['free'][$trapped_unique]['free'][$u] = 0;
                            }
                            $info['free'][$trapped_unique]['free'][$u] += ceil($trapped_wave[$u] * 0.75);
                        }
                    }
                    $trapped2killed = [];
                }
                $this->finalize_losses($report, $def_armies, $off_losses, $def_losses, $wave, $tr, $info, [
                    'combatStrength' => ['attacker' => $offense['t'], 'defender' => $defense['t']],
                ]);
            }
        }
        return $report;
    }

    /**
     * @param mixed $arr
     * @param string $name
     * @param int $limit
     * @return mixed
     */
    private function art_effect($arr, $name, $limit)
    {
        // Fool artefacts give fractional multipliers (for example 0.33×): keep them as floats.
        return array_key_exists($name, $arr) ? $this->battle->art_effect((float)$arr[$name], $limit) : 1;
    }

    /**
     * @param mixed $report
     * @param mixed $def_armies
     * @param (float|int)|float|int $off_l
     * @param (float|int)|float|int $def_l
     * @param mixed $offWave
     * @param mixed $trapped
     * @param array<mixed>|array<string, array<mixed>>|array<string, bool>|array<string, list<string>> $info
     * @param array<mixed>|array<string, array<string, (float|int)>>|array<string, array<string, mixed>>|array<string, mixed> $extra
     */
    private function finalize_losses(&$report, &$def_armies, $off_l, $def_l, $offWave, $trapped, $info = [], $extra = []): void
    {
        $revived = [];
        $dead_heroes = ["off" => 0, "def" => []];
        $off_units = $offWave['u']; // copy to save whether there was a hero
        // Bandages revive attackers only.
        foreach ($def_armies as $idx => &$army) {
            $a =& $army['u'];
            for ($u = 0; $u < 10; $u++) {
                $a[$u] = round((1 - $def_l) * $a[$u]);
            }
            if (!isset($dead_heroes['def'][$idx])) {
                $dead_heroes['def'][$idx] = 0;
            }
            $dead_heroes['def'][$idx] += $this->hero_check_kill($army, $def_l);
        }
        if ($_revived = $this->get_revived($offWave, $off_l)) {
            $revived['off'] = $_revived;
        }
        $dead_heroes['off'] = $this->hero_check_kill($offWave, $off_l);
        $arr = [
            'trapped'     => $trapped,
            'revived'     => $revived,
            'losses'      => [$off_l, $def_l],
            'dead_heroes' => $dead_heroes,
            'info'        => $info,
        ];
        foreach ($extra as $field => $value) {
            $arr[$field] = $value;
        }
        $report [] = $arr;
    }

    /**
     * @param mixed $wave
     * @param mixed $losses
     */
    private function hero_check_kill(&$wave, $losses): int
    {
        if (empty($wave['u'][10])) {
            return 0;
        }
        $lossPercent = max($losses * 100 - $wave['hero']['arm'], 0);
        if ($lossPercent >= min(90, $wave['hero']['health'])) {
            $wave['u'][10] = 0;
            return 1;
        } else {
            $wave['hero']['health'] -= $lossPercent;
            return 0;
        }
    }

    /**
     * @param mixed $army
     * @param mixed $loss
     * @return mixed
     */
    private function get_revived(&$army, $loss)
    {
        if (empty($army['h'])) {
            $bandages = 0;
        } else {
            $dead = array_fill(0, 10, 0);
            for ($u = 0; $u < 10; $u++) {
                $un = $army['u'][$u];
                $dead[$u] = $un - round($un * (1 - $loss));
            }
            $total = array_sum($dead);
            $bandages = min($army['hero']['bandage']['num'], ceil($total * $army['hero']['bandage']['eff'] / 100));
            $army['hero']['bandage']['num'] -= $bandages;
        }
        if (!$bandages) {
            return FALSE;
        }
        return $this->battle->move_to_traps($dead, $bandages);
    }

    /**
     * @param mixed $wave
     * @return mixed
     */
    private function target_levels($wave)
    {
        $bl = [0, 0];
        if (isset($wave['b'])) {
            if ($wave['b'][0]) {
                $bl = $wave['b'];
            } elseif ($wave['b'][1]) {
                $bl[0] = $wave['b'][1];
            }
        }
        return $bl;
    }

    /**
     * @param mixed $wave
     * @return mixed
     */
    private function calc_offense(&$wave)
    {
        // init
        $offense = ['i' => 0, 'c' => 0, 'b' => 1];
        // units
        $item_bonus = $this->item_unit_bonus($wave);
        $this->unit_offense($wave, $item_bonus, $offense);
        // hero
        if (!empty($wave['u'][10])) {
            $this->hero_boni($wave, $offense);
        }
        // brewery
        if (!empty($wave['C']) && isset($wave['B'])) {
            $offense['b'] *= 1 + (int)$wave['B'] * 0.01;
        }
        if (isset($wave['M'])) {
            $offense['b'] *= $this->battle->limit(1, (float)$wave['M'], 1.1);
        }
        return $offense;
    }

    /**
     * @param mixed $wave
     * @return array<int, int>|list<int>
     */
    private function item_unit_bonus($wave)
    {
        $item_bonus = array_fill(0, 10, 0);
        if (empty($wave['u'][10])) {
            return $item_bonus;
        }
        $bonus = $wave['hero']['unitBonus'] ?? null;
        if (!is_array($bonus)) {
            return $item_bonus;
        }
        $unitId = (int)($bonus['unitId'] ?? 0);
        $power = (int)($bonus['power'] ?? 0);
        if ($unitId <= 0 || $power <= 0 || unitIdToTribe($unitId) !== (int)$wave['r'] + 1) {
            return $item_bonus;
        }
        $unitIndex = unitIdToNr($unitId) - 1;
        if ($unitIndex >= 0 && $unitIndex < 10) {
            $item_bonus[$unitIndex] = $power;
        }

        return $item_bonus;
    }

    /**
     * @param mixed $wave
     * @param mixed $item_bonus
     * @param array<mixed> $offense
     */
    private function unit_offense($wave, $item_bonus, &$offense): void
    {
        $r = $this->battle->int_check_limit($wave, 'r', 0, 8);
        for ($u = 0; $u < 10; $u++) {
            //changed r<=0 and $u<=7
            $up_level = $r == 3 ? 0 : (($u <= 7) ? $wave['U'][$u] : 0);
            $value = $this->battle->stat_with_upg($this->unitStat($r, $u, 'off'), $this->unitStat($r, $u, 'cu'), $up_level);
            $value += $item_bonus[$u];
            $type = \Game\Tribes::isCavalry($r + 1, $u + 1) ? 'c' : 'i';
            $offense[$type] += $value * $wave['u'][$u];
        }
    }

    /**
     * @param mixed $wave
     * @param mixed $stats
     */
    private function hero_boni(&$wave, &$stats): void
    {
        $arm = 0;
        $str = 0;
        if ($wave['hero']) {
            $wave['hero']['arm'] ??= 0;
            $wave['hero']['str'] ??= 0;
            $wave['hero']['n'] ??= 0;
            $str += $wave['hero']['str'];
            $arm += $wave['hero']['arm'];
            $stats['n'] = $wave['hero']['n'];
        }
        $wave['a'] = $arm;
        $this->set_hero_stats($wave, $str, $stats);
    }

    /**
     * @param mixed $wave
     * @param (float|int) $str
     * @param mixed $stats
     */
    private function set_hero_stats($wave, $str, &$stats): void
    {
        $r = $wave['r'];
        $s = isset($wave['hero']['total_power']) ? 0 : $wave['hero']['power'];
        $st = $this->hero_str4($wave, $s, $r, $str);
        $stats['i'] += $st[0];
        $stats['c'] += $st[1];
        $stats['b'] *= 1 + $wave['hero'][($wave['side'] . 'Bonus')] * 0.002;
    }

    /**
     * @param mixed $wave
     * @param mixed $s
     * @param mixed $r
     * @param mixed $str_add
     * @return list<(float|int)>|list<mixed>
     */
    private function hero_str4($wave, $s, $r, $str_add)
    {
        $str = isset($wave['hero']['total_power']) ? $wave['hero']['total_power'] : (100 + $s * ($r ? 80 : 100) + $str_add);
        if ($wave['side'] == 'off') {
            $stats = [0, 0];
            $stats[isset($wave['hero']['isCavalry']) && $wave['hero']['isCavalry'] ? 1 : 0] += $str;
            return $stats;
        }
        return [$str, $str];
    }

    /**
     * @param mixed $stats
     */
    private function finalize_stats(&$stats): void
    {
        $stats['i'] *= $stats['b'];
        $stats['c'] *= $stats['b'];
        $stats['t'] = $stats['i'] + $stats['c'];
    }

    /**
     * @param mixed $def_armies
     * @return mixed
     */
    private function calc_total_defense(&$def_armies)
    {
        $defense = ['i' => 0, 'c' => 0, 'b' => 1];
        foreach ($def_armies as $idx => &$army) {
            $this->calc_defense($army, $defense);
        }
        return $defense;
    }

    /**
     * @param mixed $wave
     * @param mixed $defense
     */
    private function calc_defense(&$wave, &$defense): void
    {
        $def = ['i' => 0, 'c' => 0, 'b' => 1];
        $item_bonus = $this->item_unit_bonus($wave);
        $this->unit_defense($wave, $item_bonus, $def);
        if (!empty($wave['u'][10])) {
            $this->hero_boni($wave, $def);
        }
        if (isset($wave['P'])) {
            $def['b'] *= 1.1;
        }
        if (isset($wave['M'])) {
            $def['b'] *= $this->battle->limit(1, (float)$wave['M'], 1.1);
        }
        $defense['i'] += $def['i'] * $def['b'];
        $defense['c'] += $def['c'] * $def['b'];
    }

    /**
     * @param mixed $wave
     * @param mixed $item_bonus
     * @param array<mixed> $def
     */
    private function unit_defense($wave, $item_bonus, &$def): void
    {
        $r = $this->battle->int_check_limit($wave, 'r', 0, 8);
        for ($u = 0; $u < 10; $u++) {
            $up_level = (in_array($r, [0, 1, 2, 5, 6, 7, 8], true) && $u <= 7) ? $wave['U'][$u] : 0;
            $cu = $this->unitStat($r, $u, 'cu');
            $def['i'] += $wave['u'][$u] * ($this->battle->stat_with_upg($this->unitStat($r, $u, 'def_i'),
                        $cu,
                        $up_level) + $item_bonus[$u]);
            $def['c'] += $wave['u'][$u] * ($this->battle->stat_with_upg($this->unitStat($r, $u, 'def_c'),
                        $cu,
                        $up_level) + $item_bonus[$u]);
        }
    }

    private function unitStat(int $race, int $number, string $stat): int
    {
        return (int)Formulas::$data['units'][$race][$number][$stat];
    }

    /**
     * @param mixed $offense
     * @param (float|int) $pop_ratio
     */
    private function lone_dies($offense, $pop_ratio): bool
    {
        return round($offense['t'] * $this->battle->common_morale($pop_ratio)) <= BattleModel::LONE_ATTACKER_THRESHOLD;
    }
}
