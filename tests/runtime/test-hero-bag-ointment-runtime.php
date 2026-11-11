<?php

declare(strict_types=1);

// Ointments equipped in the hero's bag slot: they stack with what is already equipped, heal a surviving hero right
// after a battle (the attacking hero, a defending hero and a hero reinforcing the village), and are used up doing
// so; ointments left in the inventory are never used. Throwaway fixture players act as attacker, defender and
// reinforcing player; their rows are removed when the test ends.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero bag ointment runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Game\Formulas;
use Game\Hero\BagOintment;
use Model\HeroInventoryModel;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$expect = static function (bool $condition, string $what) use ($fail): void {
    if (!$condition) {
        $fail($what);
    }
};
$pdo = DBI::getInstance();
try {
    $attacker = fixtureVillageCreate();
    $defender = fixtureVillageCreate();
    $reinforcer = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Hero bag ointment runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
foreach ([$attacker, $defender, $reinforcer] as $fixture) {
    $pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
    register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
}
// Whatever the cloned source village has built or researched must not decide these battles: no wall, no smithy upgrades.
foreach ([$attacker, $defender, $reinforcer] as $fixture) {
    $pdo->run('UPDATE fdata SET f40=0, f40t=0 WHERE kid=?', [$fixture['kid']]);
    $pdo->run('UPDATE smithy SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0 WHERE kid=?', [$fixture['kid']]);
}
$db = DB::getInstance();
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$attacker['id']]);

/** A stack of ointments (item 106) in the inventory; returns its id. */
$ointments = static function (int $uid, int $num, int $place, int $equipped = 0) use ($pdo): int {
    $pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, 11, 106, ?, ?, ?)', [$uid, $num, $place, $equipped]);
    $id = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
    if ($equipped) {
        $pdo->run('UPDATE inventory SET bag=? WHERE uid=?', [$id, $uid]);
    }

    return $id;
};
$clearItems = static function (int $uid) use ($pdo): void {
    $pdo->run('DELETE FROM items WHERE uid=?', [$uid]);
    $pdo->run('UPDATE inventory SET bag=0 WHERE uid=?', [$uid]);
};
$health = static fn (int $uid): float => (float)$pdo->fetchScalar('SELECT health FROM hero WHERE uid=?', [$uid]);
$bagNum = static fn (int $uid): int => (int)$pdo->fetchScalar('SELECT COALESCE(i.num, 0) FROM inventory v LEFT JOIN items i ON i.id=v.bag AND i.proc=1 WHERE v.uid=?', [$uid]);

// --- equipping and stacking --------------------------------------------------------------------------------------
$model = new HeroInventoryModel();
$clearItems($attacker['id']);
$stack = $ointments($attacker['id'], 20, 1);
$model->apply($attacker['id'], $attacker['kid'], $stack, 'bag', 8, false);
$expect($bagNum($attacker['id']) === 8, 'Equipping 8 of 20 ointments must put 8 in the bag slot.');
$expect((int)$pdo->fetchScalar('SELECT num FROM items WHERE id=?', [$stack]) === 12, 'The rest must stay in the inventory.');
$model->apply($attacker['id'], $attacker['kid'], $stack, 'bag', 5, false);
$expect($bagNum($attacker['id']) === 13, 'More ointments must be added to the equipped stack.');
$expect((int)$pdo->fetchScalar('SELECT num FROM items WHERE id=?', [$stack]) === 7, 'The inventory stack must shrink by what was added.');
$expect((int)$pdo->fetchScalar("SELECT COUNT(*) FROM items WHERE uid=? AND btype=11 AND proc=1", [$attacker['id']]) === 1, 'There must be a single equipped stack.');
$pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, 7, 112, 4, 2, 0)', [$attacker['id']]);
$bandages = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
$model->apply($attacker['id'], $attacker['kid'], $bandages, 'bag', 4, false);
$expect($bagNum($attacker['id']) === 4 && (int)$pdo->fetchScalar('SELECT SUM(num) FROM items WHERE uid=? AND btype=11 AND proc=0', [$attacker['id']]) === 20,
    'A different kind in the bag must swap the ointments back to the inventory.');
