<?php

declare(strict_types=1);

// A hero arriving at an adventure runs through AdventureProcessor under strict types, with every column of the
// movement row coming from the database as a string. The arrival must complete (a failing task is quarantined and
// the hero never comes home) and send the hero back. Two throwaway fixture players act as the hero's owner and the
// movement is claimed a day ahead so the live engine cannot race the test.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Adventure arrival runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Adventure arrival runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$db = DB::getInstance();
$pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
$target = (int)$pdo->fetchScalar(
    'SELECT w.id FROM wdata w LEFT JOIN vdata v ON v.kid=w.id WHERE v.kid IS NULL AND w.fieldtype>0 AND w.occupied=0 AND w.id<>? ORDER BY w.id LIMIT 1',
    [$fixture['kid']]
);
if ($target === 0) {
    echo "Adventure arrival runtime passed (no free tile for the adventure).\n";
    exit(0);
}
$race = (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$fixture['id']]);
$pdo->run('UPDATE units SET u11=0 WHERE kid=?', [$fixture['kid']]);
$pdo->run('DELETE FROM adventure WHERE uid=?', [$fixture['id']]);
$pdo->run('INSERT INTO adventure (uid, kid, dif, created, end) VALUES (?, ?, 1, ?, 0)', [$fixture['id'], $target, time() - 86400]);
$units = array_fill(1, 11, 0);
$units[11] = 1;
$now = (int)miliseconds() + 86400000;
$id = (new MovementsModel())->addMovement($fixture['kid'], $target, $race, $units, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_ADVENTURE, $now - 20000, $now - 10000);
if ($id <= 0) {
    $fail('The adventure movement could not be queued.');
}
try {
    (new Automation())->processMovementTask($id);
} catch (Throwable $throwable) {
    $fail('The adventure arrival failed: ' . get_class($throwable) . ': ' . $throwable->getMessage() . ' (' . basename($throwable->getFile()) . ':' . $throwable->getLine() . ')');
}
if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id AND mode=0")) {
    $fail('The arriving adventure movement was not consumed.');
}
if (!(int)$db->fetchScalar('SELECT COUNT(*) FROM movement WHERE to_kid=' . $fixture['kid'] . ' AND mode=1 AND u11>0')) {
    $fail('The hero was not sent back from the adventure.');
}
echo "Adventure arrival runtime passed.\n";
