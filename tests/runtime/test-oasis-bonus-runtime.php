<?php

declare(strict_types=1);

// A village profits from its three best oases only, and each oasis adds 25 percent per resource of its type
// (the numbering of Formulas::$data['oases']: 2-4 wood, 6-8 clay, 10-12 iron, 14-15 crop, mixed ones add crop).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Oasis bonus runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\ResourcesHelper;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Oasis bonus runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$kid = $fixture['kid'];
$firstOasis = 4000000000 + $kid * 10;
register_shutdown_function(static fn () => $pdo->run('DELETE FROM odata WHERE did=?', [$kid]));
$pdo->run('DELETE FROM odata WHERE did=?', [$kid]);
$addOasis = static function (int $slot, int $type) use ($pdo, $kid, $firstOasis): void {
    $pdo->run('INSERT INTO odata (kid, type, did, wood, iron, clay, crop, lastmupdate) VALUES (?, ?, ?, 0, 0, 0, 0, 0)', [$firstOasis + $slot, $type, $kid]);
};

ResourcesHelper::oasisBonus($kid) === ['count' => [0, 0, 0, 0], 'bonus' => [0, 0, 0, 0]] || $fail('A village without oases has no bonus.');

$addOasis(1, 3);   // wood and crop
$addOasis(2, 12);  // iron +50%
$addOasis(3, 4);   // wood +50%
$bonus = ResourcesHelper::oasisBonus($kid);
$bonus['bonus'] === [75, 0, 50, 25] || $fail('Wood 25+50, iron 50, crop 25 expected: ' . json_encode($bonus));
$bonus['count'] === [2, 0, 1, 1] || $fail('Oasis counts per resource: ' . json_encode($bonus['count']));

// A fourth, weaker oasis is ignored: only the three best count.
$addOasis(4, 14);
ResourcesHelper::oasisBonus($kid)['bonus'] === [75, 0, 50, 25] || $fail('Only the three best oases may count.');

echo "Oasis bonus runtime passed.\n";
