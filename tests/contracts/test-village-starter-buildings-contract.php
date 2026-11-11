#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: registration seeds Main Building L1 (f26/f26t=15) and Rally
 * Point L1 (f39/f39t=16); a founded village seeds only Main Building L1.
 */

function failVillageStarterBuildingsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$register = file_get_contents($root . '/game/include/Model/RegisterModel.php');
if ($register === false) {
    failVillageStarterBuildingsContract('Unable to read RegisterModel.php.');
}

foreach ([
    "\$this->addResourceFields(\$kid, true);",
    "\$this->addResourceFields(\$kid, false);",
    "\$queryArr['f26'] = 1;",
    "\$queryArr['f26t'] = 15;",
    'if ($includeRallyPoint) {',
    "\$queryArr['f39'] = 1;",
    "\$queryArr['f39t'] = 16;",
] as $needle) {
    if (!str_contains($register, $needle)) {
        failVillageStarterBuildingsContract('RegisterModel missing starter-building seed: ' . $needle);
    }
}

if (!str_contains($register, 'function addResourceFields(int $kid, bool $includeRallyPoint)')) {
    failVillageStarterBuildingsContract('RegisterModel must make the Rally Point seed explicit per creation path.');
}

if (!str_contains($register, 'function createBaseVillage')
    || !str_contains($register, 'function createNewVillage')
    || !str_contains($register, 'function addResourceFields')) {
    failVillageStarterBuildingsContract('RegisterModel must keep createBaseVillage, createNewVillage, and addResourceFields.');
}

// Runtime: registration gets the starter pair; founding gets only Main Building L1.
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use Core\Database\DB;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Model\RegisterModel;

$db = DB::getInstance();
$m = new RegisterModel();

$kid1 = (int) $db->fetchScalar(
    'SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype=3 AND r>50 ORDER BY rand LIMIT 1'
);
$kid2 = (int) $db->fetchScalar(
    "SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype=3 AND r>50 AND kid<>$kid1 ORDER BY rand LIMIT 1"
);
if ($kid1 < 1 || $kid2 < 1) {
    failVillageStarterBuildingsContract('Need two free fieldtype-3 villages for the starter-building check.');
}

$name = 'rpseed_' . bin2hex(random_bytes(3));
$race = 1;
$uid = 0;
$createdKids = [$kid1, $kid2];

// The failure helper exits, which skips a finally block: clean up on shutdown instead.
register_shutdown_function(static function () use ($db, &$uid, $createdKids): void {
    foreach ($createdKids as $kid) {
        $db->query("DELETE FROM fdata WHERE kid=$kid");
        $db->query("DELETE FROM units WHERE kid=$kid");
        $db->query("DELETE FROM tdata WHERE kid=$kid");
        $db->query("DELETE FROM smithy WHERE kid=$kid");
        $db->query("DELETE FROM vdata WHERE kid=$kid");
        $db->query("UPDATE wdata SET occupied=0 WHERE id=$kid");
        $db->query("UPDATE available_villages SET occupied=0 WHERE kid=$kid");
    }
    if ($uid > 0) {
        $db->query("DELETE FROM adventure WHERE uid=$uid");
        $db->query("DELETE FROM inventory WHERE uid=$uid");
        $db->query("DELETE FROM face WHERE uid=$uid");
        $db->query("DELETE FROM hero WHERE uid=$uid");
        $db->query("DELETE FROM users WHERE id=$uid");
    }
});

$uid = (int) $m->addUser($name, sha1('contract'), $name . '@test.local', $race, $kid1, 1, true, false);
if ($uid < 1 || !$m->createBaseVillage($uid, $name, $race, $kid1)) {
    failVillageStarterBuildingsContract('createBaseVillage failed.');
}
if (!$m->createNewVillage($uid, $race, $kid2, $kid1)) {
    failVillageStarterBuildingsContract('createNewVillage failed.');
}
$registrationFields = $db->query("SELECT f26, f26t, f39, f39t FROM fdata WHERE kid=$kid1")->fetch_assoc();
if (!is_array($registrationFields)
    || (int) $registrationFields['f26'] !== 1
    || (int) $registrationFields['f26t'] !== 15
    || (int) $registrationFields['f39'] !== 1
    || (int) $registrationFields['f39t'] !== 16) {
    failVillageStarterBuildingsContract(
        "Registration village kid=$kid1 must seed Main Building L1 and Rally Point L1; got "
        . json_encode($registrationFields)
    );
}

$foundedFields = $db->query("SELECT f26, f26t, f39, f39t FROM fdata WHERE kid=$kid2")->fetch_assoc();
if (!is_array($foundedFields)
    || (int) $foundedFields['f26'] !== 1
    || (int) $foundedFields['f26t'] !== 15
    || (int) $foundedFields['f39'] !== 0
    || (int) $foundedFields['f39t'] !== 0) {
    failVillageStarterBuildingsContract(
        "Founded village kid=$kid2 must seed only Main Building L1; got "
        . json_encode($foundedFields)
    );
}

$initialCapacity = $db->query("SELECT maxstore, maxcrop FROM vdata WHERE kid=$kid2")->fetch_assoc();
$baseCapacity = (int) Formulas::storeCAP(0);
if (!is_array($initialCapacity)
    || (int) $initialCapacity['maxstore'] !== $baseCapacity
    || (int) $initialCapacity['maxcrop'] !== $baseCapacity) {
    failVillageStarterBuildingsContract(
        "Founded village kid=$kid2 must start with level-zero storage capacity; got "
        . json_encode($initialCapacity)
    );
}

$db->query("UPDATE fdata SET f19t=10, f20t=11 WHERE kid=$kid2");
for ($level = 1; $level <= 3; ++$level) {
    BuildingAction::upgrade($kid2, 19, 1, false, false);
    BuildingAction::upgrade($kid2, 20, 1, false, false);
}

$upgradedCapacity = $db->query("SELECT maxstore, maxcrop FROM vdata WHERE kid=$kid2")->fetch_assoc();
$levelThreeCapacity = (int) Formulas::storeCAP(3);
if (!is_array($upgradedCapacity)
    || (int) $upgradedCapacity['maxstore'] !== $levelThreeCapacity
    || (int) $upgradedCapacity['maxcrop'] !== $levelThreeCapacity) {
    failVillageStarterBuildingsContract(
        "Warehouse and Granary L3 in founded village kid=$kid2 must each store $levelThreeCapacity; got "
        . json_encode($upgradedCapacity)
    );
}

fwrite(STDOUT, "Village starter buildings contract passed.\n");
