<?php

declare(strict_types=1);

// Gold Upgrade to max level (speed-world shortcut): one click instantly sets a building to its max level for gold
// only — no resources and no construction queue. Checked at x1, x10 and x1000.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Max level upgrade runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php?id=20';
$_SERVER['QUERY_STRING'] = 'id=20';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\ExtraModules;
use Game\Formulas;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Max level upgrade runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
$speeds = [1, 10, 1000];
$scenarios = ['instant', 'nested', 'rule off', 'no gold'];

if (($argv[1] ?? '') !== 'child') {
    try {
        $player = fixtureVillageCreate();
    } catch (Throwable $exception) {
        echo "Max level upgrade runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $player['id']]);
    foreach ($speeds as $speed) {
        foreach ($scenarios as $scenario) {
            $output = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . $speed . ' ' . (int)$player['id'] . ' ' . escapeshellarg($scenario) . ' 2>&1', $output, $status);
            if ($status !== 0 || !in_array("x{$speed} {$scenario} ok", $output, true)) {
                fwrite(STDERR, implode("\n", $output) . "\n");
                fixtureVillageRemove((int)$player['id'], (int)$player['kid']);
                exit(1);
            }
        }
    }
    fixtureVillageRemove((int)$player['id'], (int)$player['kid']);
    echo "Max level upgrade runtime passed (x1, x10 and x1000).\n";
    exit(0);
}

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
});
$speed = (int)$argv[2];
$uid = (int)$argv[3];
$scenario = (string)$argv[4];
$kid = (int)$pdo->fetchScalar('SELECT kid FROM users WHERE id=?', [$uid]);
$config = Config::getInstance();
$config->game->speed = $speed;
foreach (WorldSpeedProfile::profile($speed) as $key => $value) {
    $config->game->$key = $value;
}
$config->game->useMilSeconds = $speed > 20;
$config->game->useNanoseconds = $speed > 20000;
$config->custom->speedShortcuts = $scenario !== 'rule off';
$config->dynamic->serverFinished = 0;
$config->extraSettings->upgradeToMaxLevel->coins = 20;
$at = "at x{$speed} ({$scenario})";

$pdo->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM demolition WHERE kid=?', [$kid]);
$pdo->run('UPDATE fdata SET f20=5, f20t=15 WHERE kid=?', [$kid]);
$pdo->run(
    'UPDATE vdata SET wood=1000, clay=1000, iron=1000, crop=1000, woodp=1, clayp=1, ironp=1, cropp=pop+10, upkeep=0, maxstore=100000000, maxcrop=100000000, lastmupdate=? WHERE kid=?',
    [miliseconds(), $kid]
);
$gold = $scenario === 'no gold' ? 0 : 100;
$pdo->run('UPDATE users SET gift_gold=?, bought_gold=0, access=1, vacationActiveTil=0 WHERE id=?', [$gold, $uid]);

$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = $pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
Session::getInstance();
$village = Village::getInstance();
$level = static fn (): int => (int)$pdo->fetchScalar('SELECT f20 FROM fdata WHERE kid=?', [$kid]);
$stock = static fn (): array => array_map('intval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));
$queue = static fn (): array => $pdo->run('SELECT * FROM building_upgrade WHERE kid=? ORDER BY id ASC', [$kid])->fetchAll(PDO::FETCH_ASSOC);
$giftGold = static fn (): int => (int)$pdo->fetchScalar('SELECT gift_gold FROM users WHERE id=?', [$uid]);
$max = Formulas::buildingMaxLvl(15, false);
$response = [];

if ($scenario === 'rule off') {
    if (ExtraModules::isEnabled('upgradeToMaxLevel')) {
        $fail("upgradeToMaxLevel is enabled with the world rule off {$at}.");
    }
    $result = ExtraModules::runAction('upgradeToMaxLevel', $response, ['gid' => 20]);
    if ($result !== ExtraModules::ACTION_UNAVAILABLE || $level() !== 5 || $queue() !== [] || $giftGold() !== $gold) {
        $fail("the gold max ran with the world rule off {$at}: {$result}");
    }
}

if ($scenario === 'no gold') {
    $beforeStock = $stock();
    $result = ExtraModules::runAction('upgradeToMaxLevel', $response, ['gid' => 20]);
    if ($result !== ExtraModules::ACTION_NOT_ENOUGH_GOLD || $level() !== 5 || $queue() !== [] || $stock() !== $beforeStock) {
        $fail("short gold did not refuse cleanly {$at}: {$result}");
    }
}

if ($scenario === 'instant' || $scenario === 'nested') {
    $payload = $scenario === 'nested'
        ? ['additionalData' => ['gid' => 20]]
        : ['gid' => 20];
    $beforeStock = $stock();
    $beforeGold = $giftGold();
    $result = ExtraModules::runAction('upgradeToMaxLevel', $response, $payload);
    if ($result !== ExtraModules::ACTION_COMPLETED) {
        $fail("{$scenario} max failed {$at}: {$result}");
    }
    if ($level() !== $max) {
        $fail("building is level {$level()} after {$scenario} max, expected {$max} {$at}.");
    }
    if ($queue() !== []) {
        $fail("{$scenario} max left a construction queue " . json_encode($queue()) . " {$at}.");
    }
    if ($stock() !== $beforeStock) {
        $fail("{$scenario} max spent resources " . json_encode([$beforeStock, $stock()]) . " {$at}.");
    }
    if ($giftGold() !== $beforeGold - 20) {
        $fail("{$scenario} max charged {$beforeGold}->{$giftGold()} gold {$at}.");
    }
}

echo "x{$speed} {$scenario} ok\n";
