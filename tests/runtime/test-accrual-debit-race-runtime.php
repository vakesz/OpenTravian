<?php

declare(strict_types=1);

// Resource accrual must not overwrite a debit that committed between the accrual's read and its write, and one
// interval must be credited only once (the lastmupdate guard).
//
// The race is replayed deterministically: a Village is given the state it would have read (stock and lastmupdate),
// a debit then commits, and only then does the Village persist its load-time accrual. ResourcesHelper has no seam
// between its read and its write, so for it this checks the relative, clamped credit and the guard on a stale row.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Accrual/debit race runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Village;
use Game\ResourcesHelper;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Accrual/debit race runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Accrual/debit race runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $fixture['kid'];
$stock = static fn (): array => array_map('floatval', array_values($pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid])));
$near = static fn (float $a, float $b): bool => abs($a - $b) < 0.01;

// ---- Village::persistAccruedResources (load-time accrual) ----------------------------------------------------
$t0 = miliseconds() - 10000;
$pdo->run(
    'UPDATE vdata SET wood=500, clay=500, iron=500, crop=500, woodp=3600, clayp=3600, ironp=3600, cropp=pop+upkeep+3600, maxstore=100000, maxcrop=100000, lastmupdate=? WHERE kid=?',
    [$t0, $kid]
);
// What the page load read (500 each at $t0) and accrued over 10 s at 3600/h: +10 each.
$village = (new ReflectionClass(Village::class))->newInstanceWithoutConstructor();
$set = static function (string $property, mixed $value) use ($village): void {
    (new ReflectionProperty(Village::class, $property))->setValue($village, $value);
};
$set('db', $pdo);
$set('village', ['kid' => $kid, 'wood' => 510.0, 'clay' => 510.0, 'iron' => 510.0, 'crop' => 510.0, 'lastmupdate' => $t0 + 10000]);
$set('loadedStock', [500.0, 500.0, 500.0, 500.0]);
$persist = new ReflectionMethod(Village::class, 'persistAccruedResources');

// Meanwhile a payment commits.
if (!Village::debit($kid, [0, 0, 0, 100])) {
    $fail('the debit must succeed.');
}
if (!$persist->invoke($village, $t0)) {
    $fail('the accrual must be written when nobody else accrued first.');
}
$after = $stock();
if (!$near($after[0], 510) || !$near($after[3], 410)) {
    $fail('the accrual must be added on top of the debit (expected wood 510, crop 410): ' . json_encode($after));
}
// The same accrual again (a second request that read the same lastmupdate) must not credit the interval twice.
if ($persist->invoke($village, $t0) || $stock() !== $after) {
    $fail('a stale lastmupdate must not credit the same interval twice: ' . json_encode($stock()));
}
// The credit is clamped to the stores.
$pdo->run('UPDATE vdata SET wood=99995, lastmupdate=? WHERE kid=?', [$t0, $kid]);
$persist->invoke($village, $t0);
if (!$near($stock()[0], 100000)) {
    $fail('the accrual must be clamped to the warehouse: ' . json_encode($stock()));
}

// ---- ResourcesHelper::updateVillageResources (simple mode) --------------------------------------------------
$t1 = miliseconds() - 10000;
$pdo->run('UPDATE vdata SET wood=500, clay=500, iron=500, crop=500, maxstore=100000, maxcrop=100000, lastmupdate=? WHERE kid=?', [$t1, $kid]);
if (!Village::debit($kid, [0, 0, 0, 100])) {
    $fail('the second debit must succeed.');
}
ResourcesHelper::updateVillageResources($kid, true);
$after = $stock();
// About 10 s at 3600/h, plus the few milliseconds this test takes.
if ($after[0] < 509.99 || $after[0] > 511 || $after[3] < 409.99 || $after[3] > 411) {
    $fail('updateVillageResources must keep the debit and add the accrual (about wood 510, crop 410): ' . json_encode($after));
}
$pdo->run('UPDATE vdata SET wood=99999, lastmupdate=? WHERE kid=?', [$t1, $kid]);
ResourcesHelper::updateVillageResources($kid, true);
if (!$near($stock()[0], 100000)) {
    $fail('updateVillageResources must clamp the accrual to the warehouse: ' . json_encode($stock()));
}

// ---- full mode keeps refreshing the cached production ------------------------------------------------------
$pdo->run('UPDATE vdata SET woodp=1, lastmupdate=? WHERE kid=?', [miliseconds(), $kid]);
ResourcesHelper::updateVillageResources($kid, false);
if ((float)$pdo->fetchScalar('SELECT woodp FROM vdata WHERE kid=?', [$kid]) === 1.0) {
    $fail('full mode must store the recomputed production.');
}

echo "Accrual/debit race runtime passed.\n";
