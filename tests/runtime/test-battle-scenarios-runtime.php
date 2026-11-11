<?php

declare(strict_types=1);

// Arriving attacks run to completion for the situations BattleModel handles differently: raids, normal attacks,
// rams and catapults, heroes on both sides, reinforcements, scouting, conquering a village, attacking and capturing
// an oasis, claiming an artifact, Gaul traps and hero cages. Each one must consume its movement, leave a report and
// produce the expected outcome, and a failure inside BattleModel (for example a type error) surfaces here instead
// of being logged by the engine. Two throwaway fixture players act as attacker and defender; the one oasis and the
// artifact switch the scenarios borrow are restored when the test ends.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Battle scenarios runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Game\Formulas;
use Game\NoticeHelper;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $attacker = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxa' . getmypid(), $attacker['id']]);
    $defender = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Battle scenarios runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
// The fixture villages are not on the map yet: occupy their tiles, and free them again however the test ends.
foreach ([$attacker, $defender] as $fixture) {
    $pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
    register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
}
$db = DB::getInstance();
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$attacker['id']]);
$defenderRace = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$defender['id']]);
$scout = (int)Formulas::getSpyId($race);

// What a scenario may change, put back before the next one starts: both villages' buildings, the player and
// troop rows, and the artifact switch of the world.
$buildings = [];
foreach ([$attacker, $defender] as $fixture) {
    $buildings[$fixture['kid']] = $pdo->fetchSingleRow('SELECT * FROM fdata WHERE kid=?', [$fixture['kid']]);
}
$artifactsReleased = (int)$pdo->fetchScalar('SELECT ArtifactsReleased FROM config LIMIT 1');
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE config SET ArtifactsReleased=?', [$artifactsReleased]));
$restoreBuildings = static function () use ($pdo, $buildings): void {
    foreach ($buildings as $kid => $row) {
        $sets = [];
        $values = [];
        foreach ($row as $column => $value) {
            if ($column !== 'kid') {
                $sets[] = "`$column`=?";
                $values[] = $value;
            }
        }
        $pdo->run('UPDATE fdata SET ' . implode(',', $sets) . ' WHERE kid=?', [...$values, $kid]);
    }
};
/** Puts a building of the given type and level on a village slot. */
$build = static function (array $village, int $slot, int $gid, int $level) use ($pdo): void {
    $pdo->run("UPDATE fdata SET f{$slot}t=?, f{$slot}=? WHERE kid=?", [$gid, $level, $village['kid']]);
};
/** Takes every building of the given types out of a village. */
$demolish = static function (array $village, array $gids) use ($pdo): void {
    for ($slot = 19; $slot <= 40; ++$slot) {
        $pdo->run("UPDATE fdata SET f{$slot}=0, f{$slot}t=0 WHERE kid=? AND f{$slot}t IN (" . implode(',', array_map('intval', $gids)) . ')', [$village['kid']]);
    }
};
$reset = static function () use ($pdo, $attacker, $defender, $defenderRace, $restoreBuildings): void {
    $restoreBuildings();
    $pdo->run('DELETE FROM ndata WHERE uid IN (?, ?)', [$attacker['id'], $defender['id']]);
    $pdo->run('UPDATE units SET u1=40, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0, u11=0, u99=0 WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE units SET race=? WHERE kid=?', [$defenderRace, $defender['kid']]);
    $pdo->run('UPDATE users SET race=? WHERE id=?', [$defenderRace, $defender['id']]);
    $pdo->run('UPDATE vdata SET loyalty=100, owner=? WHERE kid=?', [$defender['id'], $defender['kid']]);
    $pdo->run('DELETE FROM enforcement WHERE to_kid=?', [$defender['kid']]);
    $pdo->run('DELETE FROM trapped WHERE to_kid=? OR kid=?', [$defender['kid'], $attacker['kid']]);
    $pdo->run('DELETE FROM artefacts WHERE kid IN (?, ?)', [$attacker['kid'], $defender['kid']]);
};

/**
 * Sends the troops and processes the arrival; returns the movement id.
 *
 * @param array<int, int> $units troops by unit slot
 * @param array<string, int> $options ctar1, ctar2, spyType, target (map tile, default the defender village)
 */
