<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Model/HeroInventoryModel.php';

use Model\HeroInventoryModel;

function assertLegacyEquipmentValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

/** @param callable(): void $callback */
function assertLegacyEquipmentRejected(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$inventory = [
    'helmet' => 101,
    'body' => 102,
    'leftHand' => 103,
    'rightHand' => 104,
    'shoes' => 105,
    'horse' => 106,
];
$equipment = $inventory;
$items = [
    101 => ['id' => 101, 'btype' => 1, 'proc' => 1],
    102 => ['id' => 102, 'btype' => 2, 'proc' => 1],
    103 => ['id' => 103, 'btype' => 3, 'proc' => 1],
    104 => ['id' => 104, 'btype' => 4, 'proc' => 1],
    105 => ['id' => 105, 'btype' => 5, 'proc' => 1],
    106 => ['id' => 106, 'btype' => 6, 'proc' => 1],
];

assertLegacyEquipmentValue(
    [],
    HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $equipment, $items),
    'A matching owned equipped snapshot must remain unchanged.'
);

$removeHelmet = $equipment;
$removeHelmet['helmet'] = 0;
assertLegacyEquipmentValue(
    ['helmet' => 101],
    HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $removeHelmet, $items),
    'A zero equipment ID must retain the legitimate unequip operation.'
);

$foreignHelmet = $equipment;
$foreignHelmet['helmet'] = 999;
assertLegacyEquipmentRejected(
    static fn (): array => HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $foreignHelmet, $items),
    'A missing item row must reject a cross-account equipment ID.'
);

$wrongSlotItems = $items;
$wrongSlotItems[101]['btype'] = 2;
assertLegacyEquipmentRejected(
    static fn (): array => HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $equipment, $wrongSlotItems),
    'An item with the wrong equipment type must be rejected.'
);

$unequippedItems = $items;
$unequippedItems[101]['proc'] = 0;
assertLegacyEquipmentRejected(
    static fn (): array => HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $equipment, $unequippedItems),
    'An unequipped item must not be accepted by the legacy form.'
);

$differentOwnedItem = $equipment;
$differentOwnedItem['helmet'] = 107;
$differentOwnedItems = $items;
$differentOwnedItems[107] = ['id' => 107, 'btype' => 1, 'proc' => 1];
assertLegacyEquipmentRejected(
    static fn (): array => HeroInventoryModel::legacyEquipmentSlotsToRemove($inventory, $differentOwnedItem, $differentOwnedItems),
    'The legacy form must not equip an arbitrary owned item outside the inventory API.'
);

echo "Hero legacy equipment contract passed.\n";
