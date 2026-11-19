<?php

declare(strict_types=1);

use Controller\RallyPoint\Simulator;
use Core\Database\DB;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Hero\HeroItems;
use Model\BattleModel;
use Model\BattleSetter;

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';
require_once __DIR__ . '/../support/village-fixture.php';

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, (string)$exception . PHP_EOL);
    exit(1);
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$battleClass = new ReflectionClass(BattleModel::class);
$battle = $battleClass->newInstanceWithoutConstructor();
$battleClass->getProperty('model')->setValue($battle, new BattleSetter());
$battleClass->getProperty('heroItemsModel')->setValue($battle, new HeroItems());
$failures = [];
$check = static function (float $expected, float $actual, string $label) use (&$failures): void {
    if (abs($expected - $actual) > 0.00001) {
        $failures[] = "$label: expected $expected, got $actual";
    }
};
$wave = static function (int $race = 1, int $uid = 10, int $kid = 100): array {
    return [
        'race' => $race, 'uid' => $uid, 'kid' => $kid, 'metallurgy' => 1,
        'units' => [
            'num' => array_fill(1, 11, 0),
            'trapped' => array_fill(1, 11, 0),
            'smithy' => array_fill(1, 10, 0),
        ],
        'hero' => ['power' => 0, 'offBonus' => 0, 'defBonus' => 0],
        'heroItems' => array_fill_keys(['leftHand', 'rightHand', 'body', 'horse'], 0),
    ];
};
$offense = static function (array $army) use ($battleClass, $battle): array {
    $battleClass->getProperty('attacker')->setValue($battle, ['wave' => $army]);
    $stats = $battleClass->getMethod('calc_offense')->invoke($battle, 0);
    $battleClass->getMethod('finalize_stats')->invokeArgs($battle, [&$stats]);
    return $stats;
};
$defense = static function (array $armies) use ($battleClass, $battle): array {
    $battleClass->getProperty('defender')->setValue($battle, ['wave' => $armies]);
    return $battleClass->getMethod('calc_total_defense')->invoke($battle, ['i' => 1000, 'c' => 0]);
};

foreach ([1 => 1100, 2 => 900, 3 => 900, 6 => 900, 7 => 900, 8 => 900, 9 => 900] as $race => $expected) {
    $army = $wave($race);
    $army['units']['num'][11] = 1;
    $army['hero']['power'] = 10;
    $check($expected, $offense($army)['i'], "Tribe $race hero attack at 10 strength points");
    $check($expected, $defense([$army])['i'], "Tribe $race hero infantry defence at 10 strength points");
}

$army = $wave();
$army['units']['num'][1] = 100;
$army['units']['num'][11] = 1;
$army['heroItems']['rightHand'] = ['btype' => 4, 'type' => 16];
$base = $wave();
$base['units']['num'][1] = 100;
$base['units']['num'][11] = 1;
$equipment = (new HeroItems())->getHeroItemProperties(4, 16);
$check(100 * $equipment['unit_power'] + $equipment['hero_power'],
    $defense([$army])['i'] - $defense([$base])['i'], 'Weapon defence bonus is per matching soldier');
$army['units']['num'][1] = 0;
$check($equipment['hero_power'], $defense([$army])['i'] - $defense([$wave()])['i'] - 100,
    'No phantom troop defence from an empty weapon target slot');

$army = $wave();
$army['units']['num'][1] = 100;
$army['units']['num'][11] = 1;
$army['units']['trapped'][11] = 1;
$army['hero']['offBonus'] = 100;
$army['heroItems']['rightHand'] = ['btype' => 4, 'type' => 16];
$check(4000, $offense($army)['i'], 'A trapped hero grants neither weapon nor offensive attribute bonuses');

$home = $wave();
$home['units']['num'][1] = 100;
$home['units']['num'][11] = 1;
$home['hero']['defBonus'] = 100;
$ownReinforcement = $wave(1, 10, 101);
$ownReinforcement['units']['num'][1] = 100;
$ally = $wave(1, 20, 200);
$ally['units']['num'][1] = 100;
$check((3500 + 100) * 1.2 + 3500 * 1.2 + 3500,
    $defense([$home, $ownReinforcement, $ally])['i'],
    'Hero defence attribute covers own other-village troops but not allied troops');

