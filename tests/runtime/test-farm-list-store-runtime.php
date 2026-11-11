<?php

declare(strict_types=1);

// Runs FarmListStore (the farm-list API's writes) as a throwaway fixture player and reads the result back
// through the read model.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Farm list store runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/build.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/build.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Game\FarmList\FarmListData;
use Game\FarmList\FarmListStore;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Farm list store runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$village = $pdo->fetchSingleRow('SELECT w.id, w.x, w.y FROM wdata w JOIN vdata v ON v.kid=w.id WHERE v.owner<>? AND w.id<>? ORDER BY w.id LIMIT 1', [$user['id'], $kid]);
$oasis = $pdo->fetchSingleRow('SELECT id, x, y FROM wdata WHERE oasistype>0 AND occupied=0 ORDER BY id LIMIT 1');
$empty = $pdo->fetchSingleRow('SELECT x, y FROM wdata WHERE oasistype=0 AND occupied=0 AND fieldtype>0 ORDER BY id LIMIT 1');
if (!$village || !$oasis || !$empty) {
    $fail('The fixture needs one foreign village, one free oasis and one empty field.');
}
register_shutdown_function(static function () use ($pdo, $user): void {
    $pdo->run('DELETE FROM raidlist WHERE lid IN (SELECT id FROM farmlist WHERE owner=?)', [$user['id']]);
    $pdo->run('DELETE FROM farmlist WHERE owner=?', [$user['id']]);
});
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();

$store = new FarmListStore();
$refuses = static function (callable $action, int $status) use ($fail): void {
    try {
        $action();
    } catch (DomainException $exception) {
        if ($exception->getCode() !== $status && !($status === 400 && $exception->getCode() === 0)) {
            $fail("Expected status $status, got {$exception->getCode()}: {$exception->getMessage()}");
        }

        return;
    }
    $fail("Expected a refusal with status $status.");
};
$lists = static fn (array $ids): array => (new FarmListData())->lists($ids);
$troops = static fn (int $t1): array => ['t1' => $t1];

// Lists: create, name rules, update.
$first = $store->createList(['name' => 'One', 'villageId' => $kid, 'defaultUnits' => $troops(5), 'onlyLosses' => true])[0];
$second = $store->createList(['name' => 'Two', 'villageId' => $kid, 'defaultUnits' => []])[0];
$data = $lists([$first, $second]);
if ($data[0]['sortIndex'] !== 1 || $data[1]['sortIndex'] !== 2 || $data[0]['defaultTroop']['t1'] !== 5 || !$data[0]['onlyLosses']) {
    $fail('New lists must be numbered per village and keep their options: ' . json_encode($data));
}
$refuses(static fn () => $store->createList(['name' => 'One', 'villageId' => $kid]), 400);
$refuses(static fn () => $store->createList(['name' => '', 'villageId' => $kid]), 400);
$refuses(static fn () => $store->createList(['name' => 'Other', 'villageId' => $village['id']]), 400);
$refuses(static fn () => $store->createList(['name' => 'Bad', 'villageId' => $kid, 'defaultUnits' => ['t1' => '12x']]), 400);
$store->updateList($second, ['name' => 'Renamed', 'defaultUnits' => $troops(1)]);
if ($lists([$second])[0]['name'] !== 'Renamed') {
    $fail('updateList must rename.');
}
$refuses(static fn () => $store->updateList($second, ['name' => 'One']), 400);

// Slots: add, clash, force, update, delete.
$slot = static fn (int $listId, array $target, int $t1 = 3, bool $active = true): array => ['listId' => $listId, 'x' => $target['x'], 'y' => $target['y'], 'units' => ['t1' => $t1], 'active' => $active];
$store->saveSlots([$slot($first, $village), $slot($first, $oasis, 4, false)], false, false);
$list = $lists([$first])[0];
if ($list['slotsAmount'] !== 2 || $list['slots'][1]['isActive'] || $list['slots'][0]['troop']['t1'] !== 3 || $list['slots'][0]['distance'] <= 0) {
    $fail('Two targets must be added with troops, state and distance: ' . json_encode($list['slots']));
}
$refuses(static fn () => $store->saveSlots([$slot($first, $village)], false, false), 409);
$store->saveSlots([$slot($first, $village, 9)], false, true);
$list = $lists([$first])[0];
if ($list['slotsAmount'] !== 2 || $list['slots'][0]['troop']['t1'] !== 9) {
    $fail('A forced add must overwrite the entry that exists.');
}
$refuses(static fn () => $store->saveSlots([$slot($first, $village, 0)], false, true), 400);
$refuses(static fn () => $store->saveSlots([$slot($first, $empty, 1)], false, false), 404);
$slotId = $list['slots'][1]['id'];
$store->saveSlots([['id' => $slotId] + $slot($first, $oasis, 7, true)], true, false);
$changed = $lists([$first])[0]['slots'][1];
if ($changed['troop']['t1'] !== 7 || !$changed['isActive']) {
    $fail('An update must change the entry in place.');
}
$store->saveSlots([$slot($second, $village)], false, false);
// A bulk change may leave out the target, single troop amounts and the state: those stay as they are.
$store->saveSlots([['id' => $slotId, 'listId' => $first, 'units' => ['t2' => 4]]], true, false);
$kept = $lists([$first])[0]['slots'][1];
if ($kept['troop']['t1'] !== 7 || $kept['troop']['t2'] !== 4 || !$kept['isActive'] || $kept['target']['id'] !== (int)$oasis['id']) {
    $fail('A change without target, state and all troop amounts must keep them: ' . json_encode($kept));
}
$refuses(static fn () => $store->deleteSlots([999999999]), 404);
$store->deleteSlots([$slotId]);
if ($lists([$first])[0]['slotsAmount'] !== 1) {
    $fail('deleteSlots must remove the entry.');
}

