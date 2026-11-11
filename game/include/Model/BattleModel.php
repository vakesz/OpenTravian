<?php

declare(strict_types=1);

namespace Model;

use function array_sum;
use Core\Config;
use Core\Database\DB;
use Game\AllianceBonus\AllianceBonus;
use Game\Buildings\BuildingAction;
use Game\Conquest\LoyaltyProtection;
use Game\Formulas;
use Game\Helpers\HeroHealthHelper;
use Game\Helpers\LoyaltyHelper;
use Game\Hero\BagOintment;
use Game\Hero\CombatExperience;
use Game\Hero\HeroExperience;
use Game\Hero\HeroHelper;
use Game\Hero\HeroItems;
use Game\NoticeHelper;
use Game\ResourcesHelper;
use Game\SpeedCalculator;
use Game\Starvation;
use Game\TruceDay;
use function array_filter_units;
use function getCustom;
use function logError;
use function unitIdToNr;
use function unitIdToTribe;

/**
 * @phpstan-type MovementRow array{id: int, kid: int, to_kid: int, race: int, ctar1: int, ctar2: int, spyType: int, redeployHero: int, ship: int, mode: int, attack_type: int, start_time: int, end_time: int, end_time_seconds: int, markState: int, data: string, u1: int, u2: int, u3: int, u4: int, u5: int, u6: int, u7: int, u8: int, u9: int, u10: int, u11: int}
 * @phpstan-type BattleUnits array{num: array<int, int|float>, smithy: array<int, int|float>, dead: array<int, int|float>, trapped?: array<int, int|float>}
 * @phpstan-type AttackerUnits array{num: array<int, int|float>, smithy: array<int, int|float>, dead: array<int, int|float>, trapped: array<int, int|float>}
 * @phpstan-type Row array<string, int|float>
 * @phpstan-type Stats array{i: int|float, c: int|float, b: int|float, t: int|float, n?: int|float}
 * @phpstan-type Losses array{int|float, int|float}
 * @phpstan-type ReportTroops array{kid?: int, race: int, num: array<int, int|float>, dead: array<int, int|float>, smithy?: array<int, int|float>, uid?: int, uname?: string}
 * @phpstan-type AttackerOutcome array{total_dead: int|float, total_dead_upkeep: int|float, total_trapped: int|float, total_left: int|float}
 * @phpstan-type DefenderOutcome array{total_dead: int|float, total_dead_upkeep: int|float, total_num: int|float, total_left: int|float, def_points_share: array<int, int|float>}
 * @phpstan-type ItemRow array<string, int|float|string|null>
 * @phpstan-type EquippedItems array{leftHand: ItemRow|int, rightHand: ItemRow|int, body: ItemRow|int, shoes: ItemRow|int, bag: ItemRow|int, horse: ItemRow|int, helmet: ItemRow|int}
 * @phpstan-type Wave array{uid: int, kid: int, race: int, uniqueId: int, isEnforce: bool, isForeign: bool, units: BattleUnits, hero: Row, heroItems: EquippedItems, metallurgy: int|float, net_def_point?: int|float}
 * @phpstan-type Buildings array<int, array{item_id: int, level: int}>
 * @phpstan-type AttackerWave array{uid: int, race: int, units: AttackerUnits, hero: Row, heroItems: EquippedItems, metallurgy: int|float}
 * @phpstan-type AttackerState array{uid: int, kid: int, player: array{aid: int, name: string, cp: int, alliance_join_time: int}, total_pop: int, total_bounty: int|float, wave: AttackerWave, buildings: array<int, int>}
 * @phpstan-type DefenderState array{uid: int, kid: int, hdp: int, player: array{aid: int, name: string, race: int, escape: bool, hasGoldClub: bool, alliance_join_time: int}, isOasis: bool, isOccupied: int, total_pop: int, stone: int, wall: int, rp: int, traps: int|float, wave: list<Wave>, isWW: bool|int, buildings: Buildings, total_net_def_point: int|float, totalCranny: int|float, rallyPoint: int}
 */
class BattleModel
{
    const BASE_VILLAGE_DEF = 10;
    const SCOUT_DEF = 20;
    const SCOUT_OFF = 35;
    const LONE_ATTACKER_THRESHOLD = 84;
    /** @var EquippedItems the hero items of a troop that has no hero */
    private const NO_ITEMS = ['leftHand' => 0, 'rightHand' => 0, 'body' => 0, 'shoes' => 0, 'bag' => 0, 'horse' => 0, 'helmet' => 0];
    /** Wall durability against rams per tribe; the Rally Point simulator reads these tribe tables too. */
    public const WALL_DURABILITY = [1 => 1, 2 => 5, 3 => 2, 4 => 1, 5 => 1, 6 => 4, 7 => 1, 8 => 2, 9 => 3];
    /** Defence points per wall level, per tribe. */
    public const WALL_EXTRA = [
        1 => 10,
        2 => 6,
        3 => 8,
        4 => 0,
        5 => 10,
        6 => 8,
        7 => 6,
        8 => 10,
        9 => 6,
    ];
    /** Loyalty a chief lowers (min, max), per tribe. */
    public const ADMINISTRATOR_EFFECT = [
        1 => [20, 30],
        2 => [20, 25],
        3 => [20, 25],
        4 => [0, 0],
        5 => [200, 200],
        6 => [20, 25],
        7 => [20, 25],
        8 => [20, 25],
        9 => [15, 30],
    ];
    /** Attacker and defender combat strength of the battle being reported (see finalize_attack). */
    /** @var array{attacker: int, defender: int} */
    private array $reportStrength = ['attacker' => 0, 'defender' => 0];

    private BattleSetter $model;
    private bool $isTopOff = false;
    private bool $isTopDef = false;
    /** @var AttackerState */
    private array $attacker = [
        'uid' => 0,
        'kid' => 0,
        'player' => ['aid' => 0, 'name' => '', 'cp' => 0, 'alliance_join_time' => 0],
        'total_pop' => 0,
        'total_bounty' => 0,
        'wave' => [
            'uid' => 0,
            'race' => 1,
            'units' => ['num' => [], 'smithy' => [], 'dead' => [], 'trapped' => []],
            'metallurgy' => 1,
            'hero' => [],
            'heroItems' => self::NO_ITEMS,
        ],
        'buildings' => [],
    ];
    /** @var DefenderState */
    private array $defender = [
        'uid' => 0,
        'kid' => 0,
        'hdp' => 0,
        'player' => [
            'aid' => 0,
            'name' => '',
            'race' => 0,
            'escape' => false,
            'hasGoldClub' => false,
            'alliance_join_time' => 0,
        ],
        'isOasis' => false,
        'isOccupied' => 0,
        'total_pop' => 500,
        'stone' => 0,
        'wall' => 0,
        'rp' => 0,
        'traps' => 0,
        'wave' => [],
        'isWW' => false,
        'buildings' => [],
        'total_net_def_point' => 0,
        'totalCranny' => 0,
        'rallyPoint' => 0,
    ];
    private HeroItems $heroItemsModel;
    /** @var array{escape?: int, bounty?: array<int, int|float>, none_return?: bool, totally_destroyed?: bool, not_destroyed_reason?: mixed, rams?: array<int, int|float>, cata?: array<int, array<int, int|float>>, cata_is_disabled?: bool, protectedByArtifact?: list<array{type: mixed, size: mixed, num: mixed}>, free?: array{int|float, int|float}, captureError?: mixed, captureResult?: mixed, oasisCapture?: mixed, heroArtifactCapture?: mixed, heroPlanCapture?: mixed} */
    private array $info = [];
    /**
     * The report remembers that the attack was a raid, so its subject can say "raids" like the reference.
     *
     * @return array{raid?: true}
     */
    private function raidInfo(): array
    {
        return (int)$this->row['attack_type'] === MovementsModel::ATTACKTYPE_RAID ? ['raid' => true] : [];
    }

    private bool $cataWorks = false;
    /** @var MovementRow */
    private array $row;
    private bool $isFarm = false;

    /** @var array<string, float> */
    protected array $profile = [];
    protected bool $profilingEnabled = true;

    /** A numeric value out of the loosely typed hero item catalogue, 0 when it is not a number. */
    private static function number(mixed $value): int|float
    {
        return is_int($value) || is_float($value) ? $value : (is_numeric($value) ? $value + 0 : 0);
    }

    private function startProfile(string $name): void
    {
        if (!$this->profilingEnabled)
            return;
        $this->profile[$name] = microtime(true);
    }

    private function endProfile(string $name): void
    {
        if (!$this->profilingEnabled)
            return;
        $this->profile[$name] = (microtime(true) - $this->profile[$name]) * 1000;
    }

    private function profileOutput(): void
    {
        if ($this->profilingEnabled) {
            $this->endProfile('__construct');
        }
    }

    /**
     * The movement row with its numeric columns as ints (the database hands them over as strings), so the rest of
     * the battle works with typed values.
     *
     * @param array<string, int|string|null> $row movement row
     * @return MovementRow
     */
    private static function typedMovementRow(array $row): array
    {
        foreach (array_merge(
            ['id', 'kid', 'to_kid', 'race', 'ctar1', 'ctar2', 'spyType', 'redeployHero', 'ship', 'mode', 'attack_type', 'start_time', 'end_time', 'markState'],
            array_map(static fn (int $unit): string => 'u' . $unit, range(1, 11))
        ) as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = (int) $row[$column];
            }
        }
        /** @var MovementRow $row */

