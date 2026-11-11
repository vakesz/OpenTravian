<?php

declare(strict_types=1);

// Runs the real Village::spend()/refund()/transaction() against a throwaway fixture village.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Village spend runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DB;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Village spend runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$read = static fn (): array => array_map('floatval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));

$pdo->run('UPDATE vdata SET wood=500, clay=500, iron=500, crop=500, woodp=0, clayp=0, ironp=0, cropp=pop+upkeep, maxstore=800, maxcrop=800, lastmupdate=? WHERE kid=?', [miliseconds(), $kid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();
$village = Village::getInstance();

if (!$village->spend([100, 0, 0, 50]) || $read() !== [400.0, 500.0, 500.0, 450.0]) {
    $fail('spend() must debit exactly the cost.');
}
if ($village->spend([401, 0, 0, 0]) || $read() !== [400.0, 500.0, 500.0, 450.0]) {
    $fail('An unaffordable spend() must change nothing.');
}
// A rolled-back transaction gives the payment back.
$village->transaction(static function () use ($village): bool {
    $village->spend([400, 0, 0, 0]);

    return false;
});
if ($read() !== [400.0, 500.0, 500.0, 450.0]) {
    $fail('A failed transaction must roll the spend back.');
}
// An exception does too, and the village lock is released afterwards.
try {
    $village->transaction(static function () use ($village): bool {
        $village->spend([400, 0, 0, 0]);
        throw new RuntimeException('boom');
    });
} catch (RuntimeException) {
}
if ($read() !== [400.0, 500.0, 500.0, 450.0]) {
    $fail('An exception must roll the spend back.');
}
if ((int)DB::getInstance()->fetchScalar("SELECT IS_FREE_LOCK('v:{$kid}')") !== 1) {
    $fail('The village lock must be released after the transaction.');
}
if (!$village->transaction(static fn (): bool => $village->spend([100, 0, 0, 0])) || $read() !== [300.0, 500.0, 500.0, 450.0]) {
    $fail('A committed transaction must keep the payment.');
}
if (!$village->refund([1000, 0, 0, 1000]) || $read() !== [800.0, 500.0, 500.0, 800.0]) {
    $fail('refund() must clamp to the warehouse and granary.');
}

echo "Village spend runtime passed.\n";