$bandageStack = (int)$pdo->fetchScalar('SELECT bag FROM inventory WHERE uid=?', [$attacker['id']]);
$model->apply($attacker['id'], $attacker['kid'], $bandageStack, 'inventory_3', 4, false);
$pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, 7, 112, 3, 4, 0)', [$attacker['id']]);
$more = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
$pdo->run('UPDATE items SET proc=1 WHERE id=?', [$bandageStack]);
$pdo->run('UPDATE inventory SET bag=? WHERE uid=?', [$bandageStack, $attacker['id']]);
$pdo->run('UPDATE items SET proc=1 WHERE id=?', [$bandageStack]);
$model->apply($attacker['id'], $attacker['kid'], $more, 'bag', 3, false);
$expect($bagNum($attacker['id']) === 7, 'Bandages add to the equipped bandages like ointments do.');

// --- the heal itself ---------------------------------------------------------------------------------------------
$clearItems($attacker['id']);
$expect(BagOintment::healAfterBattle($attacker['id'], 12) === 0, 'Without equipped ointments nothing is used.');
$ointments($attacker['id'], 30, 1, 0);
$pdo->run('UPDATE hero SET health=70 WHERE uid=?', [$attacker['id']]);
$expect(BagOintment::healAfterBattle($attacker['id'], 12.4) === 0 && $health($attacker['id']) === 70.0, 'Ointments in the inventory are never used automatically.');
$clearItems($attacker['id']);
$ointments($attacker['id'], 20, 2, 1);
$expect(BagOintment::healAfterBattle($attacker['id'], 12.4) === 13, 'The battle took 12.4 points: 13 ointments.');
$expect($health($attacker['id']) === 83.0 && $bagNum($attacker['id']) === 7, 'The hero gets 13 points back and the stack keeps 7.');
$expect(BagOintment::healAfterBattle($attacker['id'], 50) === 7, 'A stack that is too small heals what it can.');
$expect($health($attacker['id']) === 90.0 && $bagNum($attacker['id']) === 0
    && (int)$pdo->fetchScalar('SELECT bag FROM inventory WHERE uid=?', [$attacker['id']]) === 0, 'A used-up stack leaves the bag slot empty.');
$ointments($attacker['id'], 20, 2, 1);
$pdo->run('UPDATE hero SET health=95 WHERE uid=?', [$attacker['id']]);
$expect(BagOintment::healAfterBattle($attacker['id'], 30) === 5 && $health($attacker['id']) === 100.0, 'The hero never goes above 100.');
$pdo->run('UPDATE hero SET health=0 WHERE uid=?', [$attacker['id']]);
$expect(BagOintment::healAfterBattle($attacker['id'], 10) === 0 && $health($attacker['id']) === 0.0, 'A dead hero is not healed.');

