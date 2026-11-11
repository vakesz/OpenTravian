<?php

declare(strict_types=1);

// Administrators of the owner's alliance or of a confederate alliance cannot lower a village's loyalty unless the
// village holds an artefact or its owner allowed it (the report then says nothing); a NAP between the alliances
// blocks it with the reference's report line; an unrelated alliance, no alliance and the own village are not
// protected. Two throwaway fixture players act as attacker and defender with each movement due a day from now, so
// the live engine never races the test for it.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Conquer loyalty protection runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
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
    echo "Conquer loyalty protection runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
foreach ([$attacker, $defender] as $fixture) {
    $pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
    register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
}
$db = DB::getInstance();
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$attacker['id']]);
$artifactsReleased = (int)$pdo->fetchScalar('SELECT ArtifactsReleased FROM config LIMIT 1');
$aidA = 900000000 + getmypid();
$aidB = $aidA + 1;
register_shutdown_function(static function () use ($aidA, $aidB, $artifactsReleased): void {
    $pdo = DBI::getInstance();
    $pdo->run('DELETE FROM diplomacy WHERE aid1 IN (?, ?) OR aid2 IN (?, ?)', [$aidA, $aidB, $aidA, $aidB]);
    $pdo->run('UPDATE config SET ArtifactsReleased=?', [$artifactsReleased]);
});
$buildings = $pdo->fetchSingleRow('SELECT * FROM fdata WHERE kid=?', [$defender['kid']]);
register_shutdown_function(static function () use ($buildings, $defender): void {
    $sets = [];
    $values = [];
    foreach ($buildings as $column => $value) {
        if ($column !== 'kid') {
            $sets[] = "`$column`=?";
            $values[] = $value;
        }
    }
    DBI::getInstance()->run('UPDATE fdata SET ' . implode(',', $sets) . ' WHERE kid=?', [...$values, $defender['kid']]);
});
for ($slot = 19; $slot <= 40; ++$slot) {
    $pdo->run("UPDATE fdata SET f{$slot}=0, f{$slot}t=0 WHERE kid=? AND f{$slot}t IN (25, 26, 44)", [$defender['kid']]);
}

/**
 * Sends 600 troops and three chiefs at the defender village once; returns [owner, loyalty, report captureResult].
 *
 * @return array{0: int, 1: float, 2: string}
 */
$conquer = static function (string $label) use ($pdo, $db, $attacker, $defender, $race, $fail): array {
    $pdo->run('DELETE FROM ndata WHERE uid IN (?, ?)', [$attacker['id'], $defender['id']]);
    $pdo->run('UPDATE units SET u1=40, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0, u11=0, u99=0 WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE vdata SET loyalty=10, owner=?, capital=0 WHERE kid=?', [$defender['id'], $defender['kid']]);
    $pdo->run('DELETE FROM enforcement WHERE to_kid=?', [$defender['kid']]);
    $pdo->run('UPDATE users SET cp=1000000 WHERE id=?', [$attacker['id']]);
    $units = array_fill(1, 11, 0);
    $units[1] = 600;
    $units[9] = 3;
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement($attacker['kid'], $defender['kid'], $race, $units, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_NORMAL, $now - 20000, $now - 10000);
    try {
        (new Automation())->processMovementTask($id);
    } catch (Throwable $throwable) {
        $fail("$label: " . get_class($throwable) . ': ' . $throwable->getMessage());
    }
    $owner = (int)$pdo->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$defender['kid']]);
    $loyalty = (float)$pdo->fetchScalar('SELECT loyalty FROM vdata WHERE kid=?', [$defender['kid']]);
    $data = (string)$pdo->fetchScalar('SELECT data FROM ndata WHERE uid=? AND to_kid=? ORDER BY id DESC LIMIT 1', [$attacker['id'], $defender['kid']]);
    $reportedLoyalty = $data !== '' && $db->fetchScalar('SELECT COUNT(*) FROM ndata WHERE uid=? AND to_kid=?', [$attacker['id'], $defender['kid']]) > 0;
    if (!$reportedLoyalty) {
        $fail("$label: the attack left no report.");
    }

    return [$owner, $loyalty, $data];
};
$setAlliances = static function (?int $attackerAid, ?int $defenderAid) use ($pdo, $attacker, $defender): void {
    $pdo->run('UPDATE users SET aid=? WHERE id=?', [$attackerAid ?? 0, $attacker['id']]);
    $pdo->run('UPDATE users SET aid=? WHERE id=?', [$defenderAid ?? 0, $defender['id']]);
};
$diplomacy = static function (int $type) use ($pdo, $aidA, $aidB): void {
    $pdo->run('DELETE FROM diplomacy WHERE aid1 IN (?, ?) OR aid2 IN (?, ?)', [$aidA, $aidB, $aidA, $aidB]);
    $pdo->run('INSERT INTO diplomacy (aid1, aid2, type, accepted) VALUES (?, ?, ?, 1)', [$aidA, $aidB, $type]);
};
$expect = static function (string $label, bool $condition, string $what) use ($fail): void {
    if (!$condition) {
        $fail("$label: $what");
    }
};
/** The battle info of a stored report. */
$captured = static fn (string $data): array => (array)((unserialize($data, ['allowed_classes' => false])['info'] ?? []));
$setPermission = static fn (int $value) => $pdo->run('UPDATE vdata SET allowLoyaltyDecrease=? WHERE kid=?', [$value, $defender['kid']]);
$setArtefact = static function (bool $present) use ($pdo, $defender): void {
    $pdo->run('DELETE FROM artefacts WHERE kid=?', [$defender['kid']]);
    $pdo->run('UPDATE config SET ArtifactsReleased=?', [$present ? 1 : 0]);
};

