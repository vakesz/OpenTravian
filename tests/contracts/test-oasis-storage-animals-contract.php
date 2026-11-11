<?php

declare(strict_types=1);

/**
 * Contract: an oasis holds 1000 (times the storage multiplier) with a 25% bonus and 2000 from 50% total bonus up
 * (the 25% + 25% crop types count as 50%), derived from the effect table; every oasis type has one nature animal
 * list in OasesModel::ANIMALS_BY_TYPE with valid unit ids, and crop oases carry the Crocodile, types 3 and 15 the Elephant.
 */

use Core\Config;
use Game\Formulas;
use Model\OasesModel;

function failOasisStorageAnimalsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require_once dirname(__DIR__, 2) . '/game/include/bootstrap.php';

Formulas::load();
$multiplier = (float) Config::getProperty('game', 'storage_multiplier');
$expected = [2 => 1, 6 => 1, 10 => 1, 14 => 1, 3 => 2, 4 => 2, 7 => 2, 8 => 2, 11 => 2, 12 => 2, 15 => 2];
foreach ($expected as $type => $factor) {
    $got = (float) Formulas::getOasisStorage($type);
    if (abs($got - 1000 * $multiplier * $factor) > 0.001) {
        failOasisStorageAnimalsContract("Oasis type {$type} storage is {$got}, expected " . (1000 * $multiplier * $factor) . '.');
    }
}

$animals = OasesModel::ANIMALS_BY_TYPE;
if (array_keys($animals) !== array_keys($expected) && array_diff(array_keys($animals), array_keys($expected)) !== []) {
    failOasisStorageAnimalsContract('ANIMALS_BY_TYPE must cover exactly the oasis types of the effect table.');
}
foreach ($expected as $type => $_) {
    if (empty($animals[$type])) {
        failOasisStorageAnimalsContract("Oasis type {$type} has no animals.");
    }
    foreach ($animals[$type] as $unit) {
        if ($unit < 1 || $unit > 10) {
            failOasisStorageAnimalsContract("Oasis type {$type} lists nature unit {$unit}.");
        }
    }
}
foreach ([14, 15] as $type) {
    if (!in_array(8, $animals[$type], true)) {
        failOasisStorageAnimalsContract("Crop oasis {$type} must carry the Crocodile.");
    }
}
foreach ($animals as $type => $list) {
    if (in_array(10, $list, true) !== in_array($type, [3, 15], true)) {
        failOasisStorageAnimalsContract("Only types 3 and 15 carry the Elephant (type {$type}).");
    }
}

echo "Oasis storage and animals contract passed.\n";
