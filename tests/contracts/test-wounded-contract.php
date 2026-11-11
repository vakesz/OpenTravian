#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: wounded troops follow Knowledge Base article 77 (Hospital for every
 * tribe but Spartans, Asclepeion for Spartans) and the Berserker rule follows
 * article 139. See docs/research/travian-tribe-data.md and the README speed profile.
 */

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\Wounded;

function failWoundedContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertWounded(bool $condition, string $message): void
{
    if (!$condition) {
        failWoundedContract($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

assertWounded(Wounded::share(Wounded::HOSPITAL) === 0.4 && Wounded::share(Wounded::ASCLEPEION) === 0.6, 'Hospital returns 40% of the losses, Asclepeion 60%.');
assertWounded(Wounded::buildingFor(8) === 48 && Wounded::buildingFor(1) === 46 && Wounded::buildingFor(9) === 46, 'Spartans heal in the Asclepeion, every other tribe in the Hospital.');
foreach ([1, 2, 3, 4, 5, 6] as $nr) {
    assertWounded(Wounded::canBeWounded(1, $nr) === ($nr <= 6), "Unit $nr of a playable tribe is woundable only for infantry, scouts and cavalry.");
}
foreach ([7, 8, 9, 10, 11] as $nr) {
    assertWounded(!Wounded::canBeWounded(1, $nr), "Siege engines, administrators, settlers and heroes (unit $nr) never become wounded.");
}
assertWounded(!Wounded::canBeWounded(4, 1) && !Wounded::canBeWounded(5, 1), 'Nature and Natars have no hospital.');
assertWounded(Wounded::woundedFor(100, 0.4) === 40 && Wounded::woundedFor(100, 0.6) === 60 && Wounded::woundedFor(1, 0.4) === 0, 'Wounded are a share of the losses.');
assertWounded(abs(Wounded::decayed(100.0, 86400) - 90.0) < 1e-9 && abs(Wounded::decayed(100.0, 2 * 86400) - 81.0) < 1e-9 && Wounded::decayed(100.0, 0) === 100.0, 'Unhealed wounded decay by 10% per day.');

// Healing takes half the training time at the same level and follows world speed like training.
$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
try {
    $config->game->extra_training_time_multiplier = 1;
    foreach ([1, 2, 10] as $speed) {
        $config->game->speed = $speed;
        $config->game->useMilSeconds = $config->game->speed > 20;
        $config->game->useNanoseconds = $config->game->speed > 20000;
        foreach ([[71, 1], [71, 20], [1, 10], [86, 5]] as [$unitId, $level]) {
            $second = $config->game->useNanoseconds ? 1_000_000_000 : ($config->game->useMilSeconds ? 1000 : 1);
            $expected = max($second, (int)round(Formulas::uTrainingTime($unitId, $level) * 0.5));
            assertWounded(Formulas::uHealingTime($unitId, $level) === $expected, "Healing unit $unitId at level $level and speed $speed is half its training time and at least one second.");
        }
    }
    $config->game->speed = 1;
    $config->game->useMilSeconds = $config->game->speed > 20;
    $config->game->useNanoseconds = $config->game->speed > 20000;
    assertWounded(Formulas::uHealingTime(71, 1) === 850 && Formulas::uHealingTime(71, 20) === (int)round(Formulas::uTrainingTime(71, 20) / 2), 'A level-1 Asclepeion heals a Hoplite in half of the 1700 s training time.');
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
}

// Storage round trip with a village id that cannot exist.
$db = DB::getInstance();
$kid = 4000000000;
Wounded::purge($kid);
try {
    $db->query("INSERT INTO wounded (kid, nr, num, updated_at) VALUES ($kid, 1, 10, " . (time() - 86400) . ")");
    $db->query("INSERT INTO wounded (kid, nr, num, updated_at) VALUES ($kid, 4, 3, " . time() . ")");
    assertWounded(Wounded::all($kid) === [1 => 9, 4 => 3], 'A day-old stack of 10 shows as 9; fresh wounded are untouched.');
    assertWounded(Wounded::take($kid, 1, 9) && !Wounded::take($kid, 1, 1), 'Troops entering the healing queue leave the wounded pool exactly once.');
    assertWounded(Wounded::all($kid) === [4 => 3], 'Taken troops are gone from the pool.');
    assertWounded(Wounded::record($kid, 4, [1 => 10]) === [], 'Nature units never become wounded.');
} finally {
    Wounded::purge($kid);
}
assertWounded(Wounded::all($kid) === [], 'Purge removes every wounded troop of a village.');

// Wiring.
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$battle = $read('game/include/Model/BattleModel.php');
assertWounded(substr_count($battle, '\\Game\\Wounded::record(') === 3, 'The attacker, every defending army and the Berserker rule must record wounded.');
assertWounded(str_contains($battle, 'private function applyBerserkerRetaliation') && str_contains($battle, '$survivors * (killed') === false && str_contains($battle, '$ratio = min(1, $killed / $survivors);'), 'Berserker retaliation distributes killed Berserkers over the survivors.');
assertWounded(str_contains($read('game/include/Model/VillageModel.php'), '\\Game\\Wounded::purge') && str_contains($read('game/include/Model/AccountDeleter.php'), 'Wounded::purge('), 'Conquest and village deletion remove the wounded.');
assertWounded(str_contains($read('game/include/Controller/BuildCtrl.php'), 'new HospitalCtrl($this->selectedBuildingIndex)'), 'Hospital and Asclepeion open the healing page.');
assertWounded(str_contains($read('game/include/Model/TrainingModel.php'), 'public function enqueue'), 'Healing queues without counting as a training mission.');
assertWounded(str_contains($read('database/world.sql'), 'CREATE TABLE `wounded`'), 'world.sql defines the wounded table.');

echo "Wounded troops contract passed.\n";