$home['heroItems']['rightHand'] = ['btype' => 4, 'type' => 16];
$check((3500 + 300 + 350) * 1.2 + 3500 * 1.2 + 3500,
    $defense([$home, $ownReinforcement, $ally])['i'],
    'Weapon bonuses stay home-village scoped while the defence attribute covers own reinforcements');
$home['units']['smithy'][1] = 20;
$home['metallurgy'] = 1.1;
$home['hero']['offBonus'] = 100;
$upgradedAttack = (new BattleSetter())->stat_with_upg(40, 1, 20);
$upgradedDefence = (new BattleSetter())->stat_with_upg(35, 1, 20);
$check((100 * ($upgradedAttack + 3) * 1.1 + 350) * 1.2, $offense($home)['i'],
    'Smithy, weapon, metallurgy and hero attack bonuses stack without upgrading hero strength');
$check((100 * ($upgradedDefence + 3) * 1.1 + 350) * 1.2, $defense([$home])['i'],
    'Smithy, weapon, metallurgy and hero defence bonuses stack without upgrading hero strength');
$mounted = $wave();
$mounted['units']['num'][11] = 1;
$mounted['hero']['power'] = 10;
$mounted['heroItems']['horse'] = ['btype' => 6, 'type' => 103];
$check(0, $offense($mounted)['i'], 'Mounted hero contributes no infantry attack');
$check(1100, $offense($mounted)['c'], 'Mounted Roman hero contributes cavalry attack');
foreach ([79 => 20, 80 => 25, 81 => 30] as $horn => $bonus) {
    $mounted['heroItems']['leftHand'] = ['btype' => 3, 'type' => $horn];
    $check($bonus, $offense($mounted)['n'], "Natar horn $horn bonus with a participating hero");
    $mounted['units']['trapped'][11] = 1;
    $check(0, $offense($mounted)['n'] ?? 0, "Natar horn $horn disabled with a trapped hero");
    $mounted['units']['trapped'][11] = 0;
}

$simulator = new Simulator();
$simClass = new ReflectionClass(Simulator::class);
foreach ([
    1 => ['base' => 1.03, 'points' => 10, 'durability' => 1],
    2 => ['base' => 1.02, 'points' => 6, 'durability' => 5],
    3 => ['base' => 1.025, 'points' => 8, 'durability' => 2],
    6 => ['base' => 1.025, 'points' => 8, 'durability' => 4],
    7 => ['base' => 1.015, 'points' => 6, 'durability' => 1],
    8 => ['base' => 1.02, 'points' => 10, 'durability' => 2],
    9 => ['base' => 1.015, 'points' => 6, 'durability' => 3],
] as $race => $expectedWall) {
    // The wall tables live once: Formulas::WALL_BASE, and BattleModel's WALL_EXTRA and WALL_DURABILITY, read by the
    // battle, the simulator and the wall page.
    $check($expectedWall['base'], Formulas::WALL_BASE[$race], "Wall base for tribe $race");
    $check($expectedWall['points'], BattleModel::WALL_EXTRA[$race], "Wall points for tribe $race");
    $check($expectedWall['durability'], BattleModel::WALL_DURABILITY[$race], "Wall durability for tribe $race");
}
foreach (['wall_extra', 'wall_durability', 'administrator_effect'] as $property) {
    if ($simClass->hasProperty($property) || (new ReflectionClass(BattleModel::class))->hasProperty($property)) {
        fwrite(STDERR, "The $property table must exist only as a BattleModel constant.\n");
        exit(1);
    }
}
foreach ([1, 2, 3, 6, 7, 8, 9] as $race) {
    $simWave = ['r' => $race - 1, 'u' => array_fill(0, 11, 0), 'U' => array_fill(0, 10, 0)];
    $simWave['u'][0] = 100;
    $simWave['U'][0] = 20;
    $stats = ['i' => 0, 'c' => 0];
    $simClass->getMethod('unit_defense')->invokeArgs($simulator, [$simWave, array_fill(0, 10, 0), &$stats]);
    $baseDef = Formulas::$data['units'][$race - 1][0]['def_i'];
    $check(100 * (new BattleSetter())->stat_with_upg($baseDef, 1, 20), $stats['i'], "Simulator tribe $race smithy defence");
    $simWave['U'][0] = 0;
    $stats = ['i' => 0, 'c' => 0];
    $simClass->getMethod('unit_defense')->invokeArgs($simulator, [$simWave, array_fill(0, 10, 0), &$stats]);
    $check(100 * $baseDef, $stats['i'], "Simulator tribe $race zero upgrades preserve base defence");
}