// --- battles ---------------------------------------------------------------------------------------------------
$send = static function (string $label, array $units) use ($attacker, $defender, $race, $db, $fail): void {
    $all = array_fill(1, 11, 0);
    foreach ($units as $slot => $count) {
        $all[$slot] = $count;
    }
    // Due a day from now: the live engine only takes due movements, so it never races this test for it.
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement($attacker['kid'], $defender['kid'], $race, $all, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_NORMAL, $now - 20000, $now - 10000);
    try {
        (new Automation())->processMovementTask($id);
    } catch (Throwable $throwable) {
        $fail("$label: " . get_class($throwable) . ': ' . $throwable->getMessage());
    }
};
$reset = static function () use ($pdo, $attacker, $defender, $reinforcer, $clearItems, $race): void {
    foreach ([$attacker, $defender, $reinforcer] as $fixture) {
        $clearItems($fixture['id']);
        // A hero set to hide leaves the village before an attack arrives; these stand and fight.
        // A level-up heals the hero to full, which would hide the battle damage this test measures.
        // Level 50's next level is further away than these battles can award.
        $pdo->run('UPDATE hero SET health=100, hide=0, exp=?, lastupdate=? WHERE uid=?', [(int)Formulas::heroExperience(50), time(), $fixture['id']]);
    }
    $pdo->run('UPDATE units SET u1=200, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0, u11=0, u99=0 WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$attacker['kid']]);
    $pdo->run('UPDATE vdata SET loyalty=100 WHERE kid=?', [$defender['kid']]);
    $pdo->run('DELETE FROM enforcement WHERE to_kid=?', [$defender['kid']]);
    $pdo->run('DELETE FROM ndata WHERE uid IN (?, ?, ?)', [$attacker['id'], $defender['id'], $reinforcer['id']]);
};
// The fixture heroes fall or heal in these battles: put their rows back afterwards.
$heroRows = [];
foreach ([$attacker, $defender, $reinforcer] as $fixture) {
    $heroRows[$fixture['id']] = $pdo->fetchSingleRow('SELECT * FROM hero WHERE uid=?', [$fixture['id']]);
}
register_shutdown_function(static function () use ($heroRows): void {
    $pdo = DBI::getInstance();
    foreach ($heroRows as $uid => $row) {
        if (!is_array($row)) {
            continue;
        }
        $sets = [];
        $values = [];
        foreach ($row as $column => $value) {
            if ($column !== 'uid') {
                $sets[] = "`$column`=?";
                $values[] = $value;
            }
        }
        $pdo->run('UPDATE hero SET ' . implode(',', $sets) . ' WHERE uid=?', [...$values, $uid]);
    }
});

// The attacking hero: the same attack costs it health without ointments and nothing with enough of them.
$reset();
$send('attacking hero without ointments', [1 => 400, 11 => 1]);
$lost = 100 - $health($attacker['id']);
$expect($lost > 0 && $lost < 90, "The attacking hero must be hurt but alive without ointments (lost $lost).");
$reset();
$ointments($attacker['id'], 60, 1, 1);
$send('attacking hero with ointments', [1 => 400, 11 => 1]);
$used = 60 - $bagNum($attacker['id']);
$expect($health($attacker['id']) === 100.0 && $used === (int)ceil($lost), "The attacking hero must be healed by the ointments (used $used, expected " . (int)ceil($lost) . ').');
$reset();
$ointments($attacker['id'], 60, 1, 0);
$send('attacking hero, ointments left in the inventory', [1 => 400, 11 => 1]);
$expect(abs((100 - $health($attacker['id'])) - $lost) < 0.0001 && (int)$pdo->fetchScalar('SELECT num FROM items WHERE uid=? AND btype=11', [$attacker['id']]) === 60, 'Inventory ointments must stay untouched.');

// A defending hero in its own village and a hero reinforcing it, each healed exactly once.
foreach (['defending' => $defender, 'reinforcing' => $reinforcer] as $role => $owner) {
    $setup = static function () use ($role, $owner, $pdo, $defender, $reinforcer, $race, $reset): void {
        $reset();
        if ($role === 'defending') {
            $pdo->run('UPDATE units SET u11=1 WHERE kid=?', [$defender['kid']]);
        } else {
            $pdo->run('INSERT INTO enforcement (uid, kid, to_kid, race, u1, u11) VALUES (?, ?, ?, ?, 25, 1)', [$reinforcer['id'], $reinforcer['kid'], $defender['kid'], $race]);
        }
    };
    $setup();
    $send("$role hero without ointments", [1 => 40]);
    $lost = 100 - $health($owner['id']);
    $expect($lost > 0 && $lost < 90, "The $role hero must be hurt but alive without ointments (lost $lost).");
    $setup();
    $ointments($owner['id'], 60, 1, 1);
    $send("$role hero with ointments", [1 => 40]);
    $used = 60 - $bagNum($owner['id']);
    $expect($health($owner['id']) === 100.0 && $used === (int)ceil($lost), "The $role hero must be healed exactly once (used $used, expected " . (int)ceil($lost) . ').');
}
$reset();

echo "Hero bag ointment runtime passed.\n";
