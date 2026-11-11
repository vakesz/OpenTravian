<?php

declare(strict_types=1);

// Reinforcements, evasions and returning troops reach their processors with every column of the movement row as a
// string from the database. Each arrival must complete (a failing task is quarantined and its troops never come
// home). Two throwaway fixture villages send troops to each other; the movements are claimed a day ahead and
// processed by id so the live engine cannot race the test.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Movement arrivals runtime skipped outside the php container.\n";
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
    $sender = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxa' . getmypid(), $sender['id']]);
    $receiver = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Movement arrivals runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
foreach ([$sender, $receiver] as $fixture) {
    $pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
    register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
}
$db = DB::getInstance();
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$sender['id']]);
$pdo->run('UPDATE units SET u1=50 WHERE kid=?', [$sender['kid']]);

$arrive = static function (string $label, int $from, int $to, int $attackType, int $mode, array $units) use ($race, $db, $fail): void {
    $all = array_fill(1, 11, 0);
    foreach ($units as $slot => $count) {
        $all[$slot] = $count;
    }
    $now = (int)miliseconds() + 86400000;
    $id = (new MovementsModel())->addMovement($from, $to, $race, $all, 0, 0, 0, 0, $mode, $attackType, $now - 20000, $now - 10000);
    if ($id <= 0) {
        $fail("$label: the movement could not be queued.");
    }
    try {
        (new Automation())->processMovementTask($id);
    } catch (Throwable $throwable) {
        $fail("$label: " . get_class($throwable) . ': ' . $throwable->getMessage() . ' (' . basename($throwable->getFile()) . ':' . $throwable->getLine() . ')');
    }
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id")) {
        $fail("$label: the arriving movement was not consumed.");
    }
};

$arrive('reinforcement', $sender['kid'], $receiver['kid'], MovementsModel::ATTACKTYPE_REINFORCEMENT, 0, [1 => 10]);
if (!(int)$db->fetchScalar('SELECT COUNT(*) FROM enforcement WHERE to_kid=' . $receiver['kid'])) {
    $fail('reinforcement: the troops did not stay in the receiving village.');
}
$before = (int)$db->fetchScalar('SELECT u1 FROM units WHERE kid=' . $sender['kid']);
$arrive('returning troops', $receiver['kid'], $sender['kid'], MovementsModel::ATTACKTYPE_NORMAL, 1, [1 => 5]);
if ((int)$db->fetchScalar('SELECT u1 FROM units WHERE kid=' . $sender['kid']) !== $before + 5) {
    $fail('returning troops: the troops did not arrive home.');
}
$arrive('evasion', $sender['kid'], 0, MovementsModel::ATTACKTYPE_EVASION, 0, [1 => 5]);
echo "Movement arrivals runtime passed.\n";