$items = new HeroItems();
foreach ([16, 19, 22, 25, 28, 31, 34, 37, 40, 43, 46, 49, 52, 55, 58, 115, 118, 121, 124, 127, 130, 133, 136, 139, 142] as $baseType) {
    foreach ([250, 1000, 4000] as $tier => $strength) {
        $check($strength, $items->getHeroItemProperties(4, $baseType + $tier)['hero_power'], "Weapon $baseType tier " . ($tier + 1) . ' strength');
    }
}
foreach ([76, 88] as $baseType) {
    foreach ([250, 1000, 4000] as $tier => $strength) {
        $check($strength, $items->getHeroItemProperties($baseType === 76 ? 3 : 2, $baseType + $tier)['hero_power'], "Equipment $baseType tier " . ($tier + 1) . ' strength');
    }
}
foreach ([125, 500, 2000] as $tier => $strength) {
    $check($strength, $items->getHeroItemProperties(2, 91 + $tier)['hero_power'], 'Segmented armour tier ' . ($tier + 1));
}
foreach ([85 => [10, 4], 86 => [15, 6], 87 => [20, 8]] as $type => [$regen, $resist]) {
    $properties = $items->getHeroItemProperties(2, $type);
    $check($regen, $properties['reg'], "Scale armour $type regeneration");
    $check($resist, $properties['resist'], "Scale armour $type damage reduction");
}
foreach ([9, 12, 15] as $tier => $unitPower) {
    $check($unitPower, $items->getHeroItemProperties(4, 25 + $tier)['unit_power'], 'Imperatoris weapon per-unit bonus tier ' . ($tier + 1));
}
foreach ([6, 8, 10] as $tier => $unitPower) {
    $check($unitPower, $items->getHeroItemProperties(4, 40 + $tier)['unit_power'], 'Druidrider weapon per-unit bonus tier ' . ($tier + 1));
}
foreach ([250, 1000, 4000] as $tier => $strength) {
    $army = $wave();
    $army['units']['num'][11] = 1;
    $army['hero']['power'] = 10;
    $army['heroItems']['rightHand'] = ['btype' => 4, 'type' => 16 + $tier];
    $army['heroItems']['leftHand'] = ['btype' => 3, 'type' => 76 + $tier];
    $army['heroItems']['body'] = ['btype' => 2, 'type' => 88 + $tier];
    $expected = 1100 + 3 * $strength;
    $check($expected, $offense($army)['i'], 'Equipped hero fighting strength in the actual battle calculator');
    $check($expected, (new HeroHelper())->calcTotalPower(1, 10, $army['heroItems']['rightHand'], $army['heroItems']['leftHand'], $army['heroItems']['body']),
        'Hero profile and battle strength use the same equipment values');
}

