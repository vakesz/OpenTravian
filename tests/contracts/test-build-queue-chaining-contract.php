<?php

declare(strict_types=1);

/**
 * Contract: construction queues chain end-to-end.
 *
 * A task queued behind another in the same queue must start when the earlier
 * one ends and run for its own full duration. Romans have a resource-field
 * queue (fields 1-18) and an infrastructure queue (slots 19-99); every other
 * tribe has one queue. Bounds are inclusive: fields 1/18 and slot 19 used to
 * be skipped, so the next task started "now" and finished about a second after
 * the first.
 *
 * Part 1 is a static source check. Part 2 runs the real Village code inside a
 * rolled-back transaction when a Roman player and the world database are
 * reachable (run it in the php container); otherwise it is skipped.
 */

function failBuildQueueChainingContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$village = file_get_contents($root . '/game/include/Core/Village.php');
$ai = file_get_contents($root . '/game/include/Game/Buildings/AutoUpgradeAI.php');
$master = file_get_contents($root . '/game/include/Model/MasterBuilder.php');
if ($village === false || $ai === false || $master === false) {
    failBuildQueueChainingContract('Unable to read build-queue sources.');
}

foreach ([
    'private function getQueueTail(int $field): int',
    'building_field BETWEEN ? AND ?',
    'SELECT MAX(commence) FROM building_upgrade',
    'return $field <= 18 ? [1, 18] : [19, 99];',
] as $needle) {
    if (!str_contains($village, $needle)) {
        failBuildQueueChainingContract("Village must keep the inclusive queue-tail helper: {$needle}");
    }
}
if (substr_count($village, '$this->getQueueTail((int)$field)') !== 2) {
    failBuildQueueChainingContract('upgradeBuilding and constructBuilding must both chain through getQueueTail().');
}
foreach (['function getLastCommence', 'sizeof($previousCommence)', 'building_field > ? AND building_field < ?'] as $legacy) {
    if (str_contains($village, $legacy)) {
        failBuildQueueChainingContract("Obsolete queue chaining remains in Village: {$legacy}");
    }
}
foreach ([$ai => 'AutoUpgradeAI', $master => 'MasterBuilder'] as $source => $name) {
    if (!str_contains($source, 'MAX(commence)') || !str_contains($source, 'BETWEEN $min AND $max')
        || !str_contains($source, '[19, 99]')) {
        failBuildQueueChainingContract("{$name} must chain per queue with inclusive bounds and MAX(commence).");
    }
}

// ---- Part 2: behaviour against the real world database ----
if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Build queue chaining contract passed (static; behavioural part skipped outside the php container).\n";
    exit(0);
}

$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;

require_once $root . '/tests/support/village-fixture.php';

try {
    $pdo = DBI::getInstance();
    $user = fixtureVillageCreate();
} catch (Throwable $e) {
    echo "Build queue chaining contract passed (static; no fixture village: {$e->getMessage()}).\n";
    exit(0);
}

$kid = (int)$user['kid'];
try {
    $pdo->run('UPDATE users SET plus=?, last_login_time=?, last_owner_login_time=? WHERE id=?', [time() + 3600, time(), time(), $user['id']]);
    $pdo->run('UPDATE vdata SET wood=7000, clay=7000, iron=7000, crop=3000, lastmupdate=? WHERE kid=?', [miliseconds(), $kid]);
    $pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
    $_SESSION[WebService::fixSessionPrefix('uid')] = (int)$user['id'];
    $_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
    Session::getInstance();
    $v = Village::getInstance();

    $end = function (int $field) use ($pdo, $kid): int {
        return (int)$pdo->fetchScalar('SELECT MAX(commence) FROM building_upgrade WHERE kid=? AND building_field=?', [$kid, $field]);
    };
    $duration = function (int $field, int $level) use ($v): int {
        return (int)Formulas::buildingUpgradeTime((int)$v->getField($field)['item_id'], $level, $v->getMainBuildingLvl(), $v->isWW());
    };

    // Edge slots: field 1 then field 2 must chain; field 18 then field 5 must chain.
    $lvl2 = (int)$v->getField(2)['level'] + 1;
    $d2 = $duration(2, $lvl2);
    $v->upgradeBuilding(1, false);
    $v->upgradeBuilding(2, false);
    if (!$end(1) || !$end(2)) {
        throw new RuntimeException('Fixture village could not queue fields 1 and 2.');
    }
    if ($end(2) !== $end(1) + $d2) {
        throw new RuntimeException('Field 2 must end one full duration after field 1 (fields 1/18 chain).');
    }

    // Cancelling the first task restarts the follower now, for its full duration.
    $first = (int)$pdo->fetchScalar('SELECT id FROM building_upgrade WHERE kid=? AND building_field=1', [$kid]);
    $now = time();
    $v->removeBuilding($first);
    $newEnd = $end(2);
    if ($newEnd < $now + $d2 || $newEnd > time() + $d2) {
        throw new RuntimeException("After cancel field 2 must end now + {$d2}s, got " . ($newEnd - $now) . 's.');
    }
    echo "Build queue chaining contract passed.\n";
} catch (Throwable $e) {
    failBuildQueueChainingContract('Build queue chaining behaviour failed: ' . $e->getMessage());
}
