<?php

declare(strict_types=1);

// A signed-in request records the player's last-seen time at most once a minute: a value
// written 30 seconds ago stays, one written two minutes ago moves to now.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Last-seen throttle runtime skipped outside the php container.\n";
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

$fail = static function (string $message): never {
    fwrite(STDERR, 'Last-seen throttle runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

if (($argv[1] ?? '') !== 'child') {
    try {
        $player = fixtureVillageCreate();
    } catch (Throwable $exception) {
        echo "Last-seen throttle runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    foreach (['30', '120'] as $age) {
        $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time() - (int)$age, time() - (int)$age, $player['id']]);
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . (int)$player['id'] . ' ' . $age, $status);
        if ($status !== 0) {
            exit(1);
        }
    }
    echo "Last-seen throttle runtime passed (kept a 30-second-old value, refreshed a two-minute-old one).\n";
    exit(0);
}

// ---- child: one process per case, because Session is a singleton ----
$uid = (int)$argv[2];
$age = (int)$argv[3];
$before = (int)$pdo->fetchScalar('SELECT last_login_time FROM users WHERE id=?', [$uid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = $pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
if (!Session::getInstance()->isValid()) {
    $fail('the fixture player is not signed in.');
}
$after = (int)$pdo->fetchScalar('SELECT last_login_time FROM users WHERE id=?', [$uid]);
if ($age < 60 && $after !== $before) {
    $fail("a {$age}-second-old last-seen time was rewritten ($before -> $after).");
}
if ($age >= 60 && $after < time() - 2) {
    $fail("a {$age}-second-old last-seen time was not refreshed ($before -> $after).");
}
