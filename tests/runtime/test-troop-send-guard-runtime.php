<?php

declare(strict_types=1);

// Units::modifyUnits() subtracts sent troops only when the village still has all of them, so a second send of
// troops that already left changes nothing instead of driving the unsigned columns below zero.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Troop send guard runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\Units;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $village = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Troop send guard runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo->run('UPDATE units SET u1=5, u2=4 WHERE kid=?', [$village['kid']]);
$count = static fn (int $nr): int => (int)$pdo->fetchScalar("SELECT u{$nr} FROM units WHERE kid=?", [$village['kid']]);

if (Units::modifyUnits($village['kid'], [1 => 3, 2 => 4]) !== true || $count(1) !== 2 || $count(2) !== 0) {
    $fail('Sending troops the village has must subtract them.');
}
if (Units::modifyUnits($village['kid'], [1 => 2, 2 => 1]) !== false || $count(1) !== 2 || $count(2) !== 0) {
    $fail('A send of troops that already left must fail and change nothing, not even the troops still present.');
}

echo "Troop send guard runtime passed.\n";
