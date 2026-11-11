<?php

declare(strict_types=1);

/**
 * Contract: cancelling a building order refunds the cost of the level before the one
 * that order was queued for (Village::cancelRefund), not the currently completed level.
 *
 * This uses a capital resource field at level 20 on an x11 fixture. Two
 * queued upgrades simultaneously reach the local high-speed resource cost
 * branch and prove both the first and a follower refund their own charge.
 *
 * All fixture changes are contained in a database transaction and rolled back.
 */

function failBuildingCancelRefundContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function resourcesForBuildingCancelRefund(\Core\Database\DBI $db, int $kid): array
{
    $row = $db->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid]);
    if ($row === false) {
        throw new RuntimeException('Fixture village resources could not be read.');
    }

    return [
        (int)$row['wood'],
        (int)$row['clay'],
        (int)$row['iron'],
        (int)$row['crop'],
    ];
}

function resourceDifferenceForBuildingCancelRefund(array $before, array $after): array
{
    return array_map(
        static fn(int $index): int => $before[$index] - $after[$index],
        array_keys($before)
    );
}

$root = dirname(__DIR__, 2);
$villageSource = file_get_contents($root . '/game/include/Core/Village.php');
if ($villageSource === false || !str_contains($villageSource, 'public function removeBuilding($id)')) {
    failBuildingCancelRefundContract('Unable to read Village cancellation path.');
}

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Building cancellation refund contract passed (behavioural part skipped outside the php container).\n";
    exit(0);
}

$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Config;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;

require_once dirname(__DIR__) . '/support/village-fixture.php';

try {
    $db = DBI::getInstance();
    $player = fixtureVillageCreate();
} catch (Throwable $exception) {
    echo "Building cancellation refund contract passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}

$kid = (int)$player['kid'];
$config = Config::getInstance();
$originalSpeed = $config->game->speed;
register_shutdown_function(static function () use ($config, $originalSpeed): void {
    $config->game->speed = $originalSpeed;
});
try {
    $config->game->speed = 11;
    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = false;
    // The test is about the refund, not the state of the round.
    $config->dynamic->serverFinished = 0;
    $now = miliseconds();
    $db->run('UPDATE users SET plus=?, last_login_time=?, last_owner_login_time=? WHERE id=?', [time() + 3600, time(), time(), $player['id']]);
    $db->run(
        'UPDATE vdata
         SET wood=100000000, clay=100000000, iron=100000000, crop=100000000,
             woodp=0, clayp=0, ironp=0, cropp=pop+10, upkeep=0,
             maxstore=200000000, maxcrop=200000000, lastmupdate=?, capital=1
         WHERE kid=?',
        [$now, $kid]
    );
    $db->run('UPDATE fdata SET f1=20, f1t=1 WHERE kid=?', [$kid]);
    $db->run('DELETE FROM demolition WHERE kid=?', [$kid]);
    $db->run('DELETE FROM building_upgrade WHERE kid=?', [$kid]);

    $_SESSION[WebService::fixSessionPrefix('uid')] = (int)$player['id'];
    $_SESSION[WebService::fixSessionPrefix('pw')] = $player['password'];
    Session::getInstance();
    $village = Village::getInstance();

    $expectedFirstCost = array_map(static fn($cost): int => (int)$cost, Formulas::buildingUpgradeCosts(1, 21));
    $expectedFollowerCost = array_map(static fn($cost): int => (int)$cost, Formulas::buildingUpgradeCosts(1, 22));
    $expectedCharge = array_map(
        static fn(int $index): int => $expectedFirstCost[$index] + $expectedFollowerCost[$index],
        array_keys($expectedFirstCost)
    );
    $beforeQueue = resourcesForBuildingCancelRefund($db, $kid);
    $village->upgradeBuilding(1, false);
    $village->upgradeBuilding(1, false);
    $afterQueue = resourcesForBuildingCancelRefund($db, $kid);
    $charged = resourceDifferenceForBuildingCancelRefund($beforeQueue, $afterQueue);
    if ($charged !== $expectedCharge) {
        throw new RuntimeException('Queue charge must equal the combined level-21 and level-22 costs; got ' . json_encode($charged) . ' instead of ' . json_encode($expectedCharge) . '.');
    }

    $tasks = $db->run('SELECT id FROM building_upgrade WHERE kid=? AND building_field=1 AND isMaster=0 ORDER BY commence ASC, id ASC', [$kid])->fetchAll(PDO::FETCH_COLUMN);
    if (count($tasks) !== 2) {
        throw new RuntimeException('The level-21 and level-22 resource-field fixture orders were not persisted.');
    }

    $village->removeBuilding((int)$tasks[1]);
    $afterFollowerCancel = resourcesForBuildingCancelRefund($db, $kid);
    $followerRefund = array_map(
        static fn(int $resource): int => -$resource,
        resourceDifferenceForBuildingCancelRefund($afterQueue, $afterFollowerCancel)
    );
    $expectedFollowerRefund = Village::cancelRefund(1, 22)['amounts'];
    if ($followerRefund !== $expectedFollowerRefund) {
        throw new RuntimeException('Cancelled level-22 follower must refund the level-21 cost; got ' . json_encode($followerRefund) . ' instead of ' . json_encode($expectedFollowerRefund) . '.');
    }

    $village->removeBuilding((int)$tasks[0]);
    $afterFirstCancel = resourcesForBuildingCancelRefund($db, $kid);
    $firstRefund = array_map(
        static fn(int $resource): int => -$resource,
        resourceDifferenceForBuildingCancelRefund($afterFollowerCancel, $afterFirstCancel)
    );
    $expectedFirstRefund = Village::cancelRefund(1, 21)['amounts'];
    if ($firstRefund !== $expectedFirstRefund) {
        throw new RuntimeException('Cancelled level-21 first order must refund the level-20 cost; got ' . json_encode($firstRefund) . ' instead of ' . json_encode($expectedFirstRefund) . '.');
    }

    echo "Building cancellation refund contract passed (x11 level-21/22 orders refund the cost of the level before each).\n";
} catch (Throwable $exception) {
    failBuildingCancelRefundContract('Building cancellation refund behaviour failed: ' . $exception->getMessage());
}
