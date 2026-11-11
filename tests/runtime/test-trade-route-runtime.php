<?php

declare(strict_types=1);

// A due trade route debits the village with a guarded UPDATE before it sends, advances to a future
// time (never replaying missed days), and an unpaid route sends nothing.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Trade route runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Model\MarketModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Trade route runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $fixture['kid'];
$to = (int)$pdo->fetchScalar('SELECT kid FROM vdata WHERE kid!=? ORDER BY kid LIMIT 1', [$kid]);
$pdo->run("UPDATE fdata SET f19t=17, f19=10, f20t=0, f20=0 WHERE kid=?", [$kid]);
$pdo->run('UPDATE vdata SET wood=1000, clay=1000, iron=1000, crop=1000, woodp=0, clayp=0, ironp=0, cropp=pop+upkeep, maxstore=5000, maxcrop=5000, lastmupdate=? WHERE kid=?', [miliseconds(), $kid]);
$pdo->run('DELETE FROM `send` WHERE kid=?', [$kid]);

$run = static function (int $routeId) use ($pdo): void {
    $row = $pdo->fetchSingleRow('SELECT * FROM traderoutes WHERE id=?', [$routeId]);
    $automation = (new ReflectionClass(Automation::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($automation, 'processTradeRoute');
    $method->invoke($automation, $row, DB::getInstance(), new MarketModel(), false);
};
$stock = static fn (): array => array_map('floatval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));

// Ten days overdue, 300 wood + 200 clay.
$overdue = time() - 10 * 86400;
$pdo->run('INSERT INTO traderoutes (kid, to_kid, r1, r2, r3, r4, enabled, start_hour, times, time) VALUES (?, ?, 300, 200, 0, 0, 1, 0, 1, ?)', [$kid, $to, $overdue]);
$routeId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
$run($routeId);
$next = (int)$pdo->fetchScalar('SELECT time FROM traderoutes WHERE id=?', [$routeId]);
if ($next <= time()) {
    $fail('An overdue route must be rescheduled into the future, not replayed.');
}
if (abs($next - (time() + 86400)) > 5) {
    $fail("An overdue route restarts one period from now (got +" . ($next - time()) . 's).');
}
if ($stock() !== [700.0, 800.0, 1000.0, 1000.0]) {
    $fail('The sent goods must be debited exactly once: ' . json_encode($stock()));
}
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM `send` WHERE kid=?', [$kid]) !== 1) {
    $fail('Exactly one merchant dispatch must exist.');
}
$run($routeId);
if ($stock() !== [700.0, 800.0, 1000.0, 1000.0] || (int)$pdo->fetchScalar('SELECT COUNT(*) FROM `send` WHERE kid=?', [$kid]) !== 1) {
    $fail('A route that is not due again must not send twice.');
}

echo "Trade route runtime passed.\n";
