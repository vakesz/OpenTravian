<?php

declare(strict_types=1);

// The weekly top-10 award is never lost: when it fails part-way, lastMedalsGiven is not advanced and no medal is
// kept, so the week stays due; the next pass awards it and advances the marker. Everything runs inside an outer
// transaction that is rolled back at the end, so the live world's medals, points and marker are left as they were.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Medals reset atomic runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Config;
use Core\Database\DB;
use Core\Time;
use Model\MedalsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$db = DB::getInstance();
// Registered before the fixture so the outer transaction is rolled back before the fixture is removed (its rows
// would otherwise stay locked by this connection).
register_shutdown_function(static function () use ($db): void {
    $db->unwindTo(0);
});
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Medals reset atomic runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $fixture['id'];
$interval = (int)Config::getInstance()->game->medals_interval;
$due = time() - $interval - 5;
if ($due <= (int)Config::getInstance()->game->start_time - 7 * Time::DAY) {
    echo "Medals reset atomic runtime skipped (the round has not started).\n";
    exit(0);
}

$db->begin_transaction();
$db->run('UPDATE config SET lastMedalsGiven=?', [$due]);
$db->run('UPDATE users SET week_attack_points=?, hidden=0 WHERE id=?', [9000000000, $uid]);

$marker = static fn (): int => (int)$db->fetchScalar('SELECT lastMedalsGiven FROM config');
$medals = static fn (): int => (int)$db->fetchScalar("SELECT COUNT(*) FROM medal WHERE uid=$uid");
$allMedals = (int)$db->fetchScalar('SELECT COUNT(*) FROM medal');

$failing = new class extends MedalsModel {
    public bool $called = false;

    protected function handlePrizeBonus(string $type, int $uid, int $rank): void
    {
        $this->called = true;
        throw new RuntimeException('injected failure');
    }
};
$thrown = false;
try {
    $failing->resetMedals();
} catch (RuntimeException $exception) {
    $thrown = $exception->getMessage() === 'injected failure';
}
if (!$failing->called || !$thrown) {
    $fail('The award must reach the prize bonus and the injected failure must reach the caller.');
}
if ($db->transactionLevel() !== 1) {
    $fail('A failed award must unwind only its own savepoint, at level ' . $db->transactionLevel() . '.');
}
if ($marker() !== $due || (int)$db->fetchScalar('SELECT COUNT(*) FROM medal') !== $allMedals) {
    $fail("A failed award must keep the week due and keep no medal (marker {$marker()} vs $due).");
}

(new MedalsModel())->resetMedals();
if ($marker() !== $due + $interval) {
    $fail("The retry must advance lastMedalsGiven by one interval (marker {$marker()}, expected " . ($due + $interval) . ').');
}
if ($medals() < 1) {
    $fail('The retry must award the fixture player the top attacker medal.');
}
if ((int)$db->fetchScalar("SELECT week_attack_points FROM users WHERE id=$uid") !== 0) {
    $fail('The retry must reset the weekly points.');
}

$db->unwindTo(0);
echo "Medals reset atomic runtime passed.\n";
