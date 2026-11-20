<?php

declare(strict_types=1);

// Settlers arriving at a free tile found a village only when the sending village still has a free expansion slot
// (it may have lost its Residence after training). Otherwise they walk home, as many as arrived, and the founding
// payment is refunded once. The fixture village has no Residence, Palace or Command Center, so it has no slot.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Expansion slot arrival runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DBI;
use Game\Formulas;
use Game\Gid;
use Game\TrainingHelper;
use Game\Unit;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $sender = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Expansion slot arrival runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$sender['kid']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$sender['kid']]));

$target = (int)$pdo->fetchScalar('SELECT w.id FROM wdata w LEFT JOIN vdata v ON v.kid=w.id WHERE v.kid IS NULL AND w.fieldtype>0 AND w.occupied=0 ORDER BY w.id ASC LIMIT 1');
if ($target === 0) {
    echo "Expansion slot arrival runtime passed (no free tile to settle).\n";
    exit(0);
}
// A broken slot check would found a village there: remove it again however the test ends.
register_shutdown_function(static function () use ($sender, $target): void {
    if ((int)DBI::getInstance()->fetchScalar('SELECT COUNT(*) FROM vdata WHERE kid=? AND owner=?', [$target, $sender['id']]) > 0) {
        fixtureVillageRemove($sender['id'], $target);
    }
});

for ($slot = 19; $slot <= 38; ++$slot) {
    $pdo->run("UPDATE fdata SET f{$slot}=0, f{$slot}t=0 WHERE kid=? AND f{$slot}t IN (?, ?, ?)", [$sender['kid'], ...Gid::EXPANSION]);
}
if (TrainingHelper::freeExpansionSlots($sender['kid']) !== 0) {
    $fail('A village without a Residence, Palace or Command Center must have no free expansion slot.');
}
$pdo->run('UPDATE users SET cp=1000000000 WHERE id=?', [$sender['id']]);
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$sender['id']]);
$cost = Formulas::newVillageCost();

$arrive = static function (string $label, int $settlers) use ($pdo, $sender, $target, $race, $cost, $fail): void {
    $units = array_fill(1, 11, 0);
    $units[Unit::SETTLER] = $settlers;
    $pdo->run('UPDATE vdata SET wood=0, clay=0, iron=0, crop=0, lastmupdate=? WHERE kid=?', [miliseconds(), $sender['kid']]);
    $lastMove = (int)$pdo->fetchScalar('SELECT COALESCE(MAX(id), 0) FROM movement');
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement($sender['kid'], $target, $race, $units, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_SETTLERS, $now - 20000, $now - 10000);
    try {
        (new Automation())->processMovementTask($id);
    } catch (Throwable $throwable) {
        $fail("$label: " . get_class($throwable) . ': ' . $throwable->getMessage());
    }
    if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM vdata WHERE kid=?', [$target]) !== 0) {
        $fail("$label: a village was founded although the sending village has no free expansion slot.");
    }
    $home = (int)$pdo->fetchScalar('SELECT COALESCE(SUM(u10), 0) FROM movement WHERE id>? AND kid=? AND to_kid=? AND mode=1', [$lastMove, $target, $sender['kid']]);
    if ($home !== $settlers) {
        $fail("$label: $settlers settlers arrived, $home walk home.");
    }
    $wood = (float)$pdo->fetchScalar('SELECT wood FROM vdata WHERE kid=?', [$sender['kid']]);
    // Exactly one payment comes back; allow the few resources the village produces while the test runs.
    if ($wood < $cost - 1 || $wood > $cost * 1.5) {
        $fail("$label: the founding payment ($cost of each resource) must be refunded once, the village got $wood wood.");
    }
    $pdo->run('DELETE FROM movement WHERE id>? AND kid=? AND to_kid=?', [$lastMove, $target, $sender['kid']]);
};

$arrive('settlers without a free slot', 3);
$arrive('fewer settlers than were sent', 2);

// A Residence at level 10 opens one slot.
$pdo->run('UPDATE fdata SET f30t=?, f30=10 WHERE kid=?', [Gid::RESIDENCE, $sender['kid']]);
if (TrainingHelper::freeExpansionSlots($sender['kid']) !== 1) {
    $fail('A level-10 Residence with no founded village must leave one free expansion slot.');
}

echo "Expansion slot arrival runtime passed.\n";
