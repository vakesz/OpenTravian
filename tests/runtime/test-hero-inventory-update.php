<?php

require dirname(__DIR__, 2) . '/game/include/Controller/HeroInventoryUpdate.php';

use Controller\HeroInventoryUpdate;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$empty = HeroInventoryUpdate::fromPost([]);
assertSameValue(null, $empty['inventory'], 'A GET must not update the inventory.');
assertSameValue(null, $empty['face'], 'A GET must not update the face.');

$inventory = [
    'horse' => '0',
    'helmet' => '2',
    'body' => '3',
    'leftHand' => '4',
    'rightHand' => '5',
    'shoes' => '6',
];
$expectedInventory = array_map('intval', $inventory);
assertSameValue($expectedInventory, HeroInventoryUpdate::fromPost($inventory)['inventory'], 'A complete inventory payload must be accepted.');

$incompleteInventory = $inventory;
unset($incompleteInventory['shoes']);
assertSameValue(null, HeroInventoryUpdate::fromPost($incompleteInventory)['inventory'], 'An incomplete inventory payload must be ignored.');

$invalidInventory = $inventory;
$invalidInventory['horse'] = '';
assertSameValue(null, HeroInventoryUpdate::fromPost($invalidInventory)['inventory'], 'Blank inventory values must be ignored.');

$face = [
    'beard' => '1',
    'headProfile' => '2',
    'hairColor' => '3',
    'hairStyle' => '4',
    'ears' => '5',
    'eyebrow' => '6',
    'eyes' => '7',
    'nose' => '8',
    'mouth' => '9',
    'gender' => 'female',
];
$expectedFace = $face;
foreach ($expectedFace as $field => $value) {
    if ($field !== 'gender') {
        $expectedFace[$field] = (int)$value;
    }
}
assertSameValue($expectedFace, HeroInventoryUpdate::fromPost($face)['face'], 'A complete face payload must be accepted.');

$invalidFace = $face;
$invalidFace['gender'] = 'unknown';
assertSameValue(null, HeroInventoryUpdate::fromPost($invalidFace)['face'], 'Unknown genders must be ignored.');

echo "HeroInventoryUpdate checks passed.\n";
