<?php

declare(strict_types=1);

// Finishing several warehouse levels at once (finish-now, starter buildings) must give exactly the capacity of the
// new level. A new warehouse built straight from level 0 used to lose the 800 base twice.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Storage multilevel runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Game\Gid;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Storage multilevel runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Storage multilevel runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $fixture['kid'];
$base = Formulas::storeCAP(0);
$full = Formulas::storeCAP(20);

// No warehouse yet: the village has the base capacity only. Slots 20 and 21 become warehouses at level 0
// (a building that was just placed), the state BuildingAction::upgrade() finishes from.
$pdo->run('UPDATE vdata SET maxstore=?, extraMaxstore=0 WHERE kid=?', [$base, $kid]);
$pdo->run('UPDATE fdata SET f20t=?, f20=0, f21t=?, f21=0 WHERE kid=?', [Gid::WAREHOUSE, Gid::WAREHOUSE, $kid]);
$maxstore = static fn (): float => (float)$pdo->fetchScalar('SELECT maxstore FROM vdata WHERE kid=?', [$kid]);

BuildingAction::upgrade($kid, 20, 20, true);
if (abs($maxstore() - $full) > 0.01) {
    $fail('a first warehouse finished from level 0 to 20 must hold ' . $full . ', not ' . $maxstore() . '.');
}
BuildingAction::upgrade($kid, 21, 20, true);
if (abs($maxstore() - 2 * $full) > 0.01) {
    $fail('a second warehouse finished from level 0 to 20 must add ' . $full . ', but the total is ' . $maxstore() . '.');
}

echo "Storage multilevel runtime passed.\n";
