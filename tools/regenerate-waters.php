#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gives an already installed world its seas, rivers, large lakes and abandoned shores, or re-renders the ones it has.
 *
 * New worlds get their water from the installer (Game\Map\WaterGenerator, placed before the landscape). This tool
 * applies the same generator to an installed world. It never moves, deletes or overwrites a player: every village
 * (any vdata row or occupied tile), every owned oasis, the start area, the Wonder of the World sites and the
 * crop-seed sites stay as they are, and no village other than an existing harbor village becomes a shore village (the
 * eight tiles around every other village stay land). An existing harbor village stays a shore village: the water it
 * sits beside is kept and grows into a sea.
 *
 * Inside the new water it clears what a sea cannot carry: forest tiles, whole hills, clay pits and decorative lakes
 * (a sprite sheet is removed with all its tiles, found as the connected tiles of one sheet), free oases (their odata
 * and animal rows) and unclaimed village sites (their available_villages rows). With --replace the old deep water is
 * removed first; the old water tiles that stay dry become village sites again, as the installer would have made them.
 *
 * Usage: php tools/regenerate-waters.php [--replace] [--seed=<int>] [--apply --undo=<file>]
 *        php tools/regenerate-waters.php --revert=<file>
 * Without --apply it only reports what it would change. Take a database dump first (README, "Harbor and shore
 * tiles"). --undo records every row it changes or deletes so --revert can put them back; give it a path outside the
 * repository. After applying, the map caches are flushed (the same as tools/flush-map-cache.php).
 */

use Core\Caching\Caching;
use Core\Database\DB;
use Game\Formulas;
use Game\Map\WaterGenerator;
use Model\InstallerModel;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$usage = "Usage: php tools/regenerate-waters.php [--replace] [--seed=<int>] [--apply --undo=<file>]\n       php tools/regenerate-waters.php --revert=<file>\n";
$apply = false;
$replace = false;
$seed = null;
$undoPath = null;
$revertPath = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $apply = true;
    } elseif ($argument === '--replace') {
        $replace = true;
    } elseif (str_starts_with($argument, '--seed=') && ctype_digit(substr($argument, 7))) {
        $seed = (int)substr($argument, 7);
    } elseif (str_starts_with($argument, '--undo=')) {
        $undoPath = substr($argument, 7);
    } elseif (str_starts_with($argument, '--revert=')) {
        $revertPath = substr($argument, 9);
    } else {
        fwrite(STDERR, $usage);
        exit(2);
    }
}
if ($apply && $undoPath === null) {
    fwrite(STDERR, "--apply needs --undo=<file> (a path outside the repository).\n");
    exit(2);
}

$db = DB::getInstance();

/** @return array{x: int, y: int} */
function tileXY(int $kid): array
{
    $xy = Formulas::kid2xy($kid);

    return ['x' => (int)$xy['x'], 'y' => (int)$xy['y']];
}

function flushMapCaches(DB $db): void
{
    $db->query('DELETE b FROM blocks b JOIN map_block m ON m.id=b.map_id WHERE m.zoomLevel=3');
    $db->query('DELETE FROM map_block WHERE zoomLevel=3');
    $db->query('UPDATE map_block SET version=version+1');
    $db->query('UPDATE map_mark SET version=version+1');
    $cache = Caching::getInstance();
    foreach (['map_block_*', 'map_mark_*', 'minimap*'] as $pattern) {
        $cache->deleteByPattern($pattern);
    }
}

/** Writes a row set back: INSERT ... ON DUPLICATE KEY UPDATE for each recorded row. */
function restoreRows(DB $db, string $table, array $rows): void
{
    foreach ($rows as $row) {
        $columns = array_keys($row);
        $marks = implode(',', array_fill(0, count($columns), '?'));
        $update = implode(',', array_map(static fn (string $column): string => "`$column`=VALUES(`$column`)", $columns));
        $db->run("INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES ($marks) ON DUPLICATE KEY UPDATE $update", array_values($row));
    }
}

if ($revertPath !== null) {
    $undo = json_decode((string)@file_get_contents($revertPath), true);
    if (!is_array($undo) || !isset($undo['wdata'])) {
        fwrite(STDERR, "$revertPath is not an undo record of this tool.\n");
        exit(1);
    }
    $db->query('START TRANSACTION');
    foreach ($undo['insertedAvailable'] as $kid) {
        $db->run('DELETE FROM available_villages WHERE kid=?', [(int)$kid]);
    }
    restoreRows($db, 'wdata', $undo['wdata']);
    restoreRows($db, 'odata', $undo['odata']);
    restoreRows($db, 'units', $undo['units']);
    restoreRows($db, 'available_villages', $undo['availableVillages']);
    $db->query('COMMIT');
    flushMapCaches($db);
    printf("Reverted %d tiles, %d oases, %d village sites.\n", count($undo['wdata']), count($undo['odata']), count($undo['availableVillages']));
    exit(0);
}

