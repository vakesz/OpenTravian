<?php

declare(strict_types=1);

// A faster speed has a shorter round: a world older than the new round would end it on the engine's next cycle and delete
// every queue (the local world did when it went from 10x to 100x). tools/set-world-speed.php refuses such a change unless
// --reset-start moves the round start to now. Checks the refusal rule at the official and custom speeds, the reset on the
// real config and server rows inside rolled-back transactions, and the tool's dry run on the real world (nothing is written).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "World speed round runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;
use Core\Database\GlobalDB;
use Game\WorldSpeedRound;

$fail = static function (string $message): never {
    fwrite(STDERR, 'World speed round runtime failed: ' . $message . "\n");
    exit(1);
};
$now = 1_800_000_000;
$day = 86400;

// The rule: refuse at or beyond the end of the round, allow below it.
foreach ([
    // speed, age in days, refused?
    [10, 30.0, false], [10, 36.0, true],            // official x10: the Natar Wonder finishes after 35.5 days
    [2, 100.0, false], [2, 160.0, true],            // official x2: 160 days
    [100, 3.4, false], [100, 3.5, true], [100, 4.97, true],   // 350/100 days
    [1000, 0.3, false], [1000, 0.35, true],
    [1000000, 0.0, false], [1000000, 0.1, true],
] as [$speed, $age, $refused]) {
    $reason = WorldSpeedRound::refusal((int)($now - $age * $day), $now, $speed);
    if (($reason !== null) !== $refused) {
        $fail("a world of {$age} days at {$speed}x: " . ($reason ?? 'allowed') . ' (expected ' . ($refused ? 'refused' : 'allowed') . ').');
    }
    if ($refused && !str_contains((string)$reason, '--reset-start')) {
        $fail('the refusal must point at --reset-start.');
    }
}

// The reset moves the round start in the world's config row and the account database's server row.
$world = DB::getInstance();
$account = GlobalDB::getInstance();
$worldId = getWorldId();
$world->begin_transaction();
$account->begin_transaction();
try {
    // The downtime a restore added to the old round's timers, and the round-progress steps already done.
    $world->query('UPDATE config SET delayTime=500, ArtifactsReleased=1, WWPlansReleased=1, WWAlertSent=1');
    $before = $world->query('SELECT startTime, installationTime FROM config')->fetch_assoc();
    $serverBefore = (int)$account->query("SELECT startTime FROM gameServers WHERE worldId='" . $account->real_escape_string($worldId) . "'")->fetch_row()[0];
    $moved = WorldSpeedRound::resetStart($world, $account, $worldId, $now);
    $after = $world->query('SELECT startTime, installationTime, delayTime, ArtifactsReleased, WWPlansReleased, WWAlertSent FROM config')->fetch_assoc();
    $serverAfter = (int)$account->query("SELECT startTime FROM gameServers WHERE worldId='" . $account->real_escape_string($worldId) . "'")->fetch_row()[0];
    if (($moved['startTime'] ?? null) !== (int)$before['startTime'] || ($moved['installationTime'] ?? null) !== (int)$before['installationTime'] || ($moved['ArtifactsReleased'] ?? null) !== 1 || ($moved['delayTime'] ?? null) !== 500) {
        throw new RuntimeException('the reset did not return the values it replaced');
    }
    if ((int)$after['delayTime'] !== 0 || (int)$after['ArtifactsReleased'] !== 0 || (int)$after['WWPlansReleased'] !== 0 || (int)$after['WWAlertSent'] !== 0) {
        throw new RuntimeException('the reset must clear the restore delay and the released/alert flags of the old round: ' . json_encode($after));
    }
    // And it can be undone.
    WorldSpeedRound::restoreStart($world, $moved);
    $undone = $world->query('SELECT startTime, delayTime, ArtifactsReleased, WWPlansReleased, WWAlertSent FROM config')->fetch_assoc();
    if ((int)$undone['startTime'] !== (int)$before['startTime'] || (int)$undone['delayTime'] !== 500 || (int)$undone['ArtifactsReleased'] !== 1 || (int)$undone['WWAlertSent'] !== 1) {
        throw new RuntimeException('restoreStart did not put the columns back: ' . json_encode($undone));
    }
    WorldSpeedRound::resetStart($world, $account, $worldId, $now);
    if ((int)$after['startTime'] !== $now || (int)$after['installationTime'] !== $now || $serverAfter !== $now) {
        throw new RuntimeException('the reset did not move the round start everywhere: ' . json_encode([$after, $serverAfter]));
    }
    if ($serverBefore === $now) {
        throw new RuntimeException('the test needs a world whose server row does not start at the test time');
    }
} catch (Throwable $exception) {
    $world->rollback();
    $account->rollback();
    $fail($exception->getMessage() . '.');
}
$world->rollback();
$account->rollback();
if ((int)$world->query('SELECT startTime FROM config')->fetch_row()[0] === $now) {
    $fail('the fixture reset was not rolled back.');
}

// The tool's dry run on the real world: refused for a speed whose round the world has outlived, accepted with --reset-start.
$currentSpeed = (int)getGameSpeed();
// The fastest speed ends a round after about 30 milliseconds, so any world has outlived it (even one just reset).
$target = $currentSpeed === 1000000000 ? 999999999 : 1000000000;
$tool = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('/travian/tools/set-world-speed.php') . ' --speed=' . $target . ' --dry-run';
exec($tool . ' 2>&1', $output, $status);
if ($status !== 1 || !str_contains(implode("\n", $output), '--reset-start')) {
    $fail('the dry run of a change past the round was not refused: ' . $status . ' ' . implode(' ', $output));
}
$output = [];
exec($tool . ' --reset-start 2>&1', $output, $status);
if ($status !== 0 || !str_contains(implode("\n", $output), 'The round start would move')) {
    $fail('the dry run with --reset-start was not accepted: ' . $status . ' ' . implode(' ', $output));
}

echo "World speed round runtime passed (the refusal rule at six speeds, the reset on both databases, the tool's dry run).\n";
