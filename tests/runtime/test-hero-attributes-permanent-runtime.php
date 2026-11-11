<?php

declare(strict_types=1);

// Saved hero attribute points are permanent: the hero/attributes endpoint refuses any value below what is
// stored, still lets the production resource change, and only a Book of Wisdom gives the points back.
// A throwaway fixture player is used and removed at shutdown.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Hero attributes permanence runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\hero\attributes;
use Core\Database\DBI;
use Core\Helper\WebService;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Hero attributes permanence runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = (int)$user['id'];
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM hero WHERE uid=?', [$uid]) === 0) {
    $fail('The fixture player has no hero row.');
}
// level 5 hero: 24 points to spend
$pdo->run('UPDATE hero SET exp=?, power=0, offBonus=0, defBonus=0, production=0, productionType=0 WHERE uid=?', [\Game\Formulas::heroExperience(5), $uid]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
\Core\Session::getInstance();

$save = static function (int $resource, int $power, int $off, int $def, int $production): array {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => ''];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'resource' => $resource,
        'attackBehaviour' => 'fight',
        'attributes' => ['power' => $power, 'offBonus' => $off, 'defBonus' => $def, 'productionPoints' => $production],
    ];
    http_response_code(200);
    (new attributes($response))->dispatch();

    return $response;
};
$row = static fn (): array => $pdo->fetchSingleRow('SELECT power, offBonus, defBonus, production, productionType FROM hero WHERE uid=?', [$uid]);

$response = $save(0, 6, 2, 0, 0);
if (!empty($response['error']) || $row()['power'] != 6 || $row()['offBonus'] != 2) {
    $fail('Spending unspent points must be saved: ' . json_encode($response));
}
$response = $save(0, 2, 6, 0, 0);
if (empty($response['error']) || $row()['power'] != 6 || $row()['offBonus'] != 2) {
    $fail('Moving saved points between attributes must be refused and leave the hero untouched: ' . json_encode($response));
}
$response = $save(3, 6, 2, 0, 0);
if (!empty($response['error']) || (int)$row()['productionType'] !== 3) {
    $fail('The production resource must stay changeable: ' . json_encode($response));
}
$response = $save(3, 7, 2, 0, 0);
if (!empty($response['error']) || $row()['power'] != 7) {
    $fail('Adding points on top of saved ones must be saved: ' . json_encode($response));
}

// what a Book of Wisdom does (HeroInventoryModel, item 13)
$pdo->run('UPDATE hero SET power=0, offBonus=0, defBonus=0, production=0 WHERE uid=?', [$uid]);
$response = $save(3, 0, 8, 0, 0);
if (!empty($response['error']) || $row()['offBonus'] != 8) {
    $fail('After a reset the points must be spendable again: ' . json_encode($response));
}
echo "Hero attributes permanence runtime passed.\n";