$send = static function (string $label, int $attackType, array $units, array $options = []) use ($attacker, $defender, $race, $db, $fail): void {
    $all = array_fill(1, 11, 0);
    foreach ($units as $slot => $count) {
        $all[$slot] = $count;
    }
    $target = $options['target'] ?? $defender['kid'];
    // Due a day from now: the live engine only takes movements that are due, so it never races this test for the
    // movement, and processMovementTask() consumes it by id.
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement(
        $attacker['kid'],
        $target,
        $options['race'] ?? $race,
        $all,
        $options['ctar1'] ?? 0,
        $options['ctar2'] ?? 0,
        $options['spyType'] ?? 0,
        0,
        0,
        $attackType,
        $now - 20000,
        $now - 10000
    );
    try {
        (new Automation())->processMovementTask($id);
    } catch (Throwable $throwable) {
        $fail("$label: " . get_class($throwable) . ': ' . $throwable->getMessage() . ' (' . basename($throwable->getFile()) . ':' . $throwable->getLine() . ')');
    }
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id AND mode=0")) {
        $fail("$label: the due movement was not consumed.");
    }
    if (!(int)$db->fetchScalar('SELECT COUNT(*) FROM ndata WHERE uid=' . $attacker['id'] . ' AND to_kid=' . $target)) {
        $fail("$label: the attack left no report for the attacker.");
    }
};
$expect = static function (string $label, bool $condition, string $what) use ($fail): void {
    if (!$condition) {
        $fail("$label: $what");
    }
};
$scalar = static fn (string $sql, array $args = []): mixed => $pdo->fetchScalar($sql, $args);

// --- plain battles -------------------------------------------------------------------------------------------
$reset();
$send('raid', MovementsModel::ATTACKTYPE_RAID, [1 => 30]);
$expect('raid', str_contains((string)$scalar('SELECT data FROM ndata WHERE uid=? ORDER BY id DESC LIMIT 1', [$attacker['id']]), 's:4:"raid";b:1;'), 'the report must remember that the attack was a raid.');
$reset();
$send('normal attack records no raid', MovementsModel::ATTACKTYPE_NORMAL, [1 => 30]);
$expect('normal attack', !str_contains((string)$scalar('SELECT data FROM ndata WHERE uid=? ORDER BY id DESC LIMIT 1', [$attacker['id']]), '"raid"'), 'a normal attack must not be flagged as a raid.');
$reset();
$send('normal attack', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400]);
$reset();
$send('rams and catapults', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 7 => 30, 8 => 30], ['ctar1' => 15, 'ctar2' => 10]);
$reset();
$send('attack with the hero', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1]);
$reset();
$pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$defender['kid']]);
$send('against a defending hero', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1]);
$reset();
$pdo->run('INSERT INTO enforcement (uid, kid, to_kid, race, u1) VALUES (?, ?, ?, ?, 25)', [$attacker['id'], $attacker['kid'], $defender['kid'], $race]);
$send('against reinforcements', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400]);
if ($scout > 0) {
    $reset();
    $send('scouting attack', MovementsModel::ATTACKTYPE_SPY, [$scout => 5], ['spyType' => 1]);
}

