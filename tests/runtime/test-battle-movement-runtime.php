<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Core\Automation;
use Core\Database\DB;
use Core\Database\DBI;
use Model\MovementsModel;

require_once dirname(__DIR__) . '/support/village-fixture.php';

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});

// The sending village is a throwaway fixture player; the only real rows read are the target oasis or Natar village,
// which are put back as they were when the test ends. Movements are claimed a day ahead and processed by id, so the
// live engine and unrelated due movements are never touched.
$db = DB::getInstance();
$pdo = DBI::getInstance();
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Battle movement runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo->run('UPDATE wdata SET occupied=1 WHERE id=?', [$fixture['kid']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('UPDATE wdata SET occupied=0 WHERE id=?', [$fixture['kid']]));
$origin = ['kid' => $fixture['kid'], 'owner' => $fixture['id'], 'race' => (int)$pdo->fetchScalar('SELECT race FROM users WHERE id=?', [$fixture['id']])];
$options = getopt('', ['natar', 'normal', 'full-storage', 'current-stock', 'target:']);
$natar = isset($options['natar']);
$normal = isset($options['normal']);
$fullStorage = isset($options['full-storage']);
$currentStock = isset($options['current-stock']);
$targetFilter = isset($options['target']) ? ' AND kid=' . (int)$options['target'] : '';
$target = $natar
    ? $db->fetchScalar("SELECT kid FROM vdata WHERE owner=1 AND isWW=0 AND isFarm=0 $targetFilter ORDER BY pop,kid LIMIT 1")
    : $db->fetchScalar("SELECT kid FROM odata WHERE owner=0 $targetFilter ORDER BY kid LIMIT 1");
if (!$origin || !$target) {
    throw new RuntimeException('This local runtime check requires the selected oasis/Natar target type.');
}

$kid = (int)$origin['kid'];
$uid = (int)$origin['owner'];
$target = (int)$target;
$targetTable = $natar ? 'vdata' : 'odata';
// Snapshots of every real row the test writes, put back at shutdown.
$savedTarget = $pdo->fetchSingleRow("SELECT wood, clay, iron, crop, lastmupdate FROM $targetTable WHERE kid=?", [$target]);
$savedUnits = $pdo->fetchSingleRow('SELECT u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, u11 FROM units WHERE kid=?', [$target]);
$savedArtifacts = (int)$pdo->fetchScalar('SELECT ArtifactsReleased FROM config LIMIT 1');
register_shutdown_function(static function () use ($target, $targetTable, $savedTarget, $savedUnits, $savedArtifacts): void {
    $pdo = DBI::getInstance();
    $pdo->run("UPDATE $targetTable SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? WHERE kid=?", [...array_values($savedTarget), $target]);
    $pdo->run('UPDATE units SET u1=?, u2=?, u3=?, u4=?, u5=?, u6=?, u7=?, u8=?, u9=?, u10=?, u11=? WHERE kid=?', [...array_values($savedUnits), $target]);
    $pdo->run('UPDATE config SET ArtifactsReleased=?', [$savedArtifacts]);
});
$claimed = static fn (int $id): bool => (new Automation())->processMovementTask($id);

{
    $db->query('UPDATE config SET ArtifactsReleased=1');
    $db->query("UPDATE units SET u1=0,u2=0,u3=0,u4=0,u5=0,u6=0,u7=0,u8=0,u9=0,u10=0,u11=0 WHERE kid=$target");
    $stock = $currentStock ? '' : 'wood=100000,clay=100000,iron=100000,crop=100000,';
    $db->query("UPDATE $targetTable SET {$stock}lastmupdate=" . miliseconds() . " WHERE kid=$target");
    $db->query("UPDATE vdata SET wood=0,clay=0,iron=0,crop=0,lastmupdate=" . miliseconds() . " WHERE kid=$kid");
    $beforeReport = (int)$db->fetchScalar("SELECT COALESCE(MAX(id),0) FROM ndata WHERE uid=$uid");
    $beforeMovement = (int)$db->fetchScalar('SELECT COALESCE(MAX(id),0) FROM movement');
    $units = array_fill(1, 11, 0);
    $units[1] = 10000;
    $movement = new MovementsModel();
    $attackType = $normal ? MovementsModel::ATTACKTYPE_NORMAL : MovementsModel::ATTACKTYPE_RAID;
    $arrival = (int)miliseconds() + 86400000;
    $id = (int)$movement->addMovement($kid, $target, (int)$origin['race'], $units, 0, 0, 0, 0, 0, $attackType, $arrival - 60000, $arrival);
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id AND mode=0 AND end_time <= " . miliseconds())) {
        throw new RuntimeException('FAIL: the movement counts as arrived before its arrival time.');
    }
    $claimed($id);
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$id")) {
        throw new RuntimeException('FAIL: the arriving attack was not consumed.');
    }
    $report = $db->query("SELECT id,type,data,bounty FROM ndata WHERE uid=$uid AND id>$beforeReport AND to_kid=$target AND deleted=0 ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if (!$report) {
        throw new RuntimeException('FAIL: troops arrived but no visible attacker report was created.');
    }
    $stored = Game\NoticeHelper::parseReport((int)$report['type'], $report['data']);
    if (!is_array($stored['strength'] ?? null) || (int)$stored['strength']['attacker'] <= 0 || !array_key_exists('defender', $stored['strength'])) {
        throw new RuntimeException('FAIL: the battle report must store the attacker and defender combat strength.');
    }
    $return = $db->query("SELECT * FROM movement WHERE id>$beforeMovement AND kid=$target AND to_kid=$kid AND mode=1 ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if (!$return || (!$currentStock && array_sum(array_slice(explode(',', (string)$return['data']), 0, 4)) <= 0)) {
        throw new RuntimeException('FAIL: attack produced no returning loot from a stocked target.');
    }
    $loot = array_map('intval', array_slice(explode(',', (string)$return['data']), 0, 4));
    if ($loot !== array_map('intval', array_slice(explode(',', $report['bounty']), 0, 4))) {
        throw new RuntimeException('FAIL: returning loot differs from the battle report.');
    }
    $returnId = (int)$return['id'];
    $db->query("UPDATE movement SET end_time=" . (miliseconds() + 60000) . " WHERE id=$returnId");
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$returnId AND mode=1 AND end_time <= " . miliseconds())) {
        throw new RuntimeException('FAIL: troops count as returned before their arrival time.');
    }
    $fill = $fullStorage ? 'wood=maxstore,clay=maxstore,iron=maxstore,crop=maxcrop' : 'wood=0,clay=0,iron=0,crop=0';
    $db->query("UPDATE vdata SET $fill,woodp=0,clayp=0,ironp=0,cropp=pop+upkeep,lastmupdate=" . miliseconds() . " WHERE kid=$kid");
    $beforeUnits = $db->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc();
    $claimed($returnId);
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM movement WHERE id=$returnId")) {
        throw new RuntimeException('FAIL: due troop return was not consumed.');
    }
    $afterUnits = $db->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc();
    for ($i = 1; $i <= 11; ++$i) {
        if ((int)$afterUnits["u$i"] !== (int)$beforeUnits["u$i"] + (int)$return["u$i"]) {
            throw new RuntimeException("FAIL: returning unit $i was not restored exactly once.");
        }
    }
    $resources = $db->query("SELECT wood,clay,iron,crop,maxstore,maxcrop FROM vdata WHERE kid=$kid")->fetch_assoc();
    foreach (['wood', 'clay', 'iron', 'crop'] as $index => $resource) {
        $capacity = (float)$resources[$index === 3 ? 'maxcrop' : 'maxstore'];
        $expected = $fullStorage ? $capacity : min($capacity, $loot[$index]);
        if (abs((float)$resources[$resource] - $expected) > 0.01) {
            throw new RuntimeException("FAIL: $resource return deposit did not match loot and storage capacity.");
        }
    }
    echo ($normal ? 'Attack' : 'Raid') . ' scheduling, returning loot, and '
        . ($fullStorage ? 'full' : 'empty') . ' storage passed (loot ' . array_sum($loot) . ").\n";
}
