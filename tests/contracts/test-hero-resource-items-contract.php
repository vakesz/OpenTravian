<?php

declare(strict_types=1);

/**
 * Contract: carried hero resources sync as inventory item145–148 tiles
 * (shape), not a separate Collect-resources stash strip.
 */

function failHeroResourceItemsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$inventory = file_get_contents($root . '/game/include/Model/HeroResourceInventory.php');
$model = file_get_contents($root . '/game/include/Model/HeroInventoryModel.php');
$ctrl = file_get_contents($root . '/game/include/Controller/HeroInventoryCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/hero/hero_inventory.php');
$heroItems = file_get_contents($root . '/game/include/Game/Hero/HeroItems.php');

foreach ([$inventory, $model, $ctrl, $template, $heroItems] as $source) {
    if ($source === false) {
        failHeroResourceItemsContract('Unable to read hero resource inventory sources.');
    }
}

if (!str_contains($inventory, 'ITEM_BTYPE = 16')
    || !str_contains($inventory, '145')
    || !str_contains($inventory, 'function syncInventoryItems')) {
    failHeroResourceItemsContract('HeroResourceInventory must define btype 16 item145–148 sync.');
}

if (!str_contains($model, 'case 16:')
    || !str_contains($model, 'HeroResourceInventory::ITEM_TYPES')) {
    failHeroResourceItemsContract('HeroInventoryModel must use item145–148 stacks into the village.');
}

if (!str_contains($ctrl, 'syncInventoryItems')) {
    failHeroResourceItemsContract('HeroInventoryCtrl must sync resource tiles before rendering.');
}

if (str_contains($template, 'heroResourceStash')
    || str_contains($template, 'claimHeroResources')
    || str_contains($template, 'Collect resources')) {
    failHeroResourceItemsContract('Remove the legacy heroResourceStash Collect-resources strip from the inventory template.');
}

$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
if ($fixes !== false && str_contains($fixes, '.heroResourceStash')) {
    failHeroResourceItemsContract('Remove obsolete .heroResourceStash CSS from overrides.css.');
}

if ($fixes === false || !str_contains($fixes, '#content.heroV2Inventory .inventoryWrapper .heroItemV1 > .item') || !str_contains($fixes, 'pointer-events: auto')) {
    failHeroResourceItemsContract('Hero inventory items must remain pointer-interactive for native use and drag handlers.');
}

if (!str_contains($heroItems, 'btype === 16')
    || !str_contains($heroItems, '145')
    || !str_contains($heroItems, "['attributes'] = [")) {
    failHeroResourceItemsContract('HeroItems must describe btype 16 resource tiles with attributes as a string array for Inventory JS.');
}

foreach ([145 => 'resource-lumber', 146 => 'resource-clay', 147 => 'resource-iron', 148 => 'resource-crop'] as $type => $icon) {
    $png = $root . '/assets/gpack/travian/mainPage/img_ltr/views/hero-v2/inventory/items/' . $icon . '.png';
    if (!is_readable($png)) {
        failHeroResourceItemsContract('Missing Gpack inventory icon: ' . $icon . '.png');
    }
}

echo "Hero resource inventory items contract passed.\n";
