<?php

declare(strict_types=1);

// Builds the farm list page's viewData for a throwaway fixture player: list and slot shape, target types,
// server-side sorting and the running-raid state.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Farm list data runtime skipped outside the php container.\n";
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
use Game\FarmList\FarmListInbox;
use Model\MovementsModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Farm list data runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$kid = $user['kid'];
$village = $pdo->fetchSingleRow('SELECT w.id, w.x, w.y FROM wdata w JOIN vdata v ON v.kid=w.id WHERE v.owner<>? AND w.id<>? ORDER BY w.id LIMIT 1', [$user['id'], $kid]);
$oasis = $pdo->fetchSingleRow('SELECT id, x, y FROM wdata WHERE oasistype>0 AND occupied=0 ORDER BY id LIMIT 1');
if (!$village || !$oasis) {
    $fail('The fixture needs one foreign village and one free oasis.');
}
$pdo->run('INSERT INTO farmlist (kid, owner, name, sortIndex, isExpanded, sortField, sortDirection, u1) VALUES (?, ?, ?, 3, 1, ?, ?, 5)', [$kid, $user['id'], 'Fixture', 'distance', 'desc']);
$listId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
register_shutdown_function(static function () use ($pdo, $listId): void {
    $pdo->run('DELETE FROM raidlist WHERE lid=?', [$listId]);
    $pdo->run('DELETE FROM farmlist WHERE id=?', [$listId]);
});
$pdo->run('INSERT INTO raidlist (lid, kid, distance, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, isActive) VALUES (?, ?, 2.5, 7, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1)', [$listId, $village['id']]);
$villageSlot = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
$pdo->run('INSERT INTO raidlist (lid, kid, distance, u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, isActive) VALUES (?, ?, 9.0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)', [$listId, $oasis['id']]);
$oasisSlot = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
// A raid far in the future so the engine never resolves it.
$pdo->run(
    'INSERT INTO movement (kid, to_kid, race, u1, mode, attack_type, start_time, end_time) VALUES (?, ?, 1, 7, 0, ?, ?, ?)',
    [$kid, $village['id'], MovementsModel::ATTACKTYPE_RAID, (time() + 86400) * 1000, (time() + 86500) * 1000]
);
register_shutdown_function(static fn () => $pdo->run('DELETE FROM movement WHERE kid=? AND end_time>?', [$kid, time() * 1000 + 86000000]));

$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
Session::getInstance();
$data = (new FarmListData())->viewData();
$own = $data['ownPlayer'];

if ($own['isSitter'] || $own['isInVacationMode'] || $own['accessRights'] !== ['sendRaids' => true] || $own['banInfo'] !== null) {
    $fail('A plain player must be allowed to send raids.');
}
if (count($own['villages']) !== 1 || $own['villages'][0]['id'] !== $kid || !array_key_exists('hasHarbour', $own['villages'][0])) {
    $fail('The village list must hold the fixture village with its rally point and harbour flags.');
}
if ($own['abandonedFarmLists'] !== [] || count($own['farmLists']) !== 1 || $own['deactivatedFarmListTargets'] !== []) {
    $fail('Exactly the fixture list must be listed.');
}
$list = $own['farmLists'][0];
if ($list['id'] !== $listId || $list['sortIndex'] !== 3 || !$list['isExpanded'] || $list['slotsAmount'] !== 2 || $list['runningRaidsAmount'] !== 1) {
    $fail('List header fields are wrong: ' . json_encode($list));
}
if ($list['defaultTroop']['t1'] !== 5 || $list['ownerVillage']['id'] !== $kid || !isset($list['ownerVillage']['troops']['ownTroopsAtTown']['units']['t10'])) {
    $fail('The default troop and the home troops must be embedded.');
}
$slots = $list['slots'];
if ([$slots[0]['id'], $slots[1]['id']] !== [$oasisSlot, $villageSlot]) {
    $fail('Distance descending must put the oasis first.');
}
if ($slots[0]['target']['type'] !== FarmListData::TYPE_OASIS_FREE || $slots[0]['target']['population'] !== null || $slots[0]['isActive']) {
    $fail('The oasis slot must be an inactive free oasis without population.');
}
if (!in_array($slots[1]['target']['type'], [FarmListData::TYPE_VILLAGE, FarmListData::TYPE_CAPITAL], true) || $slots[1]['troop']['t1'] !== 7 || $slots[1]['runningAttacks'] !== 1 || !$slots[1]['isRunning'] || $slots[1]['isSpying'] || $slots[1]['nextAttackAt'] === null) {
    $fail('The village slot must be running with its troops: ' . json_encode($slots[1]));
}
if ($slots[0]['isRunning'] || $slots[0]['lastRaid'] !== null || $slots[0]['totalBooty'] !== ['booty' => 0, 'raids' => 0]) {
    $fail('The idle slot must have no raid state.');
}
if ($list['slotsStates'] !== [['id' => $oasisSlot, 'isActive' => false], ['id' => $villageSlot, 'isActive' => true]]) {
    $fail('slotsStates must follow the slot order.');
}
$pdo->run("UPDATE farmlist SET sortField='distance', sortDirection='asc' WHERE id=?", [$listId]);
if ((new FarmListData())->lists([$listId])[0]['slots'][0]['id'] !== $villageSlot) {
    $fail('Distance ascending must put the village first.');
}
if ((new FarmListData())->lists([$listId + 100000]) !== []) {
    $fail('Another player\'s list must not be returned.');
}

// A list of a village the player no longer owns is abandoned; a target that changes hands is deactivated and announced once.
$pdo->run('INSERT INTO farmlist (kid, owner, name, sortIndex) VALUES (?, ?, ?, 1)', [(int)$village['id'], $user['id'], 'Lost']);
$lostId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
register_shutdown_function(static fn () => $pdo->run('DELETE FROM farmlist WHERE id=?', [$lostId]));
$view = (new FarmListData())->viewData()['ownPlayer'];
if (count($view['abandonedFarmLists']) !== 1 || $view['abandonedFarmLists'][0]['id'] !== $lostId || isset($view['abandonedFarmLists'][0]['ownerVillage']['troops']) || count($view['farmLists']) !== 1) {
    $fail('A list of a village that is not the player\'s must be abandoned: ' . json_encode($view['abandonedFarmLists']));
}
FarmListInbox::targetChangedOwner((int)$village['id']);
$view = (new FarmListData())->viewData()['ownPlayer'];
$deactivated = $view['deactivatedFarmListTargets'];
$changedSlot = array_values(array_filter($view['farmLists'][0]['slots'], static fn (array $slot): bool => $slot['target']['id'] === (int)$village['id']))[0];
if (count($deactivated) !== 1 || $deactivated[0]['mapId'] !== (int)$village['id'] || $changedSlot['isActive']) {
    $fail('A target that changed hands must be deactivated and announced: ' . json_encode($deactivated));
}
FarmListInbox::targetChangedOwner((int)$village['id']);
if (count((new FarmListData())->viewData()['ownPlayer']['deactivatedFarmListTargets']) !== 1) {
    $fail('The same target must be announced once.');
}
FarmListInbox::close($user['id']);
if ((new FarmListData())->viewData()['ownPlayer']['deactivatedFarmListTargets'] !== []) {
    $fail('Closing the notice must clear it.');
}
echo "Farm list data runtime passed.\n";
