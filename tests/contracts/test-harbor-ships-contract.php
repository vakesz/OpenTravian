#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: Harbor page and ship training.
 *
 * Ships queue as training rows (item_id 49, nr 101-104) and are credited to
 * units.ship1..ship4 without upkeep; a destroyed Harbor sinks the ships at home;
 * the Harbor level caps every ship owned, away and queued.
 */

use Core\Database\DB;
use Game\Ships;
use Model\TrainingModel;
use resources\View\PHPBatchView;

function assertShips(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$db = DB::getInstance();
// An oasis units row is a safe scratch village: nothing else reads its ships.
$kid = (int)$db->fetchScalar('SELECT kid FROM units WHERE race=4 ORDER BY kid LIMIT 1');
assertShips($kid > 0, 'The world needs an oasis units row for the scratch test.');
$db->query("UPDATE units SET ship1=0, ship2=0, ship3=0, ship4=0 WHERE kid=$kid");
$db->query("DELETE FROM training WHERE kid=$kid AND item_id=49");
$troopsBefore = (string)$db->fetchScalar("SELECT CONCAT_WS(',', u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) FROM units WHERE kid=$kid");
$movementBefore = (int)$db->fetchScalar("SELECT COUNT(id) FROM movement WHERE kid=$kid");
try {
    assertShips(Ships::buildRoom($kid, 1) === 1 && Ships::buildRoom($kid, 20) === 210 && Ships::buildRoom($kid, 0) === 0, 'An empty harbor has room for its level cap.');

    $model = new TrainingModel();
    // Due ten years from now in the engine's unit (up to nanoseconds): the running engine must never complete the row
    // between the insert and the assertions; the credit below is made by calling the handler directly.
    $model->addTraining($kid, Ships::HARBOR_GID, Ships::TRAINING_NR_OFFSET + Ships::RAID, 3, 315360000 * 1_000_000_000);
    assertShips(Ships::queuedShips($kid) === 3 && Ships::buildRoom($kid, 3) === 3, 'Queued ships count against the cap.');
    // The instant completion the Finish now button uses credits the whole queue as a finished batch.
    $db->begin_transaction();
    assertShips($model->completeAllTrainingInOpenTransaction($kid) === 1, 'The queued ship batch is the only row of the scratch village.');
    $db->commit();
    assertShips(Ships::homeShips($kid) === [1 => 0, 2 => 0, 3 => 0, 4 => 3], 'Finished ships are credited to units.ship4, not to a troop column.');
    assertShips(Ships::queuedShips($kid) === 0 && Ships::buildRoom($kid, 3) === 3, 'A finished ship moves from the queue to the harbor, the room stays the same.');
    assertShips((string)$db->fetchScalar("SELECT CONCAT_WS(',', u1, u2, u3, u4, u5, u6, u7, u8, u9, u10) FROM units WHERE kid=$kid") === $troopsBefore, 'Ships never touch the troop columns.');

    $db->query("UPDATE units SET ship2=2 WHERE kid=$kid");
    assertShips(Ships::buildRoom($kid, 3) === 1, 'Owned ships of every type count against the cap.');
    $db->query("INSERT INTO movement (kid, to_kid, race, mode, attack_type, start_time, end_time, ship) VALUES ($kid, $kid, 4, 0, 4, 1, 2, 4)");
    assertShips(Ships::awayShips($kid) === 1 && Ships::buildRoom($kid, 3) === 0, 'A ship out on a movement still counts against the cap.');
    assertShips(Ships::buildRoom($kid, 1) === 0, 'Lowering the Harbor level below the ships owned leaves no room, and never a negative one.');
} finally {
    $db->query("DELETE FROM movement WHERE kid=$kid AND ship>0 AND start_time=1 AND end_time=2");
    $db->query("DELETE FROM training WHERE kid=$kid AND item_id=49");
    $db->query("UPDATE units SET ship1=0, ship2=0, ship3=0, ship4=0 WHERE kid=$kid");
}
assertShips((int)$db->fetchScalar("SELECT COUNT(id) FROM movement WHERE kid=$kid") === $movementBefore, 'The scratch test left no movement behind.');

$html = PHPBatchView::render('build/building/Harbor', [
    'index' => 26, 'checker' => 'abc', 'level' => 5, 'maxShips' => 15, 'owned' => 4, 'away' => 1, 'queuedShips' => 2, 'room' => 9, 'tribe' => 9,
    'rows' => array_map(static fn (int $type): array => [
        'type' => $type, 'unitId' => 900 + $type, 'home' => $type, 'cost' => Ships::cost($type), 'duration' => '0:20:00', 'max' => 9, 'units' => Ships::unitCapacity($type),
    ], Ships::types()),
    'queue' => '<tr><td class="desc"><i class="unit_small tribe9 t904"></i> 2 x</td></tr>',
]);
foreach ([
    'name="t1"',
    'name="t4"',
    'unitSection tribe9 u904Section',
    'unitSection tribe9 u903Section',
    'unit_small tribe9 t904',
    'unit_small  t903',
    'name="destroy"',
    'class="destroyShips"',
    'class="shipDestroyAmount"',
] as $needle) {
    assertShips(str_contains($html, $needle) || str_contains(preg_replace('/\s+/', ' ', $html), str_replace('  ', ' ', $needle)), "The Harbor page must render $needle.");
}
assertShips(!str_contains($html, 'Warning') && !str_contains($html, 'Notice'), 'The Harbor page renders without PHP notices.');

foreach ([
    'game/include/Controller/BuildCtrl.php' => ['case 49:', 'new HarborCtrl', 'Ships::maxShips($lvl)'],
    'game/include/Game/Buildings/BuildingAction.php' => ['Ships::HARBOR_GID && $complete', 'ship1=0'],
    'game/include/Game/FarmList/FarmListSender.php' => ['Ships::boardRaid'],
    'game/include/Game/FarmList/FarmListStore.php' => ['Ships::hasHarbor', "'useShip'"],
    'game/include/Game/FarmList/FarmListData.php' => ["'hasHarbour'"],
    'game/deployment/public/farm-list.js' => ['useShip', 'hasHarbour'],
    'game/include/resources/View/GameView.php' => ["vars['isShore']"],
    'game/include/Controller/VillageCenterCtrl.php' => ['harborShipClasses', 'renderBehindContent', 'class="harbour'],
    'game/include/resources/Templates/village-center/main.php' => ['buildingShape g49', "(int)\$map['item_id'] === 49"],
    'game/include/resources/Templates/layout/Layout.php' => ['isShore', 'class="behindContent"', 'behindContent'],
    'game/include/Model/TrainingModel.php' => ['Ships::TRAINING_NR_OFFSET', 'Ships::column'],
] as $file => $needles) {
    $source = (string)file_get_contents($root . '/' . $file);
    foreach ($needles as $needle) {
        assertShips(str_contains($source, $needle), "$file must contain $needle.");
    }
}

echo "Harbor ships contract passed.\n";