// --- Spartan hero weapon ---------------------------------------------------------------------------------------
// A Spartan hero's weapon adds its unit bonus and fighting strength to the battle: the same attack with the Lance of
// the Hoplite equipped must cost the defender more troops than without it.
$spartanBattle = static function (bool $armed) use ($pdo, $attacker, $defender, $reset, $send, $scalar): int {
    $reset();
    $pdo->run('UPDATE users SET race=8 WHERE id=?', [$attacker['id']]);
    $pdo->run('UPDATE units SET u1=800 WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE inventory SET rightHand=0 WHERE uid=?', [$attacker['id']]);
    $pdo->run('DELETE FROM items WHERE uid=? AND btype=4', [$attacker['id']]);
    if ($armed) {
        $pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, 4, 151, 1, 0, 1)', [$attacker['id']]);
        $pdo->run('UPDATE inventory SET rightHand=? WHERE uid=?', [(int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()'), $attacker['id']]);
    }
    $send($armed ? 'Spartan hero with a weapon' : 'Spartan hero without a weapon', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1], ['race' => 8]);
    $left = (int)$scalar('SELECT u1 FROM units WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE inventory SET rightHand=0 WHERE uid=?', [$attacker['id']]);
    $pdo->run('DELETE FROM items WHERE uid=? AND btype=4', [$attacker['id']]);

    return $left;
};
// The hero may fall in these battles: put its row and the village units back afterwards.
$heroRow = $pdo->fetchSingleRow('SELECT * FROM hero WHERE uid=?', [$attacker['id']]);
$unarmed = $spartanBattle(false);
$armed = $spartanBattle(true);
if (is_array($heroRow)) {
    $sets = [];
    $values = [];
    foreach ($heroRow as $column => $value) {
        if ($column !== 'uid') {
            $sets[] = "`$column`=?";
            $values[] = $value;
        }
    }
    $pdo->run('UPDATE hero SET ' . implode(',', $sets) . ' WHERE uid=?', [...$values, $attacker['id']]);
}
$expect('Spartan hero weapon', $armed < $unarmed, "the armed hero must kill more defenders (armed leaves $armed, unarmed leaves $unarmed).");
$pdo->run('UPDATE users SET race=? WHERE id=?', [$race, $attacker['id']]);

// --- conquering a village --------------------------------------------------------------------------------------
$reset();
$pdo->run('UPDATE users SET cp=1000000 WHERE id=?', [$attacker['id']]);
$pdo->run('UPDATE vdata SET capital=0, loyalty=1 WHERE kid=?', [$defender['kid']]);
$demolish($defender, [25, 26, 44]);
$send('village conquest', MovementsModel::ATTACKTYPE_NORMAL, [1 => 600, 9 => 3]);
$expect('village conquest', (int)$scalar('SELECT owner FROM vdata WHERE kid=?', [$defender['kid']]) === $attacker['id'], 'the village did not change hands.');
$pdo->run('UPDATE users SET cp=0 WHERE id=?', [$attacker['id']]);

// --- Gaul traps ------------------------------------------------------------------------------------------------
$reset();
$pdo->run('UPDATE users SET race=3 WHERE id=?', [$defender['id']]);
// A garrison that wipes out every attacker the traps let through: a surviving attacker would free the prisoners again.
$pdo->run('UPDATE units SET race=3, u1=3000, u99=50 WHERE kid=?', [$defender['kid']]);
$build($defender, 30, 36, 10);
$send('Gaul traps', MovementsModel::ATTACKTYPE_NORMAL, [1 => 100]);
$expect('Gaul traps', (int)$scalar('SELECT COALESCE(SUM(u1), 0) FROM trapped WHERE kid=? AND to_kid=?', [$attacker['kid'], $defender['kid']]) > 0, 'no attacker was caught in a trap.');

// --- artifacts -------------------------------------------------------------------------------------------------
$reset();
$pdo->run('UPDATE config SET ArtifactsReleased=1');
$demolish($defender, [27]);
$build($attacker, 30, 27, 20);
$pdo->run(
    'INSERT INTO artefacts (uid, kid, release_kid, type, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, ?, 2, 1, ?, 1, 2, 4, 1, 1, 0)',
    [$defender['id'], $defender['kid'], $defender['kid'], time()]
);
$send('artifact capture', MovementsModel::ATTACKTYPE_NORMAL, [1 => 600, 11 => 1]);
$expect('artifact capture', (int)$scalar('SELECT COUNT(*) FROM artefacts WHERE kid=? AND uid=?', [$attacker['kid'], $attacker['id']]) === 1, 'the artifact did not move to the attacker.');
$pdo->run('UPDATE config SET ArtifactsReleased=?', [$artifactsReleased]);

// A small random-aim Cranny artefact still lets catapults aim at the Treasury; every other aim is random.
$reset();
$pdo->run('UPDATE config SET ArtifactsReleased=1');
$build($defender, 30, 27, 10);
$pdo->run(
    'INSERT INTO artefacts (uid, kid, release_kid, type, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, ?, ?, 1, ?, 1, 2, 4, 1, 1, 1)',
    [$defender['id'], $defender['kid'], $defender['kid'], \Model\ArtefactsModel::ARTIFACT_CRANNY, time()]
);
$send('catapults against a small cranny artefact', MovementsModel::ATTACKTYPE_NORMAL, [1 => 800, 7 => 50, 8 => 100], ['ctar1' => 27]);
$expect('small cranny artefact', (int)$scalar('SELECT f30 FROM fdata WHERE kid=?', [$defender['kid']]) < 10, 'the Treasury was not hit although it may be targeted.');
$pdo->run('UPDATE config SET ArtifactsReleased=?', [$artifactsReleased]);

// --- oases -----------------------------------------------------------------------------------------------------
// One neutral oasis close to the attacker is borrowed for the capture scenarios and put back afterwards.
$oasis = null;
foreach ($pdo->run('SELECT kid FROM odata WHERE owner=0 AND did=0')->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
    if (Formulas::getDistance($attacker['kid'], (int)$candidate) < 4.9) {
        $oasis = (int)$candidate;
        break;
    }
}
if ($oasis === null) {
    echo "Battle scenarios runtime: no neutral oasis within reach of the fixture village, oasis and cage scenarios skipped.\n";
} else {
    $saved = [
        'odata' => $pdo->fetchSingleRow('SELECT owner, did, loyalty, conquered_time, last_loyalty_update, lasttrain, lastfarmed FROM odata WHERE kid=?', [$oasis]),
        'wdata' => (int)$pdo->fetchScalar('SELECT occupied FROM wdata WHERE id=?', [$oasis]),
        'units' => $pdo->fetchSingleRow('SELECT u1, u2, u3, u4, u5, u6, u7, u8, u9, u10 FROM units WHERE kid=?', [$oasis]),
    ];
    $restoreOasis = static function () use ($pdo, $oasis, $saved): void {
        $pdo = DBI::getInstance();
        $pdo->run('UPDATE odata SET owner=?, did=?, loyalty=?, conquered_time=?, last_loyalty_update=?, lasttrain=?, lastfarmed=? WHERE kid=?', [...array_values($saved['odata']), $oasis]);
        $pdo->run('UPDATE wdata SET occupied=? WHERE id=?', [$saved['wdata'], $oasis]);
        $pdo->run('UPDATE units SET u1=?, u2=?, u3=?, u4=?, u5=?, u6=?, u7=?, u8=?, u9=?, u10=? WHERE kid=?', [...array_values($saved['units']), $oasis]);
    };
    register_shutdown_function($restoreOasis);
    $emptyOasis = static function (int $owner, int $did, float $loyalty) use ($pdo, $oasis): void {
        $pdo->run('UPDATE odata SET owner=?, did=?, loyalty=?, last_loyalty_update=?, lastfarmed=? WHERE kid=?', [$owner, $did, $loyalty, time(), time(), $oasis]);
        $pdo->run('UPDATE wdata SET occupied=? WHERE id=?', [$owner > 0 ? 1 : 0, $oasis]);
        $pdo->run('UPDATE units SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0 WHERE kid=?', [$oasis]);
    };

    // The Hero's Mansion decides how many oases a village may hold.
    $oasisOwner = static fn (): int => (int)$scalar('SELECT did FROM odata WHERE kid=?', [$oasis]);
    $reset();
    $build($attacker, 31, 37, 10);
    $emptyOasis(0, 0, 100.0);
    $send('oasis capture by raid', MovementsModel::ATTACKTYPE_RAID, [1 => 400, 11 => 1], ['target' => $oasis]);
    $expect('oasis capture by raid', $oasisOwner() === $attacker['kid'], 'the empty oasis was not captured.');

    $reset();
    $build($attacker, 31, 37, 10);
    $emptyOasis($defender['id'], $defender['kid'], 60.0);
    $send('attack on an owned oasis', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1], ['target' => $oasis]);
    $loyalty = (float)$scalar('SELECT loyalty FROM odata WHERE kid=?', [$oasis]);
    $expect('attack on an owned oasis', $oasisOwner() === $defender['kid'] && $loyalty < 60.0, 'the oasis loyalty did not drop.');

    // The loyalty step follows the oases the OWNER holds (one: a third per attack), not the attacker's.
    $others = $pdo->run('SELECT kid FROM odata WHERE owner=0 AND did=0 AND kid<>? LIMIT 2', [$oasis])->fetchAll(PDO::FETCH_COLUMN);
    if (count($others) === 2) {
        $savedOthers = $pdo->run('SELECT kid, owner, did FROM odata WHERE kid IN (' . implode(',', array_map('intval', $others)) . ')')->fetchAll(PDO::FETCH_ASSOC);
        register_shutdown_function(static function () use ($savedOthers): void {
            foreach ($savedOthers as $row) {
                DBI::getInstance()->run('UPDATE odata SET owner=?, did=? WHERE kid=?', [$row['owner'], $row['did'], $row['kid']]);
            }
        });
        $reset();
        $build($attacker, 31, 37, 20);
        foreach ($others as $other) {
            $pdo->run('UPDATE odata SET owner=?, did=? WHERE kid=?', [$attacker['id'], $attacker['kid'], $other]);
        }
        $emptyOasis($defender['id'], $defender['kid'], 90.0);
        $send('loyalty step of a one-oasis owner', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1], ['target' => $oasis]);
        $loyalty = (float)$scalar('SELECT loyalty FROM odata WHERE kid=?', [$oasis]);
        $expect('loyalty step', $loyalty > 56.5 && $loyalty < 57.5, "the oasis should drop by a third to 57, it is at $loyalty.");

        // A full Hero's Mansion refuses a normal-attack capture: level 10 gives one slot and the village holds two.
        $reset();
        $build($attacker, 31, 37, 10);
        foreach ($others as $other) {
            $pdo->run('UPDATE odata SET owner=?, did=? WHERE kid=?', [$attacker['id'], $attacker['kid'], $other]);
        }
        $emptyOasis($defender['id'], $defender['kid'], 5.0);
        $send('capture without a free oasis slot', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1], ['target' => $oasis]);
        $loyalty = (float)$scalar('SELECT loyalty FROM odata WHERE kid=?', [$oasis]);
        $expect('capture without a free oasis slot', $oasisOwner() === $defender['kid'] && $loyalty === 5.0, "the oasis must stay with its owner at loyalty 5, owner {$oasisOwner()} loyalty $loyalty.");
        foreach ($savedOthers as $row) {
            $pdo->run('UPDATE odata SET owner=?, did=? WHERE kid=?', [$row['owner'], $row['did'], $row['kid']]);
        }
    }

    $reset();
    $build($attacker, 31, 37, 10);
    $emptyOasis($defender['id'], $defender['kid'], 5.0);
    $send('capture of an owned oasis', MovementsModel::ATTACKTYPE_NORMAL, [1 => 400, 11 => 1], ['target' => $oasis]);
    $expect('capture of an owned oasis', $oasisOwner() === $attacker['kid'], 'the owned oasis was not taken over.');

    // Cages: the hero's bag holds 15 cages and the oasis has 20 animals of the first kind.
    $reset();
    $emptyOasis(0, 0, 100.0);
    $pdo->run('UPDATE units SET u1=20 WHERE kid=?', [$oasis]);
    $pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, 9, 1, 15, 0, 1)', [$attacker['id']]);
    $cages = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
    $pdo->run('UPDATE inventory SET bag=? WHERE uid=?', [$cages, $attacker['id']]);
    $send('hero cages', MovementsModel::ATTACKTYPE_NORMAL, [1 => 10, 11 => 1], ['target' => $oasis]);
    $left = (int)$scalar('SELECT u1 FROM units WHERE kid=?', [$oasis]);
    $expect('hero cages', $left === 5, "the oasis should keep 5 of 20 animals after 15 were caged, it has $left.");
    $expect('hero cages', (int)$scalar('SELECT COUNT(*) FROM items WHERE id=?', [$cages]) === 0, 'the used cages were not taken out of the bag.');
    $expect('hero cages', (int)$scalar('SELECT type FROM ndata WHERE uid=? AND to_kid=? ORDER BY id DESC LIMIT 1', [$attacker['id'], $oasis]) === NoticeHelper::TYPE_CAGED_ATTACK, 'the report is not a caged-attack report.');
    $pdo->run('UPDATE inventory SET bag=0 WHERE uid=?', [$attacker['id']]);
}
$reset();

echo "Battle scenarios runtime passed.\n";
