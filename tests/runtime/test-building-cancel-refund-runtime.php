<?php

declare(strict_types=1);

// Cancelling a queued construction gives back the cost of the previous level (the full cost for level 1),
// whatever the order's progress: right after queueing, half way and just before it completes. The dialog
// share is floor(100 * previous wood / new wood). The expected amounts and shares are the ones the
// reference's abort dialog showed on 2026-11-07 (docs/world-speed.md, "Cancelling a construction").

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Building cancel refund runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Building cancel refund runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

// [field, gid, level before, expected share, expected refund (reference), label]
$cases = [
    [9, 4, 0, 100, null, 'field to level 1'],
    [8, 4, 3, 60, [195, 250, 195, 55], 'cropland to level 4'],
    [21, 11, 7, 77, [350, 440, 310, 90], 'granary to level 8'],
    [22, 17, 1, 80, [80, 70, 120, 70], 'marketplace to level 2'],
];
$progressPoints = ['just queued' => 0.0, 'half way' => 0.5, 'just before completion' => 0.99];

if (($argv[1] ?? '') !== 'child') {
    try {
        $player = fixtureVillageCreate();
    } catch (Throwable $exception) {
        echo "Building cancel refund runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $kid = (int)$player['kid'];
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $player['id']]);
    foreach (array_keys($cases) as $case) {
        foreach (array_keys($progressPoints) as $progress) {
            passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $case . ' ' . escapeshellarg($progress) . ' ' . (int)$player['id'], $status);
            if ($status !== 0) {
                fixtureVillageRemove((int)$player['id'], $kid);
                exit(1);
            }
        }
    }
    fixtureVillageRemove((int)$player['id'], $kid);
    echo "Building cancel refund runtime passed (level 1 in full, then the previous level's cost, at three progress points each).\n";
    exit(0);
}

// ---- child: one process per case and progress point, because Village and Session are singletons ----
\Core\Config::getInstance()->dynamic->serverFinished = 0;   // the refund is the subject, not the state of the round
[$field, $gid, $before, $share, $expected, $label] = $cases[(int)$argv[2]];
$progress = (string)$argv[3];
$fraction = $progressPoints[$progress];
$uid = (int)$argv[4];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$resources = static fn (): array => array_map('intval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));
$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM demolition WHERE kid=?', [$kid]);
$pdo->run("UPDATE fdata SET f{$field}=?, f{$field}t=? WHERE kid=?", [$before, $gid, $kid]);
$pdo->run(
    'UPDATE vdata SET wood=1000000, clay=1000000, iron=1000000, crop=1000000, woodp=0, clayp=0, ironp=0, cropp=pop+10,
     upkeep=0, maxstore=2000000, maxcrop=2000000, lastmupdate=? WHERE kid=?',
    [miliseconds(), $kid]
);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = $pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
Session::getInstance();
$village = Village::getInstance();

$level = $before + 1;
$label .= ", $progress";
$refund = Village::cancelRefund($gid, $level);
$charge = array_map('intval', array_slice(array_values(Formulas::buildingUpgradeCosts($gid, $level)), 0, 4));
if ($refund['percent'] !== $share) {
    $fail("$label: the dialog share is {$refund['percent']}, expected $share.");
}
$expectedAmounts = $expected ?? $charge;
if ($refund['amounts'] !== $expectedAmounts) {
    $fail("$label: refund " . json_encode($refund['amounts']) . ' differs from ' . json_encode($expectedAmounts) . '.');
}
foreach ($refund['amounts'] as $i => $amount) {
    if ($amount > $charge[$i]) {
        $fail("$label: the refund exceeds the charge.");
    }
}

$start = $resources();
$village->upgradeBuilding($field, false);
$afterQueue = $resources();
$queued = array_map(static fn (int $a, int $b): int => $a - $b, $start, $afterQueue);
if ($queued !== $charge) {
    $fail("$label: the queue charge " . json_encode($queued) . ' is not the level cost ' . json_encode($charge) . '.');
}
$id = (int)$pdo->fetchScalar('SELECT id FROM building_upgrade WHERE kid=? AND building_field=? AND isMaster=0 ORDER BY id DESC LIMIT 1', [$kid, $field]);
$row = $village->onLoadBuildings['normal'][$id] ?? null;
if ($row === null) {
    $fail("$label: the queued order is not in the village.");
}
// Move the order's progress: it started `fraction` of its duration ago.
$duration = max(1, (int)$row['commence'] - time());
$shifted = (int)$row['commence'] - (int)round($duration * $fraction);
$pdo->run('UPDATE building_upgrade SET commence=? WHERE id=?', [$shifted, $id]);
$village->onLoadBuildings['normal'][$id]['commence'] = $shifted;

$village->removeBuilding($id);
$given = array_map(static fn (int $a, int $b): int => $a - $b, $resources(), $afterQueue);
if ($given !== $refund['amounts']) {
    $fail("$label: cancelling gave back " . json_encode($given) . ', expected ' . json_encode($refund['amounts']) . '.');
}
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM building_upgrade WHERE id=?', [$id]) !== 0) {
    $fail("$label: the order is still queued.");
}
