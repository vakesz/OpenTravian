<?php

declare(strict_types=1);

// A village that cannot be created leaves its map slot as it found it: the occupied flags of wdata and
// available_villages are set in the same transaction as the vdata insert, so a failed insert rolls them back.
// The insert is made to fail by creating a village on the fixture's own slot (its vdata row already exists) after
// clearing the slot's flags. Everything runs inside an outer transaction that is rolled back at the end.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Create village occupied runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DB;
use Model\RegisterModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$db = DB::getInstance();
// Registered before the fixture so the outer transaction is rolled back before the fixture is removed.
register_shutdown_function(static function () use ($db): void {
    $db->unwindTo(0);
});
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Create village occupied runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $fixture['id'];
$kid = $fixture['kid'];
$race = (int)$db->fetchScalar("SELECT race FROM users WHERE id=$uid");
$hasAvailableRow = (int)$db->fetchScalar("SELECT COUNT(*) FROM available_villages WHERE kid=$kid") === 1;

$db->begin_transaction();
$db->query("UPDATE wdata SET occupied=0 WHERE id=$kid");
$db->query("UPDATE available_villages SET occupied=0 WHERE kid=$kid");

if ((new RegisterModel())->createNewVillage($uid, $race, $kid)) {
    $fail('A village must not be created on a slot that already holds one.');
}
if ($db->transactionLevel() !== 1) {
    $fail('A failed village creation must unwind only its own savepoint, at level ' . $db->transactionLevel() . '.');
}
if ((int)$db->fetchScalar("SELECT occupied FROM wdata WHERE id=$kid") !== 0) {
    $fail('A failed village creation must leave wdata.occupied as it was.');
}
if ($hasAvailableRow && (int)$db->fetchScalar("SELECT occupied FROM available_villages WHERE kid=$kid") !== 0) {
    $fail('A failed village creation must leave available_villages.occupied as it was.');
}

$db->unwindTo(0);
echo "Create village occupied runtime passed.\n";