// ---- the world as it is --------------------------------------------------------------------------------------
$tiles = [];
$result = $db->query('SELECT id, x, y, fieldtype, oasistype, landscape, crop_percent, occupied, deepwater, shore, map FROM wdata');
while ($row = $result->fetch_assoc()) {
    $tiles[(int)$row['id']] = $row;
}
$oldWater = array_keys(array_filter($tiles, static fn (array $tile): bool => (int)$tile['deepwater'] === 1));
if ($oldWater !== [] && !$replace) {
    fwrite(STDERR, "This world already has deep water; pass --replace to re-render it.\n");
    exit(1);
}

$generator = new WaterGenerator((int)MAP_SIZE, static fn () => false);
$blocked = WaterGenerator::reservedZone((int)MAP_SIZE);
$claimed = [];
foreach ($db->query('SELECT kid FROM vdata') as $village) {
    $claimed[(int)$village['kid']] = true;
}
foreach ($tiles as $id => $tile) {
    if ((int)$tile['occupied'] === 1) {
        $claimed[$id] = true;
    }
}
// Villages that are shore villages today keep their water: their own tile and the tiles that must stay land around any
// other village are blocked, theirs are not.
$anchors = [];
foreach (array_keys($claimed) as $kid) {
    if ((int)($tiles[$kid]['shore'] ?? 0) === 1 && (int)($tiles[$kid]['fieldtype'] ?? 0) > 0) {
        $anchors[$kid] = true;
    }
}
$landAround = [];
foreach (array_keys($claimed) as $kid) {
    $blocked[$kid] = true;
    if (isset($anchors[$kid])) {
        continue;
    }
    $xy = tileXY($kid);
    foreach ($generator->neighbours($xy['x'], $xy['y']) as [$nx, $ny]) {
        $blocked[Formulas::xy2kid($nx, $ny)] = true;
    }
}

// Owned oases stay; free ones are cleared where the water comes.
$owned = [];
foreach ($db->query('SELECT kid FROM odata WHERE owner>0 OR did>0') as $oasis) {
    $owned[(int)$oasis['kid']] = true;
    $blocked[(int)$oasis['kid']] = true;
}

// A hill, clay pit or decorative lake is one sprite sheet over several tiles: the connected tiles of one sheet.
$sheetOf = [];
$sheets = [];
$sheetImage = static function (array $tile): string {
    [$parent, $child] = array_pad(explode('=', (string)$tile['map']), 2, '');

    return (string)(explode('|', $child)[2] ?? '') !== '' ? (string)explode('|', $child)[2] : (string)(explode('|', $parent)[2] ?? '');
};
foreach ($tiles as $id => $tile) {
    if (!in_array((int)$tile['landscape'], [2, 3, 4], true) || isset($sheetOf[$id])) {
        continue;
    }
    $image = $sheetImage($tile);
    $group = [$id];
    $sheetOf[$id] = $id;
    $queue = [$id];
    while ($queue) {
        $current = array_pop($queue);
        $xy = tileXY($current);
        foreach ($generator->neighbours($xy['x'], $xy['y']) as [$nx, $ny]) {
            $next = Formulas::xy2kid($nx, $ny);
            if (!isset($sheetOf[$next]) && in_array((int)($tiles[$next]['landscape'] ?? 0), [2, 3, 4], true) && $sheetImage($tiles[$next]) === $image) {
                $sheetOf[$next] = $id;
                $group[] = $next;
                $queue[] = $next;
            }
        }
    }
    $sheets[$id] = $group;
}
// A sheet is only removed whole: if any of its tiles or the tiles around it must stay land, the sheet stays.
$sheetBlocked = [];
foreach ($sheets as $origin => $group) {
    foreach ($group as $kid) {
        if (isset($blocked[$kid])) {
            $sheetBlocked[$origin] = true;
            break;
        }
    }
}

$eligible = static function (int $x, int $y) use ($tiles, $blocked, $sheetOf, $sheetBlocked, $owned): bool {
    $kid = Formulas::xy2kid($x, $y);
    $tile = $tiles[$kid] ?? null;
    if ($tile === null || isset($blocked[$kid]) || (int)$tile['occupied'] === 1 || isset($owned[$kid])) {
        return false;
    }
    $landscape = (int)$tile['landscape'];
    if ($landscape === 5) {
        return false;
    }
    if (isset($sheetOf[$kid])) {
        return !isset($sheetBlocked[$sheetOf[$kid]]);
    }

    // Bare land, forest, a free oasis, a village site or the old water: a sea can take any of them.
    return true;
};
$generator = new WaterGenerator((int)MAP_SIZE, $eligible);

