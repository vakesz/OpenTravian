<?php

declare(strict_types=1);

function failHeroInventoryBagSlotContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

foreach (['game-client.js'] as $bundle) {
    $js = file_get_contents($root . '/game/deployment/public/' . $bundle);
    if ($js === false) {
        failHeroInventoryBagSlotContract("Unable to read {$bundle}.");
    }

    if (!str_contains($js, 'tile.attr("data-slot", consumable ? "inventory" : item.slot)')) {
        failHeroInventoryBagSlotContract("{$bundle} must set data-slot=inventory for consumables in the bag grid.");
    }

    if (!str_contains($js, 'tile.closest(".heroItems").length')) {
        failHeroInventoryBagSlotContract("{$bundle} must clear data-slot when emptying bag grid tiles.");
    }

    if (!str_contains($js, 'tiles are `.heroItem[data-id][data-placeid][data-slot][data-refkey]`')) {
        failHeroInventoryBagSlotContract("{$bundle} hero inventory contract comment must document data-slot on tiles.");
    }
}

echo "Hero inventory bag slot contract passed.\n";
