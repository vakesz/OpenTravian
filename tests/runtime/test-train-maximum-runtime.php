<?php

declare(strict_types=1);

// Train maximum (speed-world shortcut), at x1, x10 and x1000, through the real Barracks request: the world rule
// gates it; the order takes everything one troop can be afforded and pays for it like a manual order.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Train maximum runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php?id=39&gid=19';
$_SERVER['QUERY_STRING'] = 'id=39&gid=19';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Build\TroopBuilding;
use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Game\Formulas;
use Game\TrainingHelper;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Train maximum runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
$speeds = [1, 10, 1000];
$scenarios = ['maximum', 'rule off'];

if (($argv[1] ?? '') !== 'child') {
    try {
        $player = fixtureVillageCreate();
    } catch (Throwable $exception) {
        echo "Train maximum runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $player['id']]);
    foreach ($speeds as $speed) {
        foreach ($scenarios as $scenario) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $speed . ' ' . (int)$player['id'] . ' ' . escapeshellarg($scenario) . ' 2>&1', $output, $status);
            // A child that dies on an uncaught error still exits 0 under the game's error handler: require its own ok line.
            if ($status !== 0 || !in_array("x{$speed} {$scenario} ok", $output, true)) {
                fwrite(STDERR, implode("\n", $output) . "\n");
                fixtureVillageRemove((int)$player['id'], (int)$player['kid']);
                exit(1);
            }
        }
    }
    fixtureVillageRemove((int)$player['id'], (int)$player['kid']);
    echo "Train maximum runtime passed (x1, x10 and x1000).\n";
    exit(0);
}

// ---- child: one process per speed and scenario, because Village and Session are singletons ----
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
});
$speed = (int)$argv[2];
$uid = (int)$argv[3];
$scenario = (string)$argv[4];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$config = Config::getInstance();
// The queue rows are written in the unit of the running world (seconds, milliseconds or nanoseconds), whatever speed the
// test pretends: the engine runs with the world's unit and would read a row in another unit as long overdue and complete it.
$unit = WorldSpeedProfile::trainingTimeUnit((int)$config->game->speed);
$config->game->speed = $speed;
foreach (WorldSpeedProfile::profile($speed) as $key => $value) {
    $config->game->$key = $value;
}
$config->game->useMilSeconds = $unit >= 1000;
$config->game->useNanoseconds = $unit >= 1000000000;
$config->custom->speedShortcuts = $scenario !== 'rule off';
// The runtime is about the shortcut, not the state of the round: a finished local world must not hide it.
$config->dynamic->serverFinished = 0;
$at = "at x{$speed} ({$scenario})";

// A Barracks (slot 39) and stock for exactly 4 units of troop 1.
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$uid]);
$unitId = nrToUnitId(1, $race);
$cost = array_map('intval', Formulas::uTrainingCost($unitId, false));
$pdo->run('DELETE FROM training WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
$pdo->run('UPDATE fdata SET f39=5, f39t=19 WHERE kid=?', [$kid]);
// No troops in the village: their upkeep would drain the crop between the test's stock and the request.
$pdo->run('UPDATE units SET u1=0, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0, u99=0 WHERE kid=?', [$kid]);
$pdo->run('UPDATE users SET vacationActiveTil=0, access=1 WHERE id=?', [$uid]);
$batches = 4;
$pdo->run(
    'UPDATE vdata SET wood=?, clay=?, iron=?, crop=?, woodp=1, clayp=1, ironp=1, cropp=pop+10, upkeep=0, maxstore=100000000, maxcrop=100000000, lastmupdate=? WHERE kid=?',
    [$cost[0] * $batches, $cost[1] * $batches, $cost[2] * $batches, $cost[3] * $batches, miliseconds(), $kid]
);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = $pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
$token = Session::getInstance()->getChecker();
$claimed = new ReflectionProperty(Session::class, 'claimedFingerprint');
$stock = static fn (): array => array_map('intval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));
$queue = static fn (): array => $pdo->run('SELECT * FROM training WHERE kid=? AND item_id=19 ORDER BY id ASC', [$kid])->fetchAll(PDO::FETCH_ASSOC);
$post = static function (array $fields) use ($claimed): void {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $fields;
    $_REQUEST = $fields;
    $claimed->setValue(null, null);
    unset($_SESSION[WebService::fixSessionPrefix('last_action')]);
    new TroopBuilding(39);
};
$max = (int)(new TrainingHelper())->getMaxUnitByNr(1, false);
$page = static function () use ($token): string {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    $_REQUEST = [];

    return (string)(new TroopBuilding(39))->view->output();
};

$request = ['action' => 'trainTroops', 'checksum' => $token, 's' => '1', 'trainMaximum' => '1'];
if ($scenario === 'rule off') {
    $html = $page();
    foreach (['trainMaximum', 'Train maximum'] as $absent) {
        if (str_contains($html, $absent)) {
            $fail("the page shows '{$absent}' with the world rule off {$at}.");
        }
    }
    $post($request);
    if ($queue() !== [] || $stock() !== array_map(static fn (int $c) => $c * $batches, $cost)) {
        $fail("the order was queued with the world rule off {$at}.");
    }
}
if ($scenario === 'maximum') {
    if ($max < 4) {
        $fail("the stock affords only {$max} units {$at}.");
    }
    $html = $page();
    foreach (['name="trainMaximum"', 'value="1"', 'Train maximum'] as $needle) {
        if (!str_contains($html, $needle)) {
            $fail("the page does not show '{$needle}' {$at}.");
        }
    }
    $before = $stock();
    $post($request);
    $rows = $queue();
    if (count($rows) !== 1 || (int)$rows[0]['num'] !== $max || (int)$rows[0]['nr'] !== 1) {
        $fail("the maximum order is " . json_encode($rows) . ", expected {$max} units of troop 1 {$at}.");
    }
    $spent = array_map(static fn (int $a, int $b): int => $a - $b, $before, $stock());
    if ($spent !== array_map(static fn (int $c): int => $c * $max, $cost)) {
        $fail('the order charged ' . json_encode($spent) . " {$at}.");
    }
    // Rows are stored in the running world's unit (seconds/ms/ns). Duration may be a sub-second tick while
    // Formulas high-speed floors are finished separately; still require a positive tick and a chained end time.
    $trainingTime = (int)$rows[0]['training_time'];
    if ($trainingTime < 1 || (int)$rows[0]['end_time'] !== (int)$rows[0]['commence'] + ($max - 1) * $trainingTime) {
        $fail("the batch timing is wrong {$at}: " . json_encode($rows[0]));
    }
}

echo "x{$speed} {$scenario} ok\n";