$db = DB::getInstance();
// Two throwaway Roman players (a host and a reinforcer), cloned from a world capital and removed at shutdown.
$players = array_map(static function (array $fixture): array {
    return ['kid' => $fixture['kid'], 'owner' => $fixture['id']];
}, [fixtureVillageCreate(1), fixtureVillageCreate(1)]);
$db->begin_transaction();
try {
    $host = (int)$players[0]['owner'];
    $reinforcer = (int)$players[1]['owner'];
    $target = (int)$players[0]['kid'];
    $origin = (int)$players[1]['kid'];
    $db->query("INSERT INTO alidata (name, tag, armor_bonus_level) VALUES ('Combat boost fixture', 'CBTEST', 5)");
    $aid = (int)$db->lastInsertId();
    $joined = time() - 10 * 86400;
    $db->query("UPDATE users SET aid=0 WHERE id=$host");
    $db->query("UPDATE users SET aid=$aid, alliance_join_time=$joined WHERE id=$reinforcer");
    $db->query("UPDATE units SET u1=100,u2=0,u3=0,u4=0,u5=0,u6=0,u7=0,u8=0,u9=0,u10=0,u11=0 WHERE kid=$target");
    $db->query("UPDATE users SET escape=0 WHERE id=$host");
    $db->query("DELETE FROM enforcement WHERE to_kid=$target");
    $db->query("INSERT INTO enforcement (uid, kid, to_kid, race, u1) VALUES ($reinforcer, $origin, $target, 1, 100)");
    $db->query("UPDATE smithy SET u1=0 WHERE kid IN ($target,$origin)");
    $battleClass->getProperty('row')->setValue($battle, ['to_kid' => $target, 'kid' => $origin, 'end_time' => miliseconds(), 'attack_type' => 4]);
    $battleClass->getMethod('assocDefender')->invoke($battle);
    $stats = $battleClass->getMethod('calc_total_defense')->invoke($battle, ['i' => 1000, 'c' => 0]);
    $check(3500 + 3500 * 1.1, $stats['i'], 'Reinforcement metallurgy follows its owner, not the host alliance');
    $db->query("UPDATE users SET aid=$aid,alliance_join_time=$joined WHERE id=$host");
    $db->query("UPDATE users SET aid=0 WHERE id=$reinforcer");
    $battleClass->getMethod('assocDefender')->invoke($battle);
    $stats = $battleClass->getMethod('calc_total_defense')->invoke($battle, ['i' => 1000, 'c' => 0]);
    $check(3500 * 1.1 + 3500, $stats['i'], 'Host metallurgy does not leak to unbonused reinforcements');
    $attackingWave = $wave(1, $host, $target);
    $attackingWave['units']['num'][1] = 100;
    $row = ['kid' => $target, 'race' => 1];
    foreach ($attackingWave['units']['num'] as $unit => $count) {
        $row["u$unit"] = $count;
    }
    $battleClass->getProperty('row')->setValue($battle, $row);
    $battleClass->getMethod('assocAttacker')->invoke($battle);
    $stats = $battleClass->getMethod('calc_offense')->invoke($battle, 0);
    $check(4400, $stats['i'], 'Attacker assembly loads its owner metallurgy');

    foreach ([2, 3, 4, 5] as $level) {
        $db->query("UPDATE alidata SET armor_bonus_level=$level,training_bonus_level=$level,cp_bonus_level=$level,trade_bonus_level=$level WHERE id=$aid");
        $delay = (int)ceil(($level - 1) * 86400 / getGameSpeed());
        $check(1 + ($level - 1) * 0.02, AllianceBonus::getArmorBonus($aid, time() - $delay + 1), "Metallurgy level $level join cooldown");
        $check(1 + $level * 0.02, AllianceBonus::getArmorBonus($aid, time() - $delay), "Metallurgy level $level exact unlock boundary");
        $check(1.02, AllianceBonus::getArmorBonus($aid, time()), "New member retains level 1 metallurgy in a level $level alliance");
        $check(0.98, AllianceBonus::getTrainingBonus($aid, time()), 'Recruitment uses the same progressive join rule');
        $check(1.04, AllianceBonus::getCulturePointProductionBonus($aid, time()), 'Philosophy uses the same progressive join rule');
        $check(1.3, AllianceBonus::getTradersBonus($aid, time()), 'Commerce uses the same progressive join rule');
    }
} finally {
    $db->rollback();
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Combat hero/equipment values, owner-scoped bonuses, stacking, trapped/mounted heroes, horns, progressive alliance unlocks and all-tribe simulator upgrades passed (DB fixtures rolled back).\n";