// The water an existing harbor village sits beside is kept (and grown into a sea).
$required = [];
foreach (array_keys($anchors) as $kid) {
    $xy = tileXY($kid);
    foreach ($generator->neighbours($xy['x'], $xy['y']) as [$nx, $ny]) {
        $neighbour = Formulas::xy2kid($nx, $ny);
        if ((int)($tiles[$neighbour]['deepwater'] ?? 0) === 1 && $eligible($nx, $ny)) {
            $required[] = [$nx, $ny];
            break;
        }
    }
}
$water = $generator->generate($seed, $required);

// Whole sheets: a sheet the water touches goes completely.
$waterKids = [];
foreach ($water as [$x, $y]) {
    $waterKids[Formulas::xy2kid($x, $y)] = true;
}
foreach (array_keys($waterKids) as $kid) {
    if (isset($sheetOf[$kid])) {
        foreach ($sheets[$sheetOf[$kid]] as $member) {
            $waterKids[$member] = true;
        }
    }
}
foreach (array_keys($waterKids) as $kid) {
    $xy = tileXY($kid);
    $water[$xy['x'] . '|' . $xy['y']] = [$xy['x'], $xy['y']];
}
$shore = [];
foreach ($generator->shoreOf($water) as [$x, $y]) {
    $kid = Formulas::xy2kid($x, $y);
    $tile = $tiles[$kid] ?? null;
    if ($tile !== null && !isset($waterKids[$kid]) && (int)$tile['landscape'] === 0 && (int)$tile['oasistype'] === 0 && ((int)$tile['fieldtype'] > 0 || isset($claimed[$kid]))) {
        $shore[$kid] = true;
    }
}

// ---- what changes ----------------------------------------------------------------------------------------
$cleared = ['forest' => 0, 'sheets' => 0, 'sheetTiles' => 0, 'freeOases' => 0, 'villageSites' => 0, 'oldWater' => 0];
$oasisTypes = [];
$freedOases = [];
$removedSites = [];
foreach (array_keys($waterKids) as $kid) {
    $tile = $tiles[$kid];
    if ((int)$tile['deepwater'] === 1) {
        ++$cleared['oldWater'];
    } elseif ((int)$tile['landscape'] === 1) {
        ++$cleared['forest'];
    } elseif (in_array((int)$tile['landscape'], [2, 3, 4], true)) {
        ++$cleared['sheetTiles'];
    } elseif ((int)$tile['oasistype'] > 0) {
        ++$cleared['freeOases'];
        $freedOases[] = $kid;
        $oasisTypes[(int)$tile['oasistype']] = ($oasisTypes[(int)$tile['oasistype']] ?? 0) + 1;
    } elseif ((int)$tile['fieldtype'] > 0) {
        ++$cleared['villageSites'];
        $removedSites[] = $kid;
    }
}
$cleared['sheets'] = count(array_unique(array_map(static fn (int $kid): int => $sheetOf[$kid], array_filter(array_keys($waterKids), static fn (int $kid): bool => isset($sheetOf[$kid])))));
$dried = array_values(array_filter($oldWater, static fn (int $kid): bool => !isset($waterKids[$kid])));
$cells = (2 * (int)MAP_SIZE + 1) ** 2;
$bodies = $generator->bodySizes($water);
$shoreSites = count(array_filter(array_keys($shore), static fn (int $kid): bool => !isset($claimed[$kid])));
$shoreVillages = array_values(array_filter(array_keys($shore), static fn (int $kid): bool => isset($claimed[$kid])));
$lostShore = array_values(array_filter(array_keys($anchors), static fn (int $kid): bool => !isset($shore[$kid]) && (int)$tiles[$kid]['shore'] === 1));
$near = [];
foreach (array_keys($claimed) as $kid) {
    $xy = tileXY($kid);
    foreach ($generator->neighbours($xy['x'], $xy['y']) as [$nx, $ny]) {
        if (isset($waterKids[Formulas::xy2kid($nx, $ny)])) {
            $near[$kid] = true;
        }
    }
}
printf("Old deep water: %d tiles. New deep water: %d tiles (%.1f%% of %d), %d bodies, the largest %s.\n", count($oldWater), count($waterKids), count($waterKids) * 100 / $cells, $cells, count($bodies), implode(', ', array_slice($bodies, 0, 6)));
printf("Cleared under the new water: %d forest tiles, %d hills/clay pits/lakes (%d sheet tiles), %d free oases%s, %d unclaimed village sites, %d old water tiles.\n", $cleared['forest'], $cleared['sheets'], $cleared['sheetTiles'], $cleared['freeOases'], $oasisTypes === [] ? '' : ' (types ' . implode(', ', array_map(static fn (int $type, int $count): string => "$type: $count", array_keys($oasisTypes), $oasisTypes)) . ')', $cleared['villageSites'], $cleared['oldWater']);
printf("Old water that dries up: %d tiles, restored as village sites.\n", count($dried));
printf("Shore village sites: %d unclaimed. Claimed villages on a shore afterwards: %d (%s); a harbor village that lost its shore: %d.\n", $shoreSites, count($shoreVillages), implode(', ', array_map(static fn (int $kid): string => (string)$kid, $shoreVillages)), count($lostShore));
printf("Claimed villages: %d, none moved or deleted; villages with water within two tiles: %d; owned oases kept: %d.\n", count($claimed), count($near), count($owned));
if (!$apply) {
    echo "Dry run; pass --apply --undo=<file> to write.\n";
    exit(0);
}

