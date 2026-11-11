<?php

declare(strict_types=1);

// The Barracks training form posts the reference's request shape (action=trainTroops plus the checksum): only that
// request queues troops, the old id/z/a fields queue nothing, and the form carries the reference's hidden fields.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Troop training request runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php?id=39&gid=19';
$_SERVER['QUERY_STRING'] = 'id=39&gid=19';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Build\TroopBuilding;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Troop training request runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$pdo->run('UPDATE fdata SET f39=5, f39t=19 WHERE kid=?', [$kid]);
$pdo->run('UPDATE vdata SET wood=100000, clay=100000, iron=100000, crop=100000, maxstore=200000, maxcrop=200000, lastmupdate=? WHERE kid=?', [time(), $kid]);
$pdo->run('DELETE FROM training WHERE kid=?', [$kid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
$token = Session::getInstance()->getChecker();
$claimed = new ReflectionProperty(Session::class, 'claimedFingerprint');
$queued = static fn (): int => (int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM training WHERE kid=? AND item_id=19', [$kid]);
$post = static function (array $fields) use ($claimed): TroopBuilding {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $fields;
    $_REQUEST = $fields;
    $claimed->setValue(null, null);
    unset($_SESSION[WebService::fixSessionPrefix('last_action')]);

    return new TroopBuilding(39);
};

$post(['id' => '39', 'z' => $token, 'a' => '2', 's' => '1', 't1' => '3']);
$queued() === 0 || $fail('The old id/z/a fields must queue nothing.');
$post(['checksum' => $token, 's' => '1', 't1' => '3']);
$queued() === 0 || $fail('A post without action=trainTroops must queue nothing.');
$post(['action' => 'trainTroops', 'checksum' => 'stale', 's' => '1', 't1' => '3']);
$queued() === 0 || $fail('A post with a wrong checksum must queue nothing.');
$post(['action' => 'trainTroops', 'checksum' => $token, 's' => '1', 't1' => '3']);
$queued() === 3 || $fail('The reference request shape must queue the troops, queued ' . $queued());

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$html = (string)(new TroopBuilding(39))->view->output();
foreach (['action="/build.php?id=39&amp;gid=19"', 'name="action" value="trainTroops"', 'name="checksum" value="' . $token . '"', 'name="s" value="1"', 'name="did"'] as $needle) {
    str_contains($html, $needle) || $fail('The training form is missing: ' . $needle);
}
foreach (['name="z"', 'name="a"', 'name="id"'] as $gone) {
    !str_contains($html, $gone) || $fail('The training form must not carry the old field: ' . $gone);
}

echo "Troop training request runtime passed.\n";