// State: sorting, expanded, order, delete.
$store->setSorting($first, 'bounty', 'desc');
if ($lists([$first])[0]['sortField'] !== 'bounty') {
    $fail('setSorting must store the sort.');
}
$refuses(static fn () => $store->setSorting($first, 'nonsense', 'asc'), 400);
$store->setExpanded([$first, $second], 'true');
if (!$lists([$second])[0]['isExpanded']) {
    $fail('setExpanded must expand every list.');
}
$store->moveList($second, 1);
if ($lists([$second])[0]['sortIndex'] !== 1 || $lists([$first])[0]['sortIndex'] !== 2) {
    $fail('moveList must reorder the village.');
}
$store->deleteList($second);
if ($lists([$second]) !== [] || $lists([$first])[0]['sortIndex'] !== 1) {
    $fail('deleteList must remove the list and renumber its village.');
}
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM raidlist WHERE lid=?', [$second]) !== 0) {
    $fail('deleteList must remove its entries.');
}
$refuses(static fn () => $store->deleteList(999999999), 404);

// The endpoints answer with the changed lists, and a refusal is an API error with its status.
$call = static function (string $class, string $method, array $body, array $get = []) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $body;
    $_GET = $get;
    $response = [];
    http_response_code(200);
    (new $class($response))->dispatch();

    return [$response, http_response_code()];
};
[$response, $status] = $call(\Controller\Ajax\farmList\create::class, 'POST', ['name' => 'Api', 'villageId' => $kid]);
if ($status !== 200 || count($response['farmLists']) !== 1 || $response['farmLists'][0]['name'] !== 'Api' || $response['deletedFarmListIds'] !== [] || $response['id'] !== $response['farmLists'][0]['id']) {
    $fail('create must answer with the new list: ' . json_encode($response));
}
$apiList = $response['farmLists'][0]['id'];
[$response, $status] = $call(\Controller\Ajax\farmList\create::class, 'POST', ['name' => 'Api', 'villageId' => $kid]);
if ($status !== 400 || empty($response['error']) || $response['message'] !== T('FarmList', 'farmListNameExistsForVillage')) {
    $fail('A refusal must be an API error with the reference wording.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\slot::class, 'POST', ['slots' => [$slot($apiList, $village)]]);
if ($status !== 200 || $response['farmLists'][0]['slotsAmount'] !== 1) {
    $fail('slot POST must add the target.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\slot::class, 'POST', ['slots' => [$slot($apiList, $village)]]);
if ($status !== 409 || $response['error'] !== 'raidList.targetExists') {
    $fail('slot POST of an existing target must answer 409 targetExists.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\slot::class, 'POST', ['slots' => [$slot($apiList, $village, 8)]], ['force' => '']);
if ($status !== 200 || $response['farmLists'][0]['slots'][0]['troop']['t1'] !== 8) {
    $fail('slot POST with force must overwrite.');
}
$entry = $response['farmLists'][0]['slots'][0]['id'];
[$response, $status] = $call(\Controller\Ajax\farmList\slot::class, 'DELETE', ['slots' => [['id' => $entry]]]);
if ($status !== 200 || $response['farmLists'][0]['slotsAmount'] !== 0) {
    $fail('slot DELETE must remove the target.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\changeSorting::class, 'PUT', ['sortField' => 'distance', 'sortDirection' => 'desc'], ['id' => (string)$apiList]);
if ($status !== 200 || $response['farmLists'][0]['sortDirection'] !== 'desc') {
    $fail('change-sorting must answer with the sorted list.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\view::class, 'GET', [], ['id' => (string)$apiList]);
if ($status !== 200 || $response['farmLists'][0]['id'] !== $apiList) {
    $fail('GET farm-list/<id> must return the list.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\view::class, 'GET', []);
if ($status !== 200 || !isset($response['viewData']['ownPlayer']['farmLists'])) {
    $fail('GET farm-list must return the page data.');
}
[$response, $status] = $call(\Controller\Ajax\farmList\remove::class, 'DELETE', [], ['id' => (string)$apiList]);
if ($status !== 200 || $response['deletedFarmListIds'] !== [$apiList]) {
    $fail('remove must report the deleted list.');
}
echo "Farm list store runtime passed.\n";