        return $row;
    }

    /**
     * Resolves one arrived attack movement: loads both sides, fights, and writes the reports and the returning troops.
     *
     * @param array<string, int|string|null> $row movement row
     */
    public function __construct(array $row)
    {
        $this->startProfile('__construct');
        $this->heroItemsModel = new HeroItems();
        $this->row = self::typedMovementRow($row);
        $this->row['end_time_seconds'] = (int) ceil($this->row['end_time'] / 1000);

        if (getGame("catapultAvailableTime") > 0) {
            $this->cataWorks = time() >= getGame("catapultAvailableTime");
        } else {
            $this->cataWorks = true;
        }
        $this->model = new BattleSetter();
        $this->startProfile("assocAttacker");
        $this->assocAttacker();
        $this->endProfile("assocAttacker");

        //Checking if there is a village or not
        if (!$this->model->getVillageState($this->row['to_kid'])) {
            $this->sendVillageDoesNotExistsReport();
            $this->returnTroops();
            $this->profileOutput();
            return;
        }

        $this->startProfile("assocDefender");
        $resultDefender = $this->assocDefender();
        $this->endProfile("assocDefender");


        // Checking for unknown defender
        if (!$this->defender['uid'] && !$this->defender['isOasis'] || !$resultDefender) {
            $this->sendVillageDoesNotExistsReport();
            $this->returnTroops();
            $this->profileOutput();
            return;
        }
        // Checking for multi account
        if ($this->defender['uid'] > 2 && $this->attacker['uid'] > 2 && $this->defender['uid'] != $this->attacker['uid']) {
            MultiAccount::addMultiAccountLog($this->attacker['uid'], $this->defender['uid'], 3);
        }
        if ($this->row['attack_type'] != MovementsModel::ATTACKTYPE_SPY && TruceDay::isActive()) {
            if ($this->defender['uid'] > 2 && $this->attacker['uid'] > 2 && $this->attacker['uid'] != $this->defender['uid']) {
                $this->sendTruceReport(true);
                //attacker report
                if (!$this->defender['isOasis'] || $this->defender['isOccupied']) {
                    //defender report
                    $this->sendTruceReport(false);
                }
                $move = new MovementsModel();
                $move->addMovement($this->row['to_kid'],
                    $this->row['kid'],
                    $this->row['race'],
                    array_filter_units($this->row),
                    0,
                    0,
                    0,
                    0,
                    1,
                    $this->row['attack_type'],
                    $this->row['end_time'],
                    2 * $this->row['end_time'] - $this->row['start_time'],
                    NULL,
                    $this->row['ship']);
                return;
            }
        }
        $helper = new HeroHelper();
        if ($this->attacker['wave']['units']['num'][11] && $this->defender['isOasis'] && !$this->defender['isOccupied'] && array_sum($this->defender['wave'][0]['units']['num'])) {
            $this->startProfile('cagedAttack');
            //checking for cages.
            $cages = $helper->getCages($this->attacker['wave']['heroItems']['bag']);
            if ($cages) {
                //caged attack :>
                $caged = array_fill(1, 10, 0);
                $this->model->move_to_cages($this->defender['wave'][0]['units']['num'], $cages, $caged);
                $this->finalize_caged_attack($caged);
                $this->endProfile('cagedAttack');
                $this->profileOutput();
            return;
            }
            $this->endProfile('cagedAttack');
        }
        $no_morale = $this->defender['isWW'] || $this->defender['uid'] == 1;
        $wall_b = \Game\Formulas::WALL_BASE[$this->defender['wave'][0]['race']];
        $pop_ratio = $this->attacker['total_pop'] / $this->defender['total_pop'];
        if (getGame("ignoreMoralPointsInAttacks")) {
            $pop_ratio = 1;
        }
        $sui = Formulas::getSpyId($this->row['race']);
        if ($sui && array_sum($this->attacker['wave']['units']['num']) == $this->attacker['wave']['units']['num'][$sui]) {
            //spy attack
            $this->startProfile('processScoutAttack');
            $this->processScoutAttack($no_morale, $pop_ratio, $sui, $wall_b);
            $this->endProfile('processScoutAttack');
            $this->profileOutput();
            return;
        }
        $this->startProfile('checkingTraps');
        if ($this->defender['traps']) {
            $units = $this->attacker['wave']['units']['num'];
            $trapped = $this->model->move_to_traps($units, $this->defender['traps']);
            foreach ($trapped as $key => $v) {
                $this->attacker['wave']['units']['trapped'][$key] = $v;
            }
        }
        $this->endProfile('checkingTraps');
        $this->startProfile('FinalizingAtkDefBonus');
        $stone = 1 + $this->defender['stone'] / 10;
        $brewery = (new VillageModel())->getCapBrewery($this->attacker['uid']);
        $offense = $this->calc_offense($brewery);
        if ($this->defender['uid'] == 1) {
            if ($this->attacker['wave']['units']['num'][11] && isset($offense['n'])) {
                $offense['b'] *= 1 + $offense['n'] / 100; // natarian horns
            }
        }
        $this->finalize_stats($offense);
        $defense = $this->calc_total_defense($offense);

        $this->finalize_stats($defense);

        $total = array_sum($this->attacker['wave']['units']['num']);
        $total -= array_sum($this->attacker['wave']['units']['trapped']);
        foreach ($this->defender['wave'] as &$army) {
            $total += array_sum($army['units']['num']);
        }

        $immensity = $this->model->calc_diffusion($total);
        $defense['t'] = $this->model->adduced_def($defense['i'], $defense['c'], $offense['i'], $offense['c']);
        $this->defender['total_net_def_point'] = $defense['t'];
        if (!$this->defender['isOasis']) {
            $defense['t'] += 2 * pow($this->defender['rp'], 2);
        }

        //MARK I
        $defense['t'] += self::BASE_VILLAGE_DEF;
        $defense['t'] += self::WALL_EXTRA[$this->defender['wave'][0]['race']] * $this->defender['wall'];

        $wall_bonus = round(pow($wall_b, $this->defender['wall']), 3);
        $pts_ratio = $this->model->remorale($offense['t'], $defense['t'], $no_morale ? 1 : $pop_ratio, $wall_bonus);
        $x = pow($pts_ratio, $immensity);
        $lone_attacker = array_sum($this->attacker['wave']['units']['num']) === 1;
        $this->endProfile('FinalizingAtkDefBonus');

        if (!$offense['t']) {
            $this->startProfile('FinalizeRaidAttackNoOffense');
            $this->finalize_attack($no_morale, $pop_ratio, [1, 0], $offense['t'], $defense['t']);
            $this->endProfile('FinalizeRaidAttackNoOffense');
            $this->profileOutput();
            return;
        }

        if ($this->row['attack_type'] == MovementsModel::ATTACKTYPE_RAID) {
            $this->startProfile('FinalizeRaidAttack');
            $off_losses = 1 / (1 + $x);
            $def_losses = $x / (1 + $x);
            if ($lone_attacker && $this->lone_dies($offense, $pop_ratio)) {
                $off_losses = 1;
            }
            $this->finalize_attack($no_morale, $pop_ratio, [$off_losses, $def_losses], $offense['t'], $defense['t']);
            $this->endProfile('FinalizeRaidAttack');
            $this->profileOutput();
            return;
        }

        $off_losses = $x ? min(1 / $x, 1) : 1;
        $def_losses = min($x, 1);
        $siege_percent = $this->model->sigma(pow($pts_ratio, 1.5));
        $art_dur = $this->model->art_effect(ArtefactsModel::getArtifactEffectByType($this->defender['uid'], $this->row['to_kid'], ArtefactsModel::ARTIFACT_INCREASE_BUILDINGS_STABILITY), 10);
        $d = $stone * $art_dur;
        $wall_dur = self::WALL_DURABILITY[$this->defender['wave'][0]['race']];
        $info =& $this->info;
        if (!$this->isFarm() && $this->attacker['wave']['units']['num'][7] && $this->defender['wall']) {
            $up = $this->attacker['wave']['units']['smithy'][7];
            $rams = $this->attacker['wave']['units']['num'][7];
            $mwl = $this->model->early_ramming($this->defender['wall'], $rams, $siege_percent, $up, $d, $wall_dur);
            $wall_bonus = round(pow($wall_b, $mwl), 3);
            $pts_ratio = $this->model->remorale($offense['t'], $defense['t'], $no_morale ? 1 : $pop_ratio, $wall_bonus);
            $siege_percent = $this->model->sigma(pow($pts_ratio, 1.5));
            $x = pow($pts_ratio, $immensity);
            $rams = $this->model->sigma(pow($pts_ratio, $immensity)) * $this->attacker['wave']['units']['num'][7];
            $rams = $this->model->fixSpeedCataRamEffect($rams, $off_losses, $def_losses);
            $off_losses = min(1 / $x, 1);
            $def_losses = min($x, 1);
            $wl = $this->model->demolish($this->defender['wall'], $rams, $up, $wall_dur, $d);
            $info['rams'] = [
                $this->defender['buildings'][40]['item_id'],
                $this->defender['wall'],
                $wl,
            ];
            $this->finalize_ram_damage();
        }
        $supporting_artifacts = $this->model->getProtectingArtifacts($this->defender['uid'], $this->row['to_kid']);
        if ($supporting_artifacts) {
            $info['protectedByArtifact'] = [];
            foreach ($supporting_artifacts as $protecting_artifact) {
                $info['protectedByArtifact'][] = [
                    'type' => $protecting_artifact['type'],
                    'size' => $protecting_artifact['size'],
                    'num' => $protecting_artifact['num'],
                ];
            }
        }
        if ($this->isFarm() || ($this->attacker['wave']['units']['num'][8] && !$this->defender['isOasis'] && !$this->cataWorks)) {
            $info['cata_is_disabled'] = true;
        }
        if (!$this->isFarm() && $this->attacker['wave']['units']['num'][8] && !$this->defender['isOasis'] && $this->cataWorks) {
            $targets = [[0, 0, 0]];
            $selected = [0];
            if ($this->row['ctar2'] <> 0) {
                $targets[] = [0, 0, 0];
                $selected[] = 0;
            }
            $random_artifact = $this->model->randomTargetArtifact($this->defender['uid'], $this->row['to_kid']);
            $village = new VillageModel();
            $cap = $village->getCapBrewery($this->attacker['uid']);
            $aims = [];
            for ($i = 0; $i < sizeof($targets); ++$i) {
                $aims[$i] = \Game\CatapultTargets::aim((int)$this->row['ctar' . ($i + 1)], (bool)$cap, (int)$random_artifact);
                $selected[$i] = $this->find_building_by_gid($aims[$i], $this->defender['buildings']);
            }
            $info['cata'] = [];

            $up = $this->attacker['wave']['units']['smithy'][8];

            $moral = $no_morale ? 1 : $this->model->limit(0.333, pow($pop_ratio, -0.3), 1);
            $cats = $this->attacker['wave']['units']['num'][8] * $siege_percent * $moral;
            //some fixes for speed servers
            $cats = $this->model->fixSpeedCataRamEffect($cats, $off_losses, $def_losses);
            if (isset($selected[1]) && $selected[1] and $this->attacker['wave']['units']['num'][8] >= 20) { // two targets
                $cats /= 2;
                if (!$this->defender['buildings'][$selected[1]]['level']) {
                    unset($targets[1]); //no building with this target exists!
                    //maybe village totally destroyed!
                } else {
                    $info['cata'][1] = [
                        $this->defender['buildings'][$selected[1]]['item_id'],
                        $this->defender['buildings'][$selected[1]]['level'],
                        $this->model->demolish($this->defender['buildings'][$selected[1]]['level'], $cats, $up, 1, $d / ($this->defender['buildings'][$selected[1]]['item_id'] == 40 ? $art_dur : 1)),
                        $this->row['ctar2'] == $this->defender['buildings'][$selected[1]]['item_id'] ? 0 : 1,
                    ];
                    $this->finalize_cata_damage($selected[1], $info['cata'][1]);
                }
            }
            if ($no_morale) {
//                $d /= $art_dur;
            }
            if (!$selected[0] || !$this->defender['buildings'][$selected[0]]['level']) {
                $selected[0] = $this->find_building_by_gid($aims[0], $this->defender['buildings']);
            }
            if (!$selected[0] || !$this->defender['buildings'][$selected[0]]['level']) {
                unset($targets[0]); //no building with this target exists!
                if (isset($targets[1])) {
                    array_unshift($targets, $targets[1]);
                    unset($targets[1]);
                }
                //maybe village totally destroyed!
            } else {
                $info['cata'][0] = [
                    $this->defender['buildings'][$selected[0]]['item_id'],
                    $this->defender['buildings'][$selected[0]]['level'],
                    $this->model->demolish($this->defender['buildings'][$selected[0]]['level'], $cats, $up, 1, $d / ($this->defender['buildings'][$selected[0]]['item_id'] == 40 ? $art_dur : 1)),
                    $this->row['ctar1'] == $this->defender['buildings'][$selected[0]]['item_id'] ? 0 : 1,
                ];
                $this->finalize_cata_damage($selected[0], $info['cata'][0]);
            }
            $this->checkIfVillageIsDestroyed();
        }
        if ($lone_attacker && $this->lone_dies($offense, $pop_ratio)) {
            $off_losses = 1;
        }

        $this->finalize_attack($no_morale,
            $pop_ratio,
            [
                $off_losses,
                $def_losses,
            ],
            $offense['t'],
            $defense['t']);

        $this->profileOutput();
            return;
    }

    /**
     * The hero items the player has equipped, each resolved to its item row (0 for an empty slot).
     *
     * @return EquippedItems
     */
    private function equippedItems(int $uid): array
    {
        $inventory = $this->model->getInventory($uid);
        $helper = new HeroHelper();
        $slot = static fn (string $name): array|int => $helper->procInput((int) ($inventory[$name] ?? 0));

        return [
            'leftHand' => $slot('leftHand'),
            'rightHand' => $slot('rightHand'),
            'body' => $slot('body'),
            'shoes' => $slot('shoes'),
            'bag' => $slot('bag'),
            'horse' => $slot('horse'),
            'helmet' => $slot('helmet'),
        ];
    }

    private function assocAttacker(): void
    {
        new Starvation($this->row['kid']);

        $this->startProfile('assocAttacker:getOwner');
        $owner = $this->model->getOwner($this->row['kid']);
        $this->endProfile('assocAttacker:getOwner');
        $this->startProfile('assocAttacker:getUser');
        $user = $this->model->getUser($owner);
        $this->endProfile('assocAttacker:getUser');
        $this->startProfile('assocAttacker:getFullUnits');
        $units = $this->model->getFullUnits($this->row['kid'], array_filter_units($this->row), true);
        $this->endProfile('assocAttacker:getFullUnits');

        $this->startProfile('assocAttacker:getAttackerNeededBuildings');
        $buildings = $this->model->getAttackerNeededBuildings($this->row['kid']);
        $this->endProfile('assocAttacker:getAttackerNeededBuildings');

        $this->startProfile('assocAttacker:populatingData');
        $this->attacker = [
            'uid' => $owner,
            'kid' => $this->row['kid'],
            'player' => [
                'aid' => $user['aid'],
                'name' => $user['name'],
                'cp' => $user['cp'],
                'alliance_join_time' => $user['alliance_join_time'],
            ],
            'total_pop' => max($user['total_pop'], 1),
            'total_bounty' => 0,
            'wave' => [
                'uid' => $owner,
                'race' => $this->row['race'],
                'metallurgy' => AllianceBonus::getArmorBonus($user['aid'], $user['alliance_join_time']),
                'units' => $units,
                'hero' => [],
                'heroItems' => self::NO_ITEMS,
            ],
            'buildings' => $buildings,
        ];
        $this->endProfile('assocAttacker:populatingData');
        if ($this->attacker['wave']['units']['num'][11]) {
            $this->startProfile('assocAttacker:hero');
            HeroHealthHelper::updateUserHeroHealth($owner);
            $this->attacker['wave']['hero'] = $this->model->getHero($owner) ?? [];
            $this->attacker['wave']['heroItems'] = $this->equippedItems($owner);
            $this->endProfile('assocAttacker:hero');
        }
    }

    public function sendVillageDoesNotExistsReport(): void
    {
        $data = [
            'attacker' => [
                'kid' => $this->attacker['kid'],
                'uid' => $this->attacker['uid'],
                'uname' => $this->attacker['player']['name'],
                'race' => $this->attacker['wave']['race'],
                'num' => $this->attacker['wave']['units']['num'],
                'dead' => null,
                'trapped' => null,
            ],
            'no_village' => true,
        ];
        $reportType = NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES;
        NoticeHelper::addNotice($this->attacker['player']['aid'],
            $this->attacker['uid'],
            $this->row['kid'],
            $this->row['to_kid'],
            $reportType,
            null,
            $data,
            $this->row['end_time_seconds']);
    }

    private function returnTroops(): void
    {
        $move = new MovementsModel();
        $move->addMovement($this->row['to_kid'], $this->row['kid'], $this->row['race'], array_filter_units($this->row), 0, 0, 0, 0, 1, $this->row['attack_type'], $this->row['end_time'], 2 * $this->row['end_time'] - $this->row['start_time'], NULL, $this->row['ship']);
    }

    private function assocDefender(): bool
    {
        $oasis = new OasesModel();
        $this->defender = [
            'uid' => 0,
            'kid' => $this->row['to_kid'],
            'hdp' => 0,
            'player' => [
                'aid' => 0,
                'name' => '',
                'race' => 0,
                'escape' => false,
                'hasGoldClub' => false,
                'alliance_join_time' => 0,
            ],
            'isOasis' => $oasis->isOasis($this->row['to_kid']),
            'isOccupied' => 0,
            'total_pop' => 500,
            'stone' => 0,
            'wall' => 0,
            'rp' => 0,
            'traps' => 0,
            'wave' => [],
            'isWW' => false,
            'buildings' => [],
            'total_net_def_point' => 0,
            'totalCranny' => 0,
            'rallyPoint' => 0,
        ];
        if ($this->defender['isOasis']) {
            $this->defender['isOccupied'] = $oasis->getOasisCaptureKid($this->row['to_kid']);
            if (!$this->defender['isOccupied']) {
                $this->startProfile('assocDefender:oasesTrain');
                OasesModel::oasesTrain($this->row['to_kid']);
                $this->endProfile('assocDefender:oasesTrain');
                $this->startProfile('assocDefender:getOasisUnits');
                $units = $this->model->getFullUnits($this->row['to_kid'], $this->model->getUnits($this->row['to_kid'], true));
                $this->endProfile('assocDefender:getOasisUnits');
                $this->defender['wave'][] = [
                    'uid' => 0,
                    'kid' => 0,
                    'race' => 4,
                    'uniqueId' => $this->row['to_kid'],
                    'isEnforce' => false,
                    'isForeign' => false,
                    'units' => $units,
                    'hero' => [],
                    'heroItems' => self::NO_ITEMS,
                    'metallurgy' => 1,
                ];
            } else {
                $this->startProfile('assocDefender:oasisOwnerStarvation');
                new Starvation($this->defender['isOccupied']);
                $this->startProfile('assocDefender:oasisOwnerStarvation');
                $this->startProfile('assocDefender:getOasisOwner');
                $this->defender['uid'] = $this->model->getOwner($this->defender['isOccupied']);
                $this->endProfile('assocDefender:getOasisOwner');
                $this->startProfile('assocDefender:getOasisUser');
                $user = $this->model->getUser($this->defender['uid']);
                $this->endProfile('assocDefender:getOasisUser');
                $this->defender['player'] = [
                    'aid' => $user['aid'],
                    'name' => $user['name'],
                    'race' => $user['race'],
                    'escape' => false,
                    'hasGoldClub' => $user['goldclub'] == 1,
                    'alliance_join_time' => $user['alliance_join_time'],
                ];
                $this->defender['total_pop'] = max($user['total_pop'], 1);
                $this->startProfile('assocDefender:getVillageFullUnits');
                $units = $this->model->getFullUnits($this->row['to_kid'], array_fill(1, 11, 0));
                $this->endProfile('assocDefender:getVillageFullUnits');
                $this->defender['wave'][] = [
                    'uid' => $this->defender['uid'],
                    'kid' => $this->defender['isOccupied'],
                    'uniqueId' => $this->row['to_kid'],
                    'isEnforce' => false,
                    'isForeign' => false,
                    'race' => $this->defender['player']['race'],
                    'units' => $units,
                    'hero' => [],
                    'heroItems' => self::NO_ITEMS,
                    'metallurgy' => 1,
                ];
            }
        } else {
            new Starvation($this->row['to_kid']);
            $this->startProfile('assocDefender:getVillageOwner');
            $this->defender['uid'] = $this->model->getOwner($this->row['to_kid']);
            $this->endProfile('assocDefender:getVillageOwner');
            $user = $this->model->getUser($this->defender['uid']);
            $this->defender['player'] = [
                'aid' => $user['aid'],
                'name' => $user['name'],
                'alliance_join_time' => $user['alliance_join_time'],
                'race' => $user['race'],
                'escape' => $user['escape'] == 1,
                'hasGoldClub' => $user['goldclub'] == 1,
            ];
            $this->defender['total_pop'] = max($user['total_pop'], 1);
            $this->startProfile('assocDefender:getVillageTroops');
            $units = $this->model->getFullUnits($this->row['to_kid'], $this->model->getUnits($this->row['to_kid']));
            $this->endProfile('assocDefender:getVillageTroops');
            $wave = [
                'uid' => $this->defender['uid'],
                'kid' => $this->row['to_kid'],
                'uniqueId' => $this->row['to_kid'],
                'isEnforce' => false,
                'isForeign' => false,
                'race' => $user['race'],
                'units' => $units,
                'hero' => [],
                'heroItems' => self::NO_ITEMS,
                    'metallurgy' => 1,
            ];
            $this->defender['traps'] = max($this->defender['traps'], 0);
            $this->defender['isWW'] = $this->isFarm = false;
            $db = DB::getInstance();
            $this->startProfile('assocDefender:getVillageInfo');
            $village = $this->model->fetchRow("SELECT isWW, isFarm, pop FROM vdata WHERE kid={$this->row['to_kid']}");
            $isZeroPop = false;
            if ($village !== null) {
                $this->defender['isWW'] = (int) $village['isWW'];
                $this->isFarm = (bool) $village['isFarm'];
                $isZeroPop = (int) $village['pop'] <= 5;
            }
            $this->endProfile('assocDefender:getVillageInfo');

            $this->startProfile('assocDefender:getDefenderBuildings');
            $totalTraps = $this->model->getDefenderBuildings($this->row['to_kid'], $this->defender, $wave['race']);
            $this->endProfile('assocDefender:getDefenderBuildings');


            if ($this->defender['player']['escape'] && $this->defender['player']['hasGoldClub'] && !$this->defender['isOasis'] && $this->row['attack_type'] <> MovementsModel::ATTACKTYPE_SPY) {
                if ($this->defender['rallyPoint']) {
                    $this->startProfile('assocDefender:escape_units');
                    $this->info['escape'] = $this->model->escape_units($this->defender['kid'], $this->row['end_time'], $wave, $isZeroPop);
                    $this->endProfile('assocDefender:escape_units');
                } else {
                    $this->info['escape'] = 5;
                }
            }
            if ($wave['units']['num'][11]) {
                $this->startProfile('assocDefender:hero');
                HeroHealthHelper::updateUserHeroHealth($this->defender['uid']);
                $wave['hero'] = $this->model->getHero($this->defender['uid']) ?? [];
                if ($wave['hero']['hide'] == 1) {
                    $wave['hero'] = [];
                    $wave['units']['num'][11] = 0;
                } else {
                    $wave['heroItems'] = $this->equippedItems($this->defender['uid']);
                }
                $this->endProfile('assocDefender:hero');
            }
            $this->defender['wave'][] = $wave;
            if ($totalTraps > 0 && $this->defender['wave'][0]['race'] == 3) {
                $this->startProfile('assocDefender:fillTraps');
                $this->defender['traps'] = min($this->model->getTraps($this->row['to_kid']), $totalTraps);
                $this->defender['traps'] = max($this->defender['traps'], 0);
                $this->defender['traps'] -= $this->model->getFilledTrapCount($this->row['to_kid']);
                $this->endProfile('assocDefender:fillTraps');
            }
        }

        $this->startProfile('assocDefender:populatingEnforcements');

        $enforcement = $this->model->getEnforcement($this->row['to_kid']);
        while (($enforce = $enforcement->fetch_assoc())) {
            $wave = [
                'uid' => (int) $enforce['uid'],
                'kid' => (int) $enforce['kid'],
                'uniqueId' => (int) $enforce['id'],
                'isEnforce' => true,
                'isForeign' => (int) $enforce['kid'] !== $this->defender['isOccupied'],
                'race' => (int) $enforce['race'],
                'units' => $this->model->getFullUnits((int) $enforce['kid'], array_map('intval', array_filter_units($enforce))),
                'hero' => [],
                'heroItems' => self::NO_ITEMS,
                    'metallurgy' => 1,
            ];
            if ($wave['units']['num'][11]) {
                HeroHealthHelper::updateUserHeroHealth($wave['uid']);
                $wave['hero'] = $this->model->getHero($wave['uid']) ?? [];
                $wave['heroItems'] = $this->equippedItems($wave['uid']);
            }
            $this->defender['wave'][] = $wave;
        }
        $this->endProfile('assocDefender:populatingEnforcements');
        $metallurgy = [];
        foreach ($this->defender['wave'] as &$wave) {
            $uid = $wave['uid'];
            if (!isset($metallurgy[$uid])) {
                $metallurgy[$uid] = 1;
                if ($uid > 1) {
                    $owner = $this->model->getUser($uid);
                    $metallurgy[$uid] = AllianceBonus::getArmorBonus($owner['aid'], $owner['alliance_join_time']);
                }
            }
            $wave['metallurgy'] = $metallurgy[$uid];
        }
        return true;
    }

    /**
     * Takes cages or bandages out of the hero's bag item stack after a battle.
     *
     * @param ItemRow|int $bag the equipped bag item row (id, num), 0 when the bag is empty
     */
    private function useBagItems(int|string $uid, array|int $bag, int|float $used): void
    {
        if (!is_array($bag)) {
            return;
        }
        (new AuctionModel())->decreaseCageOrientItem((int) $uid, (int) $bag['id'], (int) $bag['num'], (int) $used);
    }

    public function sendTruceReport(bool $isAttacker): void
    {
        $data = [
            'attacker' => [
                'kid' => $this->attacker['kid'],
                'uid' => $this->attacker['uid'],
                'uname' => $this->attacker['player']['name'],
                'race' => $this->attacker['wave']['race'],
                'num' => $this->attacker['wave']['units']['num'],
                'smithy' => $this->attacker['wave']['units']['smithy'],
                'dead' => null,
                'trapped' => null,
            ],
            'truceActive' => true,
            'truceReason' => TruceDay::getReasonId(),
        ];
        $reportType = 0;
        if ($isAttacker) {
            NoticeHelper::addNotice($this->attacker['player']['aid'], $this->attacker['uid'], $this->row['kid'], $this->row['to_kid'], $reportType, null, $data, $this->row['end_time_seconds']);
        } else {
            NoticeHelper::addNotice($this->defender['player']['aid'], $this->defender['uid'], $this->row['kid'], $this->row['kid'], $reportType, null, $data, $this->row['end_time_seconds']);
        }
    }

    /**
     * @param array<int, int|float> $caged
     */
    private function finalize_caged_attack(array $caged): void
    {
        $modify = [];
        foreach ($caged as $nr => $num) {
            if (!$num) {
                continue;
            }
            $modify[] = "u{$nr}=u{$nr}-" . $num;
        }
        $db = DB::getInstance();
        if (sizeof($modify)) {
            $db->query("UPDATE units SET " . implode(",", $modify) . " WHERE kid={$this->row['to_kid']}");
        }
        $bag = $this->attacker['wave']['heroItems']['bag'];
        $num = is_array($bag) ? (int) $bag['num'] : 0;
        $total = array_sum($caged);
        $this->useBagItems($this->attacker['uid'], $this->attacker['wave']['heroItems']['bag'], $total);
        if ($num == $total) {
            $this->attacker['wave']['heroItems']['bag'] = 0;
        }
        //send reinforcement of nature units to village.
        $calculator = new SpeedCalculator();
        $calculator->setFrom($this->row['to_kid']);
        $calculator->setTo($this->row['kid']);
        $speeds = [];
        foreach ($caged as $nr => $value) {
            if (!$value) {
                continue;
            }
            $speeds[] = Formulas::uSpeed(nrToUnitId($nr, 4));
        }
        $calculator->setMinSpeed($speeds);
        $neededTime = $calculator->calc();
        $move = new MovementsModel();
        $move->addMovement($this->row['to_kid'], $this->row['kid'], 4, $caged, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_REINFORCEMENT, $this->row['end_time'], $this->row['end_time'] + 1000 * $neededTime);
        //send hero back and send report.
        $helper = new HeroHelper();
        $calculator = new SpeedCalculator();
        $calculator->isReturn();
        $calculator->setFrom($this->row['to_kid']);
        $calculator->setTo($this->row['kid']);
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($this->attacker['uid'], $this->row['kid'], ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        $calculator->setTournamentSqLvl($this->attacker['buildings'][14]);
        $units = array_fill(1, 11, 0);
        $returnSpeeds = [];
        $returnUnitIds = [];
        for ($i = 1; $i <= 10; ++$i) {
            if ($this->attacker['wave']['units']['num'][$i] <= 0) {
                continue;
            }
            $units[$i] = $this->attacker['wave']['units']['num'][$i];
            $unitId = nrToUnitId($i, $this->attacker['wave']['race']);
            $returnUnitIds[] = $unitId;
            $returnSpeeds[] = Formulas::uSpeed($unitId);
        }
        $units[11] = 1;
        $cavalryOnly = $calculator->isCavalryOnly($returnUnitIds);
        $returnSpeeds[] = $helper->calcTotalSpeed(
            $this->attacker['wave']['race'],
            $this->attacker['wave']['heroItems']['horse'],
            $this->attacker['wave']['heroItems']['shoes']
        );
        $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
            $this->attacker['wave']['race'],
            $this->attacker['wave']['heroItems']['horse'],
            $cavalryOnly
        ));
        $calculator->setMinSpeed($returnSpeeds);
        $calculator->hasHero();
        $calculator->setLeftHand($this->attacker['wave']['heroItems']['leftHand']);
        $calculator->setShoes($this->attacker['wave']['heroItems']['shoes']);
        $calculator->setShip($this->row['ship'], (int)$this->row['race']);
        $end_time = $this->row['end_time'] + $calculator->calc() * 1000;
        $move = new MovementsModel();
        $move->addMovement($this->row['to_kid'], $this->row['kid'], $this->row['race'], $units, 0, 0, 0, 0, 1, $this->row['attack_type'], $this->row['end_time'], $end_time, NULL, $this->row['ship']);
        $report = [
            'uid' => 0,
            'uname' => '<span class="errorMessage">' . T("Global", "NatureName") . '</span>',
            'kid' => $this->row['kid'],
            "caged" => $caged,
        ];
        NoticeHelper::addNotice(0, $this->attacker['uid'], $this->row['kid'], $this->row['to_kid'], NoticeHelper::TYPE_CAGED_ATTACK, null, $report, $this->row['end_time_seconds'], 0);
        $now = time();
        $db->query("UPDATE odata SET lastfarmed=$now WHERE kid={$this->row['to_kid']}");
    }

    private function finalize_spy_attack(int|float $off_losses, int|float $offense = 0, int|float $defense = 0): void
    {
        $attacker = $this->finalize_attacker_states($off_losses);
        $defender = $this->finalize_defender_states($attacker, 0);

        $this->setAttackerPoints(0, 0);
        $this->setDefenderPoints(0, $attacker['total_dead_upkeep'], $defender['def_points_share']);
        $this->setCasualties($attacker['total_dead'], $defender['total_dead']);

        $spyType = $this->row['spyType'];
        $report = [
            'losses' => [$off_losses, 0],
            'attacker' => [
                'kid' => $this->attacker['kid'],
                'uid' => $this->attacker['uid'],
                'uname' => $this->attacker['player']['name'],
                'race' => $this->attacker['wave']['race'],
                'num' => $this->attacker['wave']['units']['num'],
                'dead' => $this->attacker['wave']['units']['dead'],
                'smithy' => $this->attacker['wave']['units']['smithy'],
                'trapped' => null,
            ],
            'defender' => $this->getDefenderUnitsForReport(),
            'info' => $this->raidInfo(),
            'strength' => ['attacker' => (int)$offense, 'defender' => (int)$defense],
        ];
        //populate info here.
        if ($attacker['total_left'] <= 0) {
            $reportType = NoticeHelper::TYPE_LOST_AS_SPY;
            $tmp = $report;
            $report['info']['none_return'] = $tmp['info']['none_return'] = true;
            $tmp['defender'] = [$tmp['defender'][0]];
            foreach ($tmp['defender'] as &$val) {
                $val['num'] = array_fill(1, 11, '?');
                $val['dead'] = null;
            }
            NoticeHelper::addNotice($this->attacker['player']['aid'], $this->attacker['uid'], $this->row['kid'], $this->row['to_kid'], $reportType, null, $tmp, $this->row['end_time_seconds']);
        } else {
            $reportType = $attacker['total_dead'] > 0 ? NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES : NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES;
            if ($spyType == 1) {//resources | cranny.
                $report['info']['res'] = $this->getAvailableResources();
                $report['info']['cranny'] = $this->getTotalCranny();
            } else {
                $gid = Formulas::getWallID($this->defender['wave'][0]['race']);
                $report['info']['wall'] = [
                    $gid,
                    $this->defender['buildings'][40]['level'],
                ];
                $gid = $level = 0;
                foreach ($this->defender['buildings'] as $build) {
                    if (in_array($build['item_id'], [25, 26, 44])) {
                        $gid = $build['item_id'];
                        $level = $build['level'];
                        break;
                    }
                }
                if ($gid <> 0) {
                    $report['info']['rp'] = [$gid, $level];
                }
            }
            NoticeHelper::addNotice($this->attacker['player']['aid'], $this->attacker['uid'], $this->row['kid'], $this->row['to_kid'], $reportType, null, $report, $this->row['end_time_seconds']);
        }
        //report defender.
        if ($attacker['total_dead'] > 0) {
            if ((!$this->defender['isOasis'] || $this->defender['isOccupied']) && $this->defender['uid'] > 0 && $this->attacker['uid'] <> $this->defender['uid']) {
                $reportType = $reportType == NoticeHelper::TYPE_LOST_AS_SPY ? NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY : NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY;
                NoticeHelper::addNotice($this->defender['player']['aid'], $this->defender['uid'], $this->row['kid'], $this->row['to_kid'], $reportType, null, $report, $this->row['end_time_seconds']);
            }
        }
        if ($attacker['total_left'] > 0) {
            $this->finalize_troops_return(implode(",", isset($this->info['bounty']) ? $this->info['bounty'] : []));
        }
    }

    private function setAttackerPoints(int|float $total_bounty, int|float $def_total_upkeep): void
    {
        $total_bounty_percent = 1;
        if ($this->getAlive($this->attacker['wave'], 11)) {
            $helper = new HeroHelper();
            $heroBonusRob = $helper->calcRobPoints($this->attacker['wave']['heroItems']['leftHand']);
            $total_bounty_percent = 1 + $heroBonusRob / 100;
        }
        if ($this->attacker['player']['aid']) {
            $m = new AllianceModel();
            $m->setAlliancePoints($this->attacker['player']['aid'], $def_total_upkeep, 0, +$total_bounty * $total_bounty_percent);
        }
        if ($this->attacker['uid'] > 2) {
            $m = new PlayerModel();
            $m->setPlayerPoints($this->attacker['uid'], $def_total_upkeep, 0, +$total_bounty * $total_bounty_percent);
        }
    }

    /**
     * @param array<int, int|float> $def_points_share defence points per defending player
     */
    private function setDefenderPoints(int|float $total_bounty, int|float $atk_total_upkeep, array $def_points_share): void
    {
        $playerModel = new PlayerModel();
        $allianceModel = new AllianceModel();

        foreach ($def_points_share as $uid => $share) {
            if ($uid > 2) {
                $bounty = $uid == $this->defender['uid'] ? -$total_bounty : 0;
                $playerModel->setPlayerPoints($uid, 0, $share, $bounty);
                $allianceModel->setAlliancePointsByUid($uid, 0, $share, $bounty);
            }
        }
    }

    private function setCasualties(int|float $atk_total_dead, int|float $def_total_dead): void
    {
        $m = new CasualtiesModel();
        $m->setTodayCasualties($atk_total_dead + $def_total_dead);
    }

    /**
     * @param AttackerWave $wave
     */
    private function getAlive(array $wave, int $i): int|float
    {
        return $wave['units']['num'][$i] - $wave['units']['trapped'][$i] - $wave['units']['dead'][$i];
    }

    /**
     * @return array<int, ReportTroops> the village's own troops first, then each foreign tribe's reinforcements
     */
    private function getDefenderUnitsForReport(): array
    {
        $own = $this->defender['wave'][0];
        $home = [
            'kid' => $own['uniqueId'],
            'race' => $own['race'],
            'num' => $own['units']['num'],
            'dead' => $own['units']['dead'],
            'smithy' => $own['units']['smithy'],
        ];
        if (!$this->defender['isOasis'] || $this->defender['isOccupied']) {
            $home['uid'] = $this->defender['uid'];
            $home['uname'] = $this->defender['player']['name'];
        }
        $foreign = [];
        foreach ($this->defender['wave'] as $key => $army) {
            if ($key == 0) {
                continue;
            }
            if (!$army['isEnforce'] || !array_sum($army['units']['num'])) {
                continue;
            }
            $slots = $army['race'] == 4 ? 10 : 11;
            if (!$army['isForeign']) {
                for ($i = 1; $i <= $slots; ++$i) {
                    $home['num'][$i] += $army['units']['num'][$i];
                    $home['dead'][$i] += $army['units']['dead'][$i];
                }
                continue;
            }
            $foreign[$army['race']] ??= ['race' => $army['race'], 'num' => array_fill(1, $slots, 0), 'dead' => array_fill(1, $slots, 0)];
            for ($i = 1; $i <= $slots; ++$i) {
                $foreign[$army['race']]['num'][$i] += $army['units']['num'][$i];
                $foreign[$army['race']]['dead'][$i] += $army['units']['dead'][$i];
            }
        }

        return [0 => $home] + $foreign;
    }

    /**
     * @return array<int, int|float>
     */
    private function getAvailableResources(): array
    {
        $db = DB::getInstance();
        if ($this->defender['isOasis']) {
            if (!$this->defender['isOccupied']) {
                (new OasesModel())->updateResources($this->row['to_kid']);
                $resources = $this->model->fetchRow("SELECT wood, clay, iron, crop FROM odata WHERE kid={$this->row['to_kid']}");
                $stock = [1 => (float) ($resources['wood'] ?? 0), (float) ($resources['clay'] ?? 0), (float) ($resources['iron'] ?? 0), (float) ($resources['crop'] ?? 0)];
            } else {
                ResourcesHelper::updateVillageResources($this->defender['isOccupied'], true);
                $village = $this->model->fetchRow("SELECT wood, clay, iron, crop FROM vdata WHERE kid={$this->defender['isOccupied']}");
                $lastFarmed = (int) $db->fetchScalar("SELECT lastfarmed FROM odata WHERE kid={$this->row['to_kid']}");
                $rate = min(10, ceil((time() - $lastFarmed) / 60)) / 100;
                $stock = [1 => (float) ($village['wood'] ?? 0) * $rate, (float) ($village['clay'] ?? 0) * $rate, (float) ($village['iron'] ?? 0) * $rate, (float) ($village['crop'] ?? 0) * $rate];
            }
        } else {
            ResourcesHelper::updateVillageResources($this->row['to_kid'], true);
            $resources = $this->model->fetchRow("SELECT wood, clay, iron, crop FROM vdata WHERE kid={$this->row['to_kid']}");
            $stock = [1 => (float) ($resources['wood'] ?? 0), (float) ($resources['clay'] ?? 0), (float) ($resources['iron'] ?? 0), (float) ($resources['crop'] ?? 0)];
        }

        return array_map(static fn (float $x): float => floor(max($x, 0)), $stock);
    }

    private function getTotalCranny(): int|float
    {
        if ($this->defender['isOasis']) {
            return 0;
        }
        $total_cranny = $this->defender['totalCranny'];
        if ($total_cranny) {
            $total_cranny *= ArtefactsModel::getArtifactEffectByType($this->defender['uid'], $this->row['to_kid'], ArtefactsModel::ARTIFACT_CRANNY);
            //*20% for version 3.5 and above. *20% for version 4, but MUST have the hero in the army
            $alive = $this->attacker['wave']['units']['num'][11] - $this->attacker['wave']['units']['trapped'][11] - $this->attacker['wave']['units']['dead'][11];
            $total_cranny *= ($alive && $this->attacker['wave']['race'] == 2) ? (4 / 5) : 1; //attacker teuton bonus
        }

        return $total_cranny;
    }

    /**
     * @return Stats
     */
    private function calc_offense(int|float|string $brewery): array
    {
        // init
        $offense = ['i' => 0, 'c' => 0, 'b' => 1, 't' => 0];
        // units
        $item_bonus = $this->item_unit_bonus($this->attacker['wave']);
        $this->unit_offense($this->attacker['wave']['race'], $this->attacker['wave']['units'], $item_bonus, $offense);
        $offense['i'] *= $this->attacker['wave']['metallurgy'];
        $offense['c'] *= $this->attacker['wave']['metallurgy'];
        // hero // added trapped.
        if ($this->attacker['wave']['units']['num'][11] - $this->attacker['wave']['units']['trapped'][11]) {
            $this->hero_boni($this->attacker['wave'], $offense, true);
        }
        // brewery
        if ($brewery) {
            $offense['b'] *= 1 + (int)$brewery * 0.01;
        }

        return $offense;
    }

    /**
     * @param Wave|AttackerWave $wave
     * @return array<int, int|float>
     */
    private function item_unit_bonus(array $wave): array
    {
        $item_bonus = array_fill(1, 10, 0);
        if (!in_array($wave['race'], [1, 2, 3, 6, 7, 8, 9])) {
            return $item_bonus;
        }
        // set weapon unit bonus
        //right hand
        //process right hand item bonus for units.
        if ($wave['units']['num'][11] - ($wave['units']['trapped'][11] ?? 0) > 0 && is_array($wave['heroItems']['rightHand'])) {
            $item = $this->heroItemsModel->getHeroItemProperties($wave['heroItems']['rightHand']['btype'],
                $wave['heroItems']['rightHand']['type']);
            if (isset($item['unit_power']) && isset($item['unitId'])) {
                $unitId = (int) self::number($item['unitId']);
                $race = unitIdToTribe($unitId);
                $nr = unitIdToNr($unitId);
                if ($race == $wave['race']) {
                    $item_bonus[$nr] += self::number($item['unit_power']);
                }
            }
        }

        return $item_bonus;
    }

    /**
     * @param Stats $stats
     * @param Wave|AttackerWave $wave
     */
    private function hero_boni(array $wave, array &$stats, bool $isOffense): void
    {
        $str = 0;
        // items
        if ($wave['units']['num'][11]) {
            $items = [
                $wave['heroItems']['leftHand'],
                $wave['heroItems']['rightHand'],
                $wave['heroItems']['body'],
            ];
            for ($i = 0; $i < 3; $i++) {
                if (!is_array($items[$i])) {
                    continue;
                }
                $item = $this->heroItemsModel->getHeroItemProperties($items[$i]['btype'], $items[$i]['type']);
                if (isset($item['hero_power'])) {
                    $str += self::number($item['hero_power']);
                }
            }
            // natarian horns
            $leftHand = $wave['heroItems']['leftHand'];
            if (is_array($leftHand)) {
                $item = $this->heroItemsModel->getHeroItemProperties($leftHand['btype'], $leftHand['type']);
                if (isset($item['powerAgainstNatars'])) {
                    $stats['n'] = self::number($item['powerAgainstNatars']);
                }
            }
        }
        $this->set_hero_stats($wave, $str, $stats, $isOffense);
    }

    /**
     * @param Stats $stats
     * @param Wave|AttackerWave $wave
     */
    private function set_hero_stats(array $wave, int|float $str, array &$stats, bool $isOffense): void
    {
        $r = $wave['race'];
        $s = $wave['hero']['power'];
        $st = $this->hero_str4($wave, $s, $r, $str, $isOffense);
        $stats['i'] += $st[0];
        $stats['c'] += $st[1];
        if ($isOffense) {
            $stats['b'] *= 1 + $wave['hero']['offBonus'] * 0.002;
        }
    }

    /**
     * @param Wave|AttackerWave $wave
     * @return array{int|float, int|float}
     */
    private function hero_str4(array $wave, int|float $s, int $r, int|float $str_add, bool $isOffense): array
    {
        $str = (new HeroHelper())->calcPower($r, $s) + $str_add;
        if ($isOffense) {
            $stats = [0, 0];
            $stats[is_array($wave['heroItems']['horse']) ? 1 : 0] += $str;

            return $stats;
        }

        return [$str, $str];
    }

    /**
     * @param Stats $stats
     */
    private function finalize_stats(array &$stats): void
    {
        $stats['i'] *= $stats['b'];
        $stats['c'] *= $stats['b'];
        $stats['t'] = $stats['i'] + $stats['c'];
    }

    /**
     * @param Stats $offense
     * @return Stats
     */
    private function calc_total_defense(array $offense): array
    {
        $defense = ['i' => 0, 'c' => 0, 'b' => 1, 't' => 0];
        $heroBonuses = [];
        foreach ($this->defender['wave'] as $army) {
            if (!empty($army['units']['num'][11])) {
                $heroBonuses[$army['uid']] = $army['hero']['defBonus'];
            }
        }
        foreach ($this->defender['wave'] as &$army) {
            $army['net_def_point'] = $this->calc_defense($army, $defense, $offense, $heroBonuses[$army['uid']] ?? 0);
        }

        return $defense;
    }

    /**
     * @param Stats $defense
     * @param Stats $offense
     * @param Wave $wave
     */
    private function calc_defense(array $wave, array &$defense, array $offense, int|float $heroBonus): int|float
    {
        $def = ['i' => 0, 'c' => 0, 'b' => 1, 't' => 0];
        $item_bonus = $this->item_unit_bonus($wave);
        $this->unit_defense($wave, $item_bonus, $def);
        $def['i'] *= $wave['metallurgy'];
        $def['c'] *= $wave['metallurgy'];

        if (!empty($wave['units']['num'][11])) {
            $this->hero_boni($wave, $def, false);
        }
        $def['b'] *= 1 + $heroBonus * 0.002;
        $defense['i'] += $def['i'] * $def['b'];
        $defense['c'] += $def['c'] * $def['b'];


        return $this->model->adduced_def($def['i'] * $def['b'], $def['c'] * $def['b'], $offense['i'], $offense['c']);
    }

    /**
     * @param array<int, int|float> $item_bonus
     * @param Stats $offense
     * @param AttackerUnits $wave
     */
    private function unit_offense(int $r, array $wave, array $item_bonus, array &$offense): void
    {
        for ($u = 1; $u <= 10; $u++) {
            $up_level = (in_array($r, [1, 2, 3, 6, 7, 8, 9]) and $u <= 8) ? $wave['smithy'][$u] : 0;
            $value = $item_bonus[$u] + $this->model->stat_with_upg($this->unitStat($r, $u, 'off'),
                    $this->unitStat($r, $u, 'cu'),
                    $up_level);

            $type = \Game\Tribes::isCavalry((int)$r, (int)$u) ? 'c' : 'i';

            $offense[$type] += $value * ($wave['num'][$u] - $wave['trapped'][$u]);
        }
    }

    /**
     * @param Stats $def
     * @param array<int, int|float> $item_bonus
     * @param Wave $wave
     */
    private function unit_defense(array $wave, array $item_bonus, array &$def): void
    {
        $r = (int) $this->model->int_check_limit($wave, 'race', 1, 9);
        $units = $wave['units'];
        for ($u = 1; $u <= 10; $u++) {
            $up_level = (in_array($r, [1, 2, 3, 6, 7, 8, 9]) && $u <= 8) ? $units['smithy'][$u] : 0;
            $cu = $this->unitStat($r, $u, 'cu');

            $def_i = $this->model->stat_with_upg($this->unitStat($r, $u, 'def_i'), $cu, $up_level);
            $def_c = $this->model->stat_with_upg($this->unitStat($r, $u, 'def_c'), $cu, $up_level);

            $def['i'] += $units['num'][$u] * ($def_i + $item_bonus[$u]);
            $def['c'] += $units['num'][$u] * ($def_c + $item_bonus[$u]);

        }
    }

    private function unitStat(int $tribe, int $number, string $stat): int
    {
        return (int)Formulas::$data['units'][$tribe - 1][$number - 1][$stat];
    }

    /**
     * @param Losses $losses
     */
    private function finalize_attack(bool|int $no_morale, int|float $pop_ratio, array $losses, int|float $total_off_point, int|float $total_def_point): void
    {
        $this->reportStrength = ['attacker' => (int)$total_off_point, 'defender' => (int)$total_def_point];
        $artifactCaptured = false;
        $this->startProfile('finalize_attack:finalize_attacker_states');
        $attacker = $this->finalize_attacker_states($losses[0]);
        $this->endProfile('finalize_attack:finalize_attacker_states');
        $this->startProfile('finalize_attack:finalize_defender_states');
        $defender = $this->finalize_defender_states($attacker, $losses[1]);
        $this->endProfile('finalize_attack:finalize_defender_states');
        $this->applyBerserkerRetaliation($defender);
        $captured = false;

        $this->startProfile('finalize_attack:handleHeroExperienceAndDamage');
        $this->handleHeroExperienceAndDamage($losses, $defender);
        $this->endProfile('finalize_attack:handleHeroExperienceAndDamage');

        if (!$attacker['total_left']) {
            $this->startProfile('finalize_attack:handlePointsAndBounty');
            $this->handlePointsAndBounty($attacker, $defender, $total_off_point, $total_def_point);
            $this->endProfile('finalize_attack:handlePointsAndBounty');
            $this->info['none_return'] = true;
            unset($this->info['bounty']);
            goto finalize;
        }
        if ($this->getAlive($this->attacker['wave'], 11)) {
            $this->startProfile('finalize_attack:handleOasisCapture');
            $this->handleOasisCapture($defender);
            $this->endProfile('finalize_attack:handleOasisCapture');
        }
        if ($this->row['attack_type'] == MovementsModel::ATTACKTYPE_NORMAL) {
            if (ArtefactsModel::artifactsReleased() && $this->getAlive($this->attacker['wave'], 11)) {
                $this->startProfile('finalize_attack:handleArtefactCapture');
                $artifactCaptured = $this->handleArtefactCapture();
                $this->endProfile('finalize_attack:handleArtefactCapture');
            }
            $this->startProfile('finalize_attack:handleVillageCapture');
            if (!isset($this->info['totally_destroyed']) || !$this->info['totally_destroyed']) {
                $captured = $this->handleVillageCapture($no_morale, $pop_ratio);
            }
            $this->endProfile('finalize_attack:handleVillageCapture');
        }
        $this->startProfile('finalize_attack:handlePointsAndBounty');
        $this->handlePointsAndBounty($attacker, $defender, $total_off_point, $total_def_point, $captured);
        $this->endProfile('finalize_attack:handlePointsAndBounty');
        if ($this->row['attack_type'] == MovementsModel::ATTACKTYPE_NORMAL) {
            $this->startProfile('finalize_attack:handleTrapFreeOnNormalAttack');
            $this->handleTrapFreeOnNormalAttack($attacker);
            $this->endProfile('finalize_attack:handleTrapFreeOnNormalAttack');
        }
        if (!$captured && $this->attacker['uid'] <> 1) {
            $this->startProfile('finalize_attack:finalize_troops_return');
            $this->finalize_troops_return(implode(",", isset($this->info['bounty']) ? $this->info['bounty'] : []));
            $this->endProfile('finalize_attack:finalize_troops_return');
        }
        finalize:
        $db = DB::getInstance();
        $now = floor($this->row['end_time'] / 1000);
        if ($this->defender['isOasis'] && !$this->defender['isOccupied']) {
            $this->startProfile('finalize_attack:addSurrounding');
            $xy = Formulas::kid2xy($this->row['to_kid']);
            NoticeHelper::addSurrounding($xy['x'], $xy['y'], NoticeHelper::SURROUNDING_OASIS_RAID, null, $this->row['end_time_seconds']);
            $this->endProfile('finalize_attack:addSurrounding');
            $attackerUid = $this->attacker['uid'];
            if ($attackerUid > 2) {
                (new SeasonPassModel())->onMissionEvent($attackerUid, 'defeatAnimals');
            }
        }
        if ($this->defender['isOasis']) {
            $db->query("UPDATE odata SET lastfarmed=$now WHERE kid={$this->row['to_kid']}");
        }
        $this->startProfile('finalize_attack:sendBattleReports');
        $this->sendBattleReports($attacker, $defender, $losses[0], $losses[1]);
        $this->endProfile('finalize_attack:sendBattleReports');
        if ($this->defender['uid'] == 1 && $artifactCaptured) {
            $this->startProfile('finalize_attack:deleteVillage');
            (new AccountDeleter())->deleteVillage($this->row['to_kid']);
            $this->endProfile('finalize_attack:deleteVillage');
        }
    }

    /**
     * Viking Berserkers: every Berserker that dies while attacking or raiding takes
     * one defending unit with it. Walls and watchtowers do not reduce this and it is
     * calculated after the main battle: each surviving defender type loses
     * survivors * (killed Berserkers / total survivors). Heroes are not affected
     * (Knowledge Base article 139).
     * @param DefenderOutcome $defender
     */
    private function applyBerserkerRetaliation(array &$defender): void
    {
        if ((int)$this->attacker['wave']['race'] !== 9
            || !in_array((int)$this->row['attack_type'], [MovementsModel::ATTACKTYPE_NORMAL, MovementsModel::ATTACKTYPE_RAID], true)) {
            return;
        }
        $killed = (int)($this->attacker['wave']['units']['dead'][3] ?? 0);
        $survivors = 0;
        foreach ($this->defender['wave'] as $army) {
            for ($i = 1; $i <= 10; ++$i) {
                $survivors += max(0, (int)$army['units']['num'][$i] - (int)$army['units']['dead'][$i]);
            }
        }
        if ($killed <= 0 || $survivors <= 0) {
            return;
        }
        $ratio = min(1, $killed / $survivors);
        $db = DB::getInstance();
        $extraTotal = 0;
        foreach ($this->defender['wave'] as &$army) {
            $modify = [];
            $upkeep = 0;
            $extraDead = [];
            for ($i = 1; $i <= 10; ++$i) {
                $alive = max(0, (int)$army['units']['num'][$i] - (int)$army['units']['dead'][$i]);
                $extra = min($alive, (int)round($alive * $ratio));
                if ($extra <= 0) {
                    continue;
                }
                $army['units']['dead'][$i] += $extra;
                $extraDead[$i] = $extra;
                $extraTotal += $extra;
                $modify[] = "u{$i}=u{$i}-" . $extra;
                $upkeep += $extra * Formulas::uUpkeep(nrToUnitId($i, $army['race']), $this->defender['hdp'], true);
            }
            if (!$modify) {
                continue;
            }
            \Game\Wounded::record((int)($army['isEnforce'] ? $army['kid'] : $army['uniqueId']), (int)$army['race'], $extraDead);
            $set = implode(',', $modify);
            if ($army['isEnforce']) {
                $db->query("UPDATE enforcement SET $set WHERE id=" . (int)$army['uniqueId']);
            } else {
                $db->query("UPDATE units SET $set WHERE kid=" . (int)$army['uniqueId']);
            }
            if ($upkeep > 0 && !$this->defender['isOasis']) {
                ResourcesHelper::modifyUpkeep($this->defender['uid'], $this->row['to_kid'], $upkeep, 1);
            }
        }
        unset($army);
        $defender['total_dead'] += $extraTotal;
        $defender['total_left'] -= $extraTotal;
    }

    /**
     * @return AttackerOutcome
     */
    private function finalize_attacker_states(int|float $off_losses): array
    {
        $db = DB::getInstance();
        $upkeep = $modifiedUpkeep = 0;
        $this->attacker['total_bounty'] = 0;
        $helper = new HeroHelper();


        for ($i = 1; $i <= 11; ++$i) {
            $num = $this->attacker['wave']['units']['num'][$i] - $this->attacker['wave']['units']['trapped'][$i];
            if ($i == 11 && $this->attacker['wave']['units']['num'][11]) {
                if ($this->attacker['wave']['units']['trapped'][11] == 0) {
                    $lossPercent = $off_losses * 100 - $helper->calcResist($this->attacker['wave']['heroItems']['body']);
                    if ($lossPercent < 0) {
                        $lossPercent = 0;
                    }
                    if ($lossPercent >= min(90, $this->attacker['wave']['hero']['health']) || ($this->attacker['wave']['hero']['health'] - $lossPercent < 1)) {
                        //updating resources to now.
                        $this->attacker['wave']['units']['dead'][$i] = 1;
                        $upkeep += 6;
                        ResourcesHelper::updateVillageResources($this->row['kid'], true);
                        $db->query("UPDATE hero SET health=0 WHERE uid=" . $this->attacker['uid']);

                        // Recalculate only the future dead-hero rate after
                        // settling the elapsed interval while the hero lived.
                        ResourcesHelper::updateVillageResources($this->row['kid'], false, false);

                        $modifiedUpkeep += 6;

                    } else if ($lossPercent) {
                        $lossPercent = min($this->attacker['wave']['hero']['health'], $lossPercent);
                        $db->query("UPDATE hero SET health=health-$lossPercent WHERE uid=" . $this->attacker['uid']);
                        BagOintment::healAfterBattle((int)$this->attacker['uid'], $lossPercent);
                    }
                } else {
                    $this->attacker['wave']['units']['dead'][$i] = $dead = 0;
                }
            } else {
                $this->attacker['wave']['units']['dead'][$i] = $dead = min($num, round($num * $off_losses));
                $upkeep += $dead * Formulas::uUpkeep(nrToUnitId($i, $this->attacker['wave']['race']), 0, true);
                $modifiedUpkeep += $dead * Formulas::uUpkeep(nrToUnitId($i, $this->attacker['wave']['race']), $this->attacker['buildings'][41], true);
            }
            if ($i < 11) {
                $alive = $this->attacker['wave']['units']['num'][$i] - $this->attacker['wave']['units']['trapped'][$i] - $this->attacker['wave']['units']['dead'][$i];
                if ($alive) {
                    $this->attacker['total_bounty'] += $alive * Formulas::uCarry(nrToUnitId($i, $this->attacker['wave']['race']));
                }
            }
        }
        \Game\Wounded::record((int)$this->row['kid'], (int)$this->attacker['wave']['race'], $this->attacker['wave']['units']['dead']);
        $move = new MovementsModel();
        $total_trapped = array_sum($this->attacker['wave']['units']['trapped']);
        if ($total_trapped) {//auto move to traps!
            $trappedId = $move->isSameVillageTrappedExists($this->row['kid'], $this->row['to_kid']);
            if ($trappedId !== false) {
                $modify = [];
                for ($i = 1; $i <= 11; ++$i) {
                    $trapped = $this->attacker['wave']['units']['trapped'][$i];
                    if ($trapped) {
                        $modify[] = "u{$i}=u{$i}+" . $trapped;
                    }
                }
                $db->query("UPDATE trapped SET " . implode(",", $modify) . " WHERE id=$trappedId");
            } else {
                $move->addTrapped($this->row['kid'], $this->row['to_kid'], $this->row['race'], $this->attacker['wave']['units']['trapped']);
            }
        }
        if ($this->attacker['wave']['units']['num'][11] > 0) {
            $_revived = $this->get_revived($this->attacker['wave']);
            if (0 < ($bandages = array_sum($_revived))) {
                $neededTime = 1000 * max(86400 / getGame('movement_speed_increase'), 300);
                $move->addMovement($this->row['kid'], $this->row['kid'], $this->row['race'], $_revived, 0, 0, 0, 0, 1, MovementsModel::ATTACKTYPE_REINFORCEMENT, $this->row['end_time'], $this->row['end_time'] + $neededTime, null);
                $this->useBagItems($this->attacker['uid'], $this->attacker['wave']['heroItems']['bag'], $bandages);
            }
        }
        $total = array_sum($this->attacker['wave']['units']['num']);
        $totalDead = array_sum($this->attacker['wave']['units']['dead']);

        if ($modifiedUpkeep > 0) {
            ResourcesHelper::modifyUpkeep($this->attacker['uid'], $this->row['kid'], $modifiedUpkeep, 1);
        }
        return [
            'total_dead' => array_sum($this->attacker['wave']['units']['dead']),
            'total_dead_upkeep' => $upkeep,//for top 10 points.
            'total_trapped' => $total_trapped,
            'total_left' => $total - $totalDead - $total_trapped,
        ];
    }

    /**
     * @param Wave|AttackerWave $army
     * @return array<int, int|float>
     */
    private function get_revived(array $army): array
    {
        if (empty($army['units']['num'][11])) {
            $bandages = 0;
        } else {
            $dead = array_fill(1, 10, 0);
            for ($u = 1; $u <= 10; $u++) {
                $un = $army['units']['dead'][$u];
                $dead[$u] = $un;
            }
            $helper = new HeroHelper();
            $total = array_sum($dead);
            $bandages = $helper->getBandages($army['heroItems']['bag']);
            $bandages = min($bandages['num'], ceil($total * $bandages['eff'] / 100));
        }
        if (!$bandages) {
            return [];
        }

        return $this->model->move_to_traps($dead, $bandages);
    }

    /**
     * @param AttackerOutcome $attacker
     * @return DefenderOutcome
     */
    private function finalize_defender_states(array $attacker, int|float $def_losses): array
    {
        $def_points_share = [];
        $db = DB::getInstance();
        $total_dead = $upkeep = $modifiedUpkeep = 0;
        $total_num = 0;
        $move = new MovementsModel();
        $helper = new HeroHelper();

        $heroArmies = [];
        $totalDefendingSupply = 0;
        foreach ($this->defender['wave'] as $value) {
            $supply = $this->defendingSupply($value);
            $totalDefendingSupply += $supply;
            if (empty($value['units']['num'][11])) {
                continue;
            }
            $heroArmies[(int)$value['uid']] = [
                'supply' => $supply,
                'helmetBonus' => (int) $helper->calcMoreExp($value['heroItems']['helmet']),
            ];
        }
        $heroExperience = CombatExperience::defensiveShares(
            $heroArmies,
            $totalDefendingSupply,
            (int)$attacker['total_dead_upkeep'],
            Formulas::getHeroExpLevelMultiplier()
        );

        foreach ($this->defender['wave'] as &$value) {
            if ($this->defender['total_net_def_point'] == 0) {
                $share = 0;
            } else {
                $share = $attacker['total_dead_upkeep'] * (($value['net_def_point'] ?? 0) / $this->defender['total_net_def_point']);
            }
            $def_points_share[$value['uid']] = ($def_points_share[$value['uid']] ?? 0) + $share;
            //logError('Share: %s: %s, net_def=%s', [$value['uid'], $share, $value['net_def_point']]);
            $total = array_sum($value['units']['num']);
            $total_num += $total;
            $modify = [];
            for ($i = 1; $i <= ($value['race'] == 4 ? 10 : 11); ++$i) {
                if ($i == 11 && $value['units']['num'][11]) {
                    $experience = $heroExperience[(int)$value['uid']] ?? 0;
                    $lossPercent = $def_losses * 100 - $helper->calcResist($value['heroItems']['body']);
                    if ($lossPercent < 0)
                        $lossPercent = 0;
                    if ($lossPercent >= min(90, $value['hero']['health']) || ($value['hero']['health'] - $lossPercent < 1)) {
                        $dead = 1;
                        $upkeep += 6;
                        $modifiedUpkeep += 6;
                        $total_dead++;
                        //updating resources to now.
                        ResourcesHelper::updateVillageResources($value['kid'], true);
                        $db->query("UPDATE hero SET health=0 WHERE health>0 AND uid=" . $value['uid']);

                        // Recalculate only the future dead-hero rate after
                        // settling the elapsed interval while the hero lived.
                        ResourcesHelper::updateVillageResources($value['kid'], false, false);
                    } else {
                        $dead = 0;
                        if ($lossPercent) {
                            $db->query("UPDATE hero SET health=health-$lossPercent WHERE uid={$value['uid']}");
                            BagOintment::healAfterBattle((int)$value['uid'], $lossPercent);
                        }
                    }
                    // Experience after damage: a level-up heals only when the hero is still alive.
                    if ($experience > 0) {
                        HeroExperience::add((int)$value['uid'], $experience);
                    }
                } else {
                    $dead = min($value['units']['num'][$i], round($value['units']['num'][$i] * $def_losses));
                    $total_dead += $dead;
                    $upkeep += $dead * Formulas::uUpkeep(nrToUnitId($i, $value['race']), 0, true);
                    $modifiedUpkeep += $dead * Formulas::uUpkeep(nrToUnitId($i, $value['race']), $this->defender['hdp'], true);
                }
                if ($dead > 0) {
                    $modify[] = "u{$i}=u{$i}-" . $dead;
                }
                $value['units']['dead'][$i] = $dead;
            }
            // Wounded go home with the troops that were hit: reinforcements to their own village.
            \Game\Wounded::record((int)($value['isEnforce'] ? $value['kid'] : $value['uniqueId']), (int)$value['race'], $value['units']['dead']);

            if ($value['units']['num'][11]) {
                $_revived = $this->get_revived($value);
                $bandages = array_sum($_revived);
                if ($bandages > 0) {
                    $neededTime = 1000 * max(86400 / getGame('movement_speed_increase'), 300);
                    if ($value['isEnforce']) {
                        $move->addMovement($value['kid'], $value['kid'], $value['race'], $_revived, 0, 0, 0, 0, 1, MovementsModel::ATTACKTYPE_REINFORCEMENT, $this->row['end_time'], $this->row['end_time'] + $neededTime, null);
                    } else {
                        $move->addMovement($this->row['to_kid'], $this->row['to_kid'], $value['race'], $_revived, 0, 0, 0, 0, 1, MovementsModel::ATTACKTYPE_REINFORCEMENT, $this->row['end_time'], $this->row['end_time'] + $neededTime, null);
                    }
                    $this->useBagItems((int) $value['hero']['uid'], $value['heroItems']['bag'], $bandages);
                }
            }
            if ($value['isEnforce']) {
                if (!($total - array_sum($value['units']['dead']))) {
                    $db->query("DELETE FROM enforcement WHERE id=" . $value['uniqueId']);
                } else if (sizeof($modify)) {
                    $set = implode(",", $modify);
                    $db->query("UPDATE enforcement SET $set WHERE id=" . $value['uniqueId']);
                }
            } else if (sizeof($modify)) {
                $set = implode(",", $modify);
                $db->query("UPDATE units SET $set WHERE kid=" . $value['uniqueId']);
            }
        }
        $this->startProfile('finalize_defender_states:modifyUpkeep');
        if ($this->defender['isOasis']) {
            if ($this->defender['isOccupied']) {
                ResourcesHelper::modifyUpkeep($this->defender['uid'], $this->defender['isOccupied'], $modifiedUpkeep, 1);
            }
        } else {
            ResourcesHelper::modifyUpkeep($this->defender['uid'], $this->row['to_kid'], $modifiedUpkeep, 1);
        }
        $this->endProfile('finalize_defender_states:modifyUpkeep');
        return [
            'total_dead' => $total_dead,
            'total_dead_upkeep' => $upkeep,
            'total_num' => $total_num,
            'total_left' => $total_num - $total_dead,
            'def_points_share' => $def_points_share,
        ];
    }

    /**
     * @param Losses $losses
     * @param DefenderOutcome $defender
     */
    private function handleHeroExperienceAndDamage(array $losses, array $defender): void
    {
        if ($this->attacker['wave']['units']['num'][11]) {
            $helper = new HeroHelper();
            $exp = CombatExperience::offensive(
                (int)$defender['total_dead_upkeep'],
                (int) $helper->calcMoreExp($this->attacker['wave']['heroItems']['helmet']),
                Formulas::getHeroExpLevelMultiplier()
            );
            // Damage was applied earlier; level-up heals only when the attacker is still alive.
            if ($exp > 0) {
                HeroExperience::add((int)$this->attacker['uid'], $exp);
            }
        }
    }

    /**
     * @param Wave $wave
     */
    private function defendingSupply(array $wave): int
    {
        $supply = 0;
        for ($unit = 1; $unit <= 10; ++$unit) {
            $supply += (int)($wave['units']['num'][$unit] ?? 0)
                * Formulas::uUpkeep(nrToUnitId($unit, (int)$wave['race']), 0, true);
        }

        return $supply + (!empty($wave['units']['num'][11]) ? 6 : 0);
    }

    /**
     * @param AttackerOutcome $attacker
     * @param DefenderOutcome $defender
     */
    private function handlePointsAndBounty(array $attacker, array $defender, int|float $total_off_point, int|float $total_def_point, bool $captured = false): void
    {
        $total_bounty = 0;
        if (!$captured) {
            $this->startProfile('handlePointsAndBounty:modifyBounty');
            $this->modifyBounty();
            $bounty = $this->info['bounty'] ?? [];
            $total_bounty = array_sum($bounty) - ($bounty[5] ?? 0);
            $this->endProfile('handlePointsAndBounty:modifyBounty');
        }
        if (getDisplay('topHammers')) {
            $this->handleTopDefTopOffHammerStatistics($total_off_point, $total_def_point);
        }
        $this->startProfile('handlePointsAndBounty:setAttackerPoints');
        $this->setAttackerPoints($total_bounty, $defender['total_dead_upkeep']);
        $this->endProfile('handlePointsAndBounty:setAttackerPoints');
        $this->startProfile('handlePointsAndBounty:setDefenderPoints');
        $this->setDefenderPoints($total_bounty, $attacker['total_dead_upkeep'], $defender['def_points_share']);
        $this->endProfile('handlePointsAndBounty:setDefenderPoints');
        $this->startProfile('handlePointsAndBounty:setCasualties');
        $this->setCasualties($attacker['total_dead'], $defender['total_dead']);
        $this->endProfile('handlePointsAndBounty:setCasualties');
        $this->startProfile('handlePointsAndBounty:handleDailyQuestAchivements');
        $this->handleDailyQuestAchivements();
        $this->endProfile('handlePointsAndBounty:handleDailyQuestAchivements');
    }

    private function modifyBounty(): void
    {
        //modify bounty.
        $total_bounty = $maxCarryLoad = $this->attacker['total_bounty'];
        $resources = $this->get_available_resources_to_raid();
        if ($this->isFarm || $this->defender['uid'] == 1) {
            $resources = array_map(function ($x) {
                return floor($x * 0.5);
            }, $resources);
        }
        if ($total_bounty >= array_sum($resources)) {
            $this->info['bounty'] = $resources;
        } else {
            $this->info['bounty'] = [1 => 0, 0, 0, 0];
            $x = 0;
            $divFactor = 4;
            while ($maxCarryLoad > 0) {
                ++$x;
                $curTotalRes = 0;
                $div = floor($maxCarryLoad / $divFactor);
                for ($i = 1; $i <= 4; ++$i) {
                    $take = min($div, $resources[$i], $maxCarryLoad);
                    $maxCarryLoad -= $take;
                    $resources[$i] -= $take;
                    $this->info['bounty'][$i] += $take;
                    $curTotalRes += $take;
                }
                if ($curTotalRes <= 0 && $divFactor == 1) {
                    break;
                }
                $divFactor = 1;
            }
        }
        $this->set_village_or_oasis_resources($this->info['bounty']);
        $this->info['bounty'][] = $total_bounty;
    }

    /**
     * @return array<int, int|float>
     */
    private function get_available_resources_to_raid(): array
    {
        $resources = $this->getAvailableResources();
        $cranny = $this->getTotalCranny();
        for ($i = 1; $i <= 4; ++$i) {
            $resources[$i] = max(0, $resources[$i] - $cranny);
        }
        return $resources;
    }

    /**
     * @param array<int, int|float> $steal
     */
    private function set_village_or_oasis_resources(array $steal): bool
    {
        if (!array_sum($steal)) {
            return true;
        }
        if ($this->defender['isOasis']) {
            if (!$this->defender['isOccupied']) {
                $this->model->modifyOasisResources($this->row['to_kid'], [$steal[1], $steal[2], $steal[3], $steal[4]]);
            } else {
                $this->model->modifyVillageResources($this->defender['isOccupied'], [
                    $steal[1],
                    $steal[2],
                    $steal[3],
                    $steal[4],
                ]);
            }
        } else {
            $this->model->modifyVillageResources($this->row['to_kid'], [$steal[1], $steal[2], $steal[3], $steal[4]]);
        }
        return true;
    }

    private function handleTopDefTopOffHammerStatistics(int|float $total_off_point, int|float $total_def_point): void
    {
        $db = DB::getInstance();
        $db->query("UPDATE users SET max_off_point=GREATEST(max_off_point, $total_off_point) WHERE id={$this->attacker['uid']}");
        if ($db->affectedRows()) {
            $this->isTopOff = true;
            $db->query("UPDATE users SET max_off_time=" . $this->row['end_time_seconds'] . " WHERE id={$this->attacker['uid']}");
        }
        if ($this->defender['uid'] > 2) {
            $db->query("UPDATE users SET max_def_point=GREATEST(max_def_point, $total_def_point) WHERE id={$this->defender['uid']}");
            if ($db->affectedRows()) {
                $this->isTopDef = true;
                $db->query("UPDATE users SET max_def_time=" . $this->row['end_time_seconds'] . " WHERE id={$this->defender['uid']}");
            }
        }
    }

    private function handleDailyQuestAchivements(): void
    {
        if ($this->defender['isOasis'] && !$this->defender['isOccupied']) {
            (new DailyQuestModel())->setQuestAsCompleted($this->attacker['uid'], 2);
        }
        if (!$this->defender['isOasis'] && $this->defender['uid'] == 1) {
            (new DailyQuestModel())->setQuestAsCompleted($this->attacker['uid'], 3);
        }
    }

    /**
     * @param $defender
     * @param array<int|string, mixed> $defender
     */
    private function handleOasisCapture(array $defender): void
    {
        $db = DB::getInstance();
        $oasis = new OasesModel();
        $heroMansion = (int)$this->attacker['buildings'][37];

        if ($heroMansion <= 0) {
            return;
        }

        if ($this->defender['isOasis'] && $oasis->getOasisCaptureKid($this->row['to_kid']) != $this->row['kid'] && !$defender['total_left']) {
            $distance = Formulas::getDistance($this->row['kid'], $this->row['to_kid']);
            if ($distance < 4.9497474683058326708059105347339) {
                $oasesCount = (int)$db->fetchScalar("SELECT COUNT(kid) FROM odata WHERE did={$this->row['kid']}");
                $slots = max(0, floor(($heroMansion - 5) / 5)) - $oasesCount;
                if ($this->row['attack_type'] == MovementsModel::ATTACKTYPE_RAID) {
                    if ($slots > 0 && !$this->defender['isOccupied']) {
                        $this->info['oasisCapture'] = 0;
                        OasesModel::captureOasis($this->row['to_kid'], $this->attacker['uid'], $this->row['kid']);
                    } else if ($slots <= 0 && !$this->defender['isOccupied']) {
                        $this->info['oasisCapture'] = -1;
                    }
                } else {
                    LoyaltyHelper::updateOasisLoyalty($this->row['to_kid']);
                    $oasis = $db->query("SELECT loyalty, did FROM odata WHERE kid={$this->row['to_kid']}")->fetch_assoc();
                    $ownerOases = (int)$db->fetchScalar('SELECT COUNT(kid) FROM odata WHERE did=' . (int)$oasis['did']);
                    if ($oasis['did'] != $this->row['kid']) {
                        if ($slots <= 0) {
                            $this->info['oasisCapture'] = -1;
                        } else {
                            $loyaltyChange = max(0, min(floor(100 / max(1, min(3, (4 - $ownerOases)))), $oasis['loyalty']));
                            if ($loyaltyChange >= $oasis['loyalty']) {
                                OasesModel::releaseOasis($this->row['to_kid'], (int) $oasis['did']);
                                OasesModel::captureOasis($this->row['to_kid'], $this->attacker['uid'], $this->row['kid']);
                                $this->info['oasisCapture'] = 0;
                            } else {
                                $this->info['oasisCapture'] = [
                                    $oasis['loyalty'],
                                    ceil($oasis['loyalty'] - $loyaltyChange),
                                ];
                                $db->query("UPDATE odata SET loyalty=loyalty-$loyaltyChange WHERE kid={$this->row['to_kid']}");
                            }
                        }
                    }
                }
            }
        }
    }

    private function handleArtefactCapture(): bool
    {
        $db = DB::getInstance();
        $arts = new ArtefactsModel();
        if (!$this->defender['isOasis'] && $this->getAlive($this->attacker['wave'], 11)) {
            $villageArtifacts = $db->query("SELECT * FROM artefacts WHERE kid={$this->row['to_kid']}");
            while (($artifact = $villageArtifacts->fetch_assoc())) {
                $result = $arts->canClaimArtifact(
                    (int) $artifact['size'],
                    $this->row['kid'],
                    $this->attacker['buildings'],
                    $this->defender['buildings']
                );
                if ($result === true) {
                    $arts->captureArtefact($artifact, $this->row['kid'], $this->attacker['uid']);
                    if ($artifact['type'] == 12) {
                        $this->info['heroPlanCapture'] = true;
                    } else {
                        $this->info['heroArtifactCapture'] = true;
                    }
                    return true;
                } else {
                    $this->info['captureError'] = $result;
                }
            }
        }
        return false;
    }

    /**
     * @param $no_morale
     * @param $pop_ratio
     * @return bool
     */
    private function handleVillageCapture(bool|int $no_morale, int|float $pop_ratio): bool
    {
        $db = DB::getInstance();
        if ($this->row['attack_type'] == MovementsModel::ATTACKTYPE_NORMAL && !$this->defender['isOasis'] && $this->getAlive($this->attacker['wave'], 9)) {
            //king.
            if ($this->model->is_capital($this->row['to_kid'])) {
                $this->info['captureResult'] = -1;
            } else {
                //Only 1 WW village can be captured at a time
                if (getCustom("allowOnlyOneWWVillagePerAccount") && $this->defender['isWW'] && ($db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE isWW=1 AND owner={$this->attacker['uid']}")) >= 1) {
                    $this->info['captureResult'] = -4;
                } else {
                    return $this->handleVillageCaptured($no_morale, $pop_ratio);
                }
            }
        }
        return false;
    }

    /**
     * @param $no_morale
     * @param $pop_ratio
     * @return bool
     */
    private function handleVillageCaptured(bool|int $no_morale, int|float $pop_ratio): bool
    {
        $captured = false;
        $db = DB::getInstance();
        $total_villages = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE owner={$this->attacker['uid']}");
        if (!($this->attacker['uid'] == $this->defender['uid']) &&
            $this->attacker['player']['cp'] < Formulas::newVillageCP($total_villages + 1)) {
            $this->info['captureResult'] = -2;
        } else {
            if ($this->model->get_building_lvl(-1, 25, $this->defender['buildings']) > 0 ||
                $this->model->get_building_lvl(-1, 26, $this->defender['buildings']) > 0 ||
                $this->model->get_building_lvl(-1, 44, $this->defender['buildings']) > 0) {
                $this->info['captureResult'] = -3;
            } elseif (($blocked = LoyaltyProtection::blockedBy((int) $this->attacker['uid'], (int) $this->defender['uid'], (int) $this->row['to_kid'])) !== null) {
                // Allies cannot lower the loyalty and the report says nothing; a NAP is reported.
                if ($blocked === LoyaltyProtection::NAP) {
                    $this->info['captureResult'] = -5;
                }
            } else {
                $alive_admins = $this->getAlive($this->attacker['wave'], 9);
                $af = self::ADMINISTRATOR_EFFECT[$this->row['race']];
                $moral_bonus = $no_morale ? 1 : $this->model->common_morale($pop_ratio);
                $ld = (!$this->model->is_great_celebration_running($this->row['kid']) ? 0 : 5) - (!$this->model->is_great_celebration_running($this->row['to_kid']) ? 0 : 5);
                $B = false;
                if ($this->row['race'] == 2) {
                    $p = new VillageModel();
                    $B = $p->getCapBrewery($this->attacker['uid']) > 0;
                }
                $c = $moral_bonus / ($B ? 2 : 1);
                $loy = [
                    round(($af[0] + $ld) * $c),
                    round(($af[1] + $ld) * $c),
                ];
                $loyaltyChange = 0;
                for ($i = 1; $i <= $alive_admins; ++$i) {
                    $loyaltyChange += mt_rand((int) $loy[0], (int) $loy[1]);
                }
                LoyaltyHelper::updateVillageLoyalty($this->row['to_kid']);
                $village = $db->query("SELECT loyalty FROM vdata WHERE kid={$this->row['to_kid']}")->fetch_assoc();
                if ($loyaltyChange >= floor((float) $village['loyalty'])) {
                    $this->info['captureResult'] = 0;
                    $p = new VillageModel();
                    $captured = $p->captureVillage($this->defender['uid'], $this->row['to_kid'], $this->defender['total_pop'], $this->attacker['uid'], $this->attacker['total_pop'], $this->row['race'], $this->row['kid']);
                    if ($captured) {
                        $move = new MovementsModel();
                        $units = [];
                        for ($i = 1; $i <= 11; ++$i) {
                            $units[$i] = $this->getAlive($this->attacker['wave'], $i);
                            if ($i == 9) {
                                $units[$i]--;
                            }
                        }
                        $move->addEnforce($this->attacker['uid'], $this->row['kid'], $this->row['to_kid'], $this->row['race'], $units);
                        {
                            ResourcesHelper::updateVillageUpkeep($this->attacker['uid'], $this->row['kid']);
                            ResourcesHelper::updateVillageUpkeep($this->attacker['uid'], $this->row['to_kid']);
                        }
                    }
                } else {
                    $this->info['captureResult'] = [
                        $village['loyalty'],
                        $village['loyalty'] - $loyaltyChange,
                    ];
                    $db->query("UPDATE vdata SET loyalty=loyalty-$loyaltyChange WHERE kid={$this->row['to_kid']}");
                }
            }
            return $captured;
        }
        return false;
    }

    /**
     * @param AttackerOutcome $attacker
     */
    private function handleTrapFreeOnNormalAttack(array $attacker): void
    {
        if (!($attacker['total_left'] && !$this->defender['isOasis'] && $this->row['attack_type'] == MovementsModel::ATTACKTYPE_NORMAL)) {
            return;
        }
        $db = DB::getInstance();
        $this->info['free'] = [0, 0];

        $total_all = 0;
        $helper = new AccountDeleter();
        $traps = $db->query("SELECT * FROM trapped WHERE to_kid={$this->row['to_kid']}");
        while (($trappedRow = $traps->fetch_assoc())) {
            $owner = $this->model->getOwner($trappedRow['kid']);
            $isMine = $owner == $this->attacker['uid'];
            $isAlliance = false;
            if (!$isMine && $this->attacker['player']['aid']) {
                $isAlliance = $this->model->getUser($owner)['aid'] == $this->attacker['player']['aid'];
            }
            if (!$isMine && !$isAlliance) {
                continue;
            }
            $total_sum = 0;
            for ($i = 1; $i <= 10; ++$i) {
                $total_all += $trappedRow['u' . $i];
                $trappedRow['u' . $i] = ceil($trappedRow['u' . $i] * 0.75);
                $total_sum += $trappedRow['u' . $i];
            }
            if ($trappedRow['u11']) {
                $health = $db->fetchScalar("SELECT health FROM hero WHERE kid={$trappedRow['kid']}");
                if ($health <= 75) {
                    $trappedRow['u11'] = 0;
                    $db->query("UPDATE hero SET health=0 WHERE kid={$trappedRow['kid']}");
                } else {
                    $total_sum += 1;
                    $db->query("UPDATE hero SET health=health-75 WHERE kid={$trappedRow['kid']}");
                }
            }
            $helper->returnTrappedOrEnforcementRow($trappedRow, false, $this->row['end_time']);
            $this->info['free'][$isMine ? 0 : 1] += $total_sum;
        }
        if (!array_sum($this->info['free'])) {
            unset($this->info['free']);
        }
        if ($total_all) {
            // Remove traps
            $db->query("UPDATE units SET u99=IF(u99-$total_all>0, u99-$total_all, 0) WHERE kid={$this->row['to_kid']}");
        }
    }

    private function finalize_troops_return(?string $data = null): bool
    {
        //need to return units.
        $calculator = new SpeedCalculator();
        $calculator->setFrom($this->row['to_kid']);
        $calculator->setTo($this->row['kid']);
        $calculator->isReturn();
        $calculator->setTournamentSqLvl($this->attacker['buildings'][14]);
        $speeds = $alive = $units_id = [];
        $alive = array_fill(1, 11, 0);
        for ($i = 1; $i <= 11; ++$i) {
            $alive[$i] = $this->getAlive($this->attacker['wave'], $i);
            if ($i == 11) {
                continue;
            }
            if ($alive[$i]) {
                $speeds[] = Formulas::uSpeed(nrToUnitId($i, $this->attacker['wave']['race']));
                $units_id[] = (nrToUnitId($i, $this->attacker['wave']['race']));
            }
        }
        $calculator->hasCata($alive[8] > 0);
        $helper = new HeroHelper();
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($this->attacker['uid'], $this->row['kid'], ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        if ($alive[11]) {
            $calculator->hasHero();
            $calculator->setLeftHand($this->attacker['wave']['heroItems']['leftHand']);
            $calculator->setShoes($this->attacker['wave']['heroItems']['shoes']);
            if ($this->attacker['player']['aid'] > 0) {
                if ($this->attacker['player']['aid'] == $this->defender['player']['aid']) {
                    $calculator->isAlliance();
                } else {
                    $db = DB::getInstance();
                    $aid = $this->attacker['player']['aid'];
                    $to_aid = $this->defender['player']['aid'];
                    $diplomacy = $db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE accepted=1 AND type=1 AND ((aid1=$aid AND aid2=$to_aid) OR (aid1=$to_aid AND aid2=$aid))");
                    if ($diplomacy > 0) {
                        $calculator->isAlliance();
                    }
                }
            }
            if ($this->attacker['uid'] == $this->defender['uid']) {
                $calculator->isOwn();
            }
            $cavalryOnly = $calculator->isCavalryOnly($units_id);
            $speeds[] = $helper->calcTotalSpeed(
                $this->attacker['wave']['race'],
                $this->attacker['wave']['heroItems']['horse'],
                $this->attacker['wave']['heroItems']['shoes']
            );
            $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
                $this->attacker['wave']['race'],
                $this->attacker['wave']['heroItems']['horse'],
                $cavalryOnly
            ));
            if (array_sum($alive) > 1) {
                $calculator->troopsWithHero();
            }
        }
        $calculator->setMinSpeed($speeds);
        $calculator->setShip($this->row['ship'], (int)$this->row['race']);
        $move = new MovementsModel();
        $move->addMovement($this->row['to_kid'], $this->row['kid'], $this->row['race'], $alive, 0, 0, 0, 0, 1, $this->row['attack_type'], $this->row['end_time'], $this->row['end_time'] + $calculator->calc() * 1000, $data, $this->row['ship']);
        return true;
    }

    /**
     * @param array<int|string, mixed> $attacker
     * @param array<int|string, mixed> $defender
     */
    private function sendBattleReports(array $attacker, array $defender, int|float $atk_losses, int|float $def_losses): bool
    {
        $this->startProfile("getDefenderUnitsForReport");
        $defenderReport = $this->getDefenderUnitsForReport();
        $this->endProfile('getDefenderUnitsForReport');
        $report = [
            'losses' => [$atk_losses, $def_losses],
            'attacker' => [
                'kid' => $this->row['kid'],
                'uid' => $this->attacker['uid'],
                'uname' => $this->attacker['player']['name'],
                'race' => $this->attacker['wave']['race'],
                'num' => $this->attacker['wave']['units']['num'],
                'dead' => $this->attacker['wave']['units']['dead'],
                'smithy' => $this->attacker['wave']['units']['smithy'],
                'trapped' => array_sum($this->attacker['wave']['units']['trapped']) ? $this->attacker['wave']['units']['trapped'] : null,
            ],
            'defender' => $defenderReport,
            'info' => $this->info + $this->raidInfo(),
            'strength' => $this->reportStrength,
        ];
        $bounty = '';
        if (isset($report['info']['bounty'])) {
            $bounty = implode(',', array_values($report['info']['bounty']));
            unset($report['info']['bounty']);
        }
        if ($attacker['total_dead'] <= 0 && $attacker['total_left'] > 0) {
            $reportType = NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES;
        } else if ($attacker['total_left']) {
            $reportType = NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES;
        } else {
            $reportType = NoticeHelper::TYPE_LOST_AS_ATTACKER;
        }
        $tmp = $report;
        //get a temp to save attacker report.
        if ($def_losses < 0.25 && !$attacker['total_left']) {
            if ($this->attacker['uid'] != $this->defender['uid']) {
                $tmp['defender'] = [$tmp['defender'][0]];//hiding reinforces.
                $tmp['defender'][0]['num'] = array_fill(1, $tmp['defender'][0]['race'] == 4 ? 10 : 11, '?');
                $tmp['defender'][0]['dead'] = null;
            }
        }
        $this->startProfile("sendBattleReports:sendAttackerReport");
        $noticeId = NoticeHelper::addNotice($this->attacker['player']['aid'],
            $this->attacker['uid'],
            $this->row['kid'],
            $this->row['to_kid'],
            $reportType,
            $bounty,
            $tmp,
            $this->row['end_time_seconds'],
            false,
            $atk_losses * 100,
            $this->isTopOff);
        if ($reportType === NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES
            && $this->row['data'] === \Game\FarmList\FarmListSender::ONLY_LOSSES_MOVEMENT_DATA) {
            // Farm list "Only create reports with losses": keep the row for the
            // list's last-raid column, but never surface it as a report.
            DB::getInstance()->query("UPDATE ndata SET deleted=1, viewed=1 WHERE id=" . (int)$noticeId);
        }
        if ($this->isTopOff) {
            $db = DB::getInstance();
            $db->query("UPDATE users SET max_off_nid=$noticeId WHERE id={$this->attacker['uid']}");
        }
        $this->endProfile("sendBattleReports:sendAttackerReport");
        unset($tmp);
        if ($this->defender['uid'] <= 1) {
            return false;
        }
        $this->startProfile("sendBattleReports:sendDefendersReport");
        if (!$attacker['total_left'] && !$defender['total_dead']) {
            $reportType = NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES;
        } else if ($defender['total_left'] && $defender['total_num'] && $defender['total_dead']) {
            $reportType = NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES;
        } else if (!$defender['total_left'] && !$defender['total_dead']) {
            $reportType = NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES;
        } else {
            $reportType = NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES;
        }
        //sending reinforces owners a report.
        $uid = [];
        foreach ($this->defender['wave'] as $k) {
            if ($k['race'] == 4 || !$k['isEnforce'] || !$k['isForeign'] || $k['uid'] == $this->defender['uid']) {
                continue;
            }
            $uid[] = $k['uid'];
        }
        $uid = array_unique($uid);
        $tmp = $report;
        unset($tmp['info']['escape']);
        if (sizeof($uid) >= 20 && $reportType == NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES) {
            //do nothing
        } else {
            foreach ($uid as $u) {
                $tmp['defender'] = $this->filter_units_for_player($u);
                NoticeHelper::addNotice(0,
                    $u,
                    $this->row['kid'],
                    $this->row['to_kid'],
                    $reportType,
                    '',
                    $tmp,
                    $this->row['end_time_seconds'],
                    true,
                    ($reportType == NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES ? 0 : $def_losses) * 100);
            }
        }
        if ($this->defender['uid'] > 1) {
            if ($this->attacker['uid'] != $this->defender['uid']) {
                $noticeId = NoticeHelper::addNotice($this->defender['player']['aid'],
                    $this->defender['uid'],
                    $this->row['kid'],
                    $this->row['to_kid'],
                    $reportType,
                    $bounty,
                    $report,
                    $this->row['end_time_seconds'],
                    false,
                    ($reportType == NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES ? 0 : $def_losses) * 100,
                    $this->isTopDef);
                if ($this->isTopDef) {
                    $db = DB::getInstance();
                    $db->query("UPDATE users SET max_def_nid=$noticeId WHERE id={$this->defender['uid']}");
                }
            }
        }
        $this->endProfile("sendBattleReports:sendDefendersReport");
        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function filter_units_for_player(int|string $uid): array
    {
        $assoc[] = [
            'kid' => $this->defender['wave'][0]['uniqueId'],
            'race' => $this->defender['wave'][0]['race'],
            'num' => array_fill(1, $this->defender['wave'][0]['race'] == 4 ? 10 : 11, '?'),
            'dead' => null,
            'smithy' => $this->defender['wave'][0]['units']['smithy'],
        ];
        if (!$this->defender['isOasis']) {
            $assoc[0]['uid'] = $this->defender['uid'];
            $assoc[0]['uname'] = $this->defender['player']['name'];
        }
        foreach ($this->defender['wave'] as $row) {
            if (!$row['isEnforce'] || !$row['isForeign'] || !array_sum($row['units']['num']) || $row['uid'] != $uid) {
                continue;
            }
            $assoc[] = [
                'uid' => $uid,
                'uname' => $this->model->getUser($uid)['name'],
                'kid' => $row['kid'],
                'race' => $row['race'],
                'num' => $row['units']['num'],
                'dead' => $row['units']['dead'],
                'smithy' => $row['units']['smithy'],
            ];
        }
        return $assoc;
    }

    /**
     * @param Stats $offense
     */
    private function lone_dies(array $offense, int|float $pop_ratio): bool
    {
        return round($offense['t'] * $this->model->common_morale($pop_ratio)) <= self::LONE_ATTACKER_THRESHOLD;
    }

    private function isFarm(): bool
    {
        return $this->isFarm;
    }

    private function finalize_ram_damage(): bool
    {
        $damage = $this->info['rams'] ?? [0, 0, 0];
        if ($damage[2] == $damage[1]) {
            return false;
        }
        BuildingAction::downgrade($this->row['to_kid'], 40, $damage[1] - $damage[2], $damage[2] == 0);
        $def =& $this->defender;
        $def['wall'] = (int) $damage[2];
        $def['buildings'][40]['level'] = (int) $damage[2];
        if ($damage[2] == 0 && !BuildingAction::building_upgrade_state($this->row['to_kid'], 40)) {
            $def['buildings'][40]['item_id'] = 0;
        }
        $this->checkIfVillageIsDestroyed();
        return true;
    }

    private function checkIfVillageIsDestroyed(): void
    {
        $db = DB::getInstance();
        $pop = $db->fetchScalar("SELECT pop FROM vdata WHERE kid={$this->row['to_kid']}");
        if ($pop > 0) return;
        $deleter = new AccountDeleter();
        $reason = $deleter->isVillageDestroyAble($this->attacker['uid'], $this->row['to_kid'], $this->defender['uid']);
        if (true === $reason) {
            $deleter->deleteVillage($this->row['to_kid'], false);
            $this->info['totally_destroyed'] = true;
        } else {
            $this->info['not_destroyed_reason'] = $reason;
        }
    }

    /**
     * @param Buildings $buildings
     */
    private function find_building_by_gid(int $gid, array $buildings): int
    {
        if ($gid != 99) {
            $targets = [];
            foreach ($buildings as $key => $build) {
                if (!$build['level'] || in_array($gid, \Game\Formulas::WALL_BUILDINGS)) {
                    continue;
                }
                if ($build['item_id'] == $gid) {
                    $targets[] = $key;
                }
            }
            $size = sizeof($targets);
            if ($size) {
                shuffle($targets);
                if ($size == 1) {
                    return $targets[0];
                }
                return $targets[mt_rand(0, $size - 1)];
            }
        }
        $free = [];
        foreach ($buildings as $k => $build) {
            if ($build['item_id'] && $build['level'] && !in_array($build['item_id'], \Game\Formulas::WALL_BUILDINGS)) {
                $free[] = $k;
            }
        }
        if (!sizeof($free)) {
            return 0;
        }
        shuffle($free);
        $find = $free[mt_rand(0, sizeof($free) - 1)];
        return $find ? $find : 0;
    }

    /**
     * @param array<int, int|float> $damage
     */
    private function finalize_cata_damage(int $field, array &$damage): bool
    {
        if ($damage[2] == $damage[1] || !isset($this->defender['buildings'][$field])) {
            return false;
        }
        BuildingAction::downgrade($this->row['to_kid'], $field, $damage[1] - $damage[2], $damage[2] == 0);
        $level = (int) $damage[2];
        $itemId = $this->defender['buildings'][$field]['item_id'];
        if (($field > 18 && $field < 99) && $level == 0 && !BuildingAction::building_upgrade_state($this->row['to_kid'], $field)) {
            $itemId = 0;
            BuildingAction::removeDemolitionsOnField($this->row['to_kid'], $field);
        }
        $this->defender['buildings'][$field] = ['item_id' => $itemId, 'level' => $level];
        if ($itemId == 41) {
            $this->defender['hdp'] = $level;
        }

        return true;
    }

    private function processScoutAttack(bool|int $no_morale, int|float $pop_ratio, int $sui, int|float $wall_b): void
    {
        $offense = ($no_morale ? 1 : $this->model->common_morale($pop_ratio)) * $this->attacker['wave']['units']['num'][$sui] * $this->model->stat_with_upg(self::SCOUT_OFF, Formulas::uUpkeep(nrToUnitId($sui, $this->row['race'])), $this->attacker['wave']['units']['smithy'][$sui]);
        $atkArtifactEffect = $this->model->art_effect(ArtefactsModel::getArtifactEffectByType($this->attacker['uid'], $this->row['kid'], ArtefactsModel::ARTIFACT_SPY), 10);
        $offense *= $atkArtifactEffect;
        $defense = 0;
        foreach ($this->defender['wave'] as &$army) {
            $army['net_def_point'] = 0;
            if ($army['race'] == 4) {
                continue;
            }
            $r = $army['race'];
            $sui = Formulas::getSpyId($r);
            $total_this = $army['units']['num'][$sui] * $this->model->stat_with_upg(self::SCOUT_DEF, Formulas::uUpkeep(nrToUnitId($sui, $r), 0, true), $army['units']['smithy'][$sui]);
            $spyArtifactEffect = $army['isEnforce'] ? 1 : $this->model->art_effect(ArtefactsModel::getArtifactEffectByType($army['uid'], $army['kid'], ArtefactsModel::ARTIFACT_SPY), 10);
            $this->defender['total_net_def_point'] += $total_this;
            $army['net_def_point'] = $total_this;
            $defense += $total_this * $spyArtifactEffect;
        }
        $defense *= round(pow($wall_b, $this->defender['wall']), 3); // wall
        if ($defense == 0) {
            $off_losses = 0;
        } else {
            $x = pow($offense / $defense, 1.5);
            $off_losses = $x ? min(1 / $x, 1) : 1;
        }
        $this->finalize_spy_attack($off_losses, $offense, $defense);
    }
}