// ---- apply -----------------------------------------------------------------------------------------------
$installer = new InstallerModel();
$undo = ['wdata' => [], 'odata' => [], 'units' => [], 'availableVillages' => [], 'insertedAvailable' => []];
$changed = array_unique(array_merge(array_keys($waterKids), $oldWater, array_keys($shore), array_keys(array_filter($tiles, static fn (array $tile): bool => (int)$tile['shore'] === 1))));
foreach (array_chunk($changed, 500) as $chunk) {
    $list = implode(',', $chunk);
    foreach ($db->query("SELECT id, x, y, fieldtype, oasistype, landscape, crop_percent, occupied, deepwater, shore, map FROM wdata WHERE id IN ($list)") as $row) {
        $undo['wdata'][] = $row;
    }
}
if ($freedOases !== []) {
    $list = implode(',', $freedOases);
    foreach ($db->query("SELECT * FROM odata WHERE kid IN ($list)") as $row) {
        $undo['odata'][] = $row;
    }
    foreach ($db->query("SELECT * FROM units WHERE kid IN ($list)") as $row) {
        $undo['units'][] = $row;
    }
}
$listAll = implode(',', array_keys($waterKids));
foreach ($db->query("SELECT kid, fieldtype, r, angle, rand, occupied FROM available_villages WHERE kid IN ($listAll)") as $row) {
    $undo['availableVillages'][] = $row;
}
if (file_put_contents($undoPath, json_encode($undo, JSON_PRETTY_PRINT)) === false) {
    fwrite(STDERR, "Could not write $undoPath.\n");
    exit(1);
}

$db->query('START TRANSACTION');
$db->query('UPDATE wdata SET shore=0 WHERE shore=1');
if ($freedOases !== []) {
    $list = implode(',', $freedOases);
    $db->query("DELETE FROM odata WHERE kid IN ($list) AND owner=0 AND did=0");
    $db->query("DELETE FROM units WHERE kid IN ($list) AND race=4");
}
$db->query("DELETE FROM available_villages WHERE kid IN ($listAll)");
$db->query("UPDATE wdata SET deepwater=1, fieldtype=0, oasistype=0, landscape=0, crop_percent=0, shore=0, map='||=||' WHERE id IN ($listAll) AND occupied=0");
// Old water that stays dry becomes village sites again, with the installer's field type draw.
foreach ($dried as $kid) {
    $fieldtype = 0;
    $installer->getFieldType($kid, $fieldtype);
    $xy = tileXY($kid);
    $db->run("UPDATE wdata SET deepwater=0, fieldtype=?, shore=0 WHERE id=?", [$fieldtype, $kid]);
    $db->run('INSERT INTO available_villages (kid, fieldtype, r, angle, rand) VALUES (?, ?, ?, ?, ?)', [$kid, $fieldtype, hypot($xy['x'], $xy['y']), Formulas::getAngleByXY($xy['x'], $xy['y']), mt_rand(10000000, 99999999)]);
    $undo['insertedAvailable'][] = $kid;
}
if ($shore !== []) {
    $db->query('UPDATE wdata SET shore=1 WHERE id IN (' . implode(',', array_keys($shore)) . ') AND deepwater=0');
}
$db->query('COMMIT');
// The undo record also names the village sites this run inserted, so --revert can remove them again.
file_put_contents($undoPath, json_encode($undo, JSON_PRETTY_PRINT));
flushMapCaches($db);
echo "Applied. Undo record: $undoPath\n";