// The village changes hands or its loyalty drops when the attack is allowed to lower it.
$setAlliances(null, null);
$setPermission(0);
$result = $conquer('no alliances');
$expect('no alliances', $result[0] === $attacker['id'], 'a village of a player without an alliance must be conquerable.');

$setAlliances($aidA, $aidA);
$result = $conquer('same alliance');
$expect('same alliance', $result[0] === $defender['id'] && abs($result[1] - 10.0) < 0.001, 'allies must not lower the loyalty.');
$expect('same alliance', !array_key_exists('captureResult', $captured($result[2])), 'the report must say nothing about the loyalty.');

$setPermission(1);
$result = $conquer('same alliance with permission');
$expect('same alliance with permission', $result[0] === $attacker['id'], 'the owner allowed the loyalty drop.');
$setPermission(0);

$setArtefact(true);
$pdo->run('INSERT INTO artefacts (uid, kid, release_kid, type, size, conquered, num, effecttype, effect, aoe, status, active) VALUES (?, ?, ?, 2, 1, ?, 1, 2, 4, 1, 1, 0)', [$defender['id'], $defender['kid'], $defender['kid'], time()]);
$result = $conquer('same alliance with an artefact');
$expect('same alliance with an artefact', $result[0] === $attacker['id'], 'an artefact in the village lifts the protection.');
$setArtefact(false);

$setAlliances($aidA, $aidB);
$diplomacy(1);
$result = $conquer('confederacy');
$expect('confederacy', $result[0] === $defender['id'] && abs($result[1] - 10.0) < 0.001, 'confederates must not lower the loyalty.');
$setPermission(1);
$result = $conquer('confederacy with permission');
$expect('confederacy with permission', $result[0] === $attacker['id'], 'the owner allowed the loyalty drop.');
$setPermission(0);

$diplomacy(2);
$result = $conquer('NAP');
$expect('NAP', $result[0] === $defender['id'] && abs($result[1] - 10.0) < 0.001, 'a NAP must block the loyalty drop.');
$expect('NAP', ($captured($result[2])['captureResult'] ?? null) === -5, 'the report must carry the NAP result.');
$setPermission(1);
$result = $conquer('NAP with permission');
$expect('NAP with permission', $result[0] === $defender['id'], 'the owner permission must not lift a NAP.');
$setPermission(0);

$diplomacy(3);
$result = $conquer('war');
$expect('war', $result[0] === $attacker['id'], 'alliances at war are not protected.');
$pdo->run('DELETE FROM diplomacy WHERE aid1 IN (?, ?) OR aid2 IN (?, ?)', [$aidA, $aidB, $aidA, $aidB]);
$result = $conquer('unrelated alliances');
$expect('unrelated alliances', $result[0] === $attacker['id'], 'unrelated alliances are not protected.');

$setAlliances(null, null);
$pdo->run('UPDATE users SET cp=0 WHERE id=?', [$attacker['id']]);
// A captured village forgets the previous owner's permission.
$setPermission(1);
$conquer('permission reset');
$expect('permission reset', (int)$pdo->fetchScalar('SELECT allowLoyaltyDecrease FROM vdata WHERE kid=?', [$defender['kid']]) === 0, 'a conquered village must not keep the previous owner permission.');

echo "Conquer loyalty protection runtime passed.\n";
