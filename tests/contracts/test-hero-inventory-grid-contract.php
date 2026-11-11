<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Model/AuctionModel.php';

use Model\AuctionModel;

function assertHeroInventoryGrid(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

assertHeroInventoryGrid(AuctionModel::HERO_INVENTORY_COLUMNS === 8, 'Hero inventory rows must contain eight slots.');
assertHeroInventoryGrid(AuctionModel::HERO_INVENTORY_MAX_ROWS === 6, 'Hero inventory must never grow beyond six rows.');
assertHeroInventoryGrid(AuctionModel::HERO_INVENTORY_MAX_SLOTS === 48, 'Hero inventory must expose at most 48 slots.');

foreach ([
    0 => 8,
    1 => 8,
    7 => 8,
    8 => 16,
    15 => 16,
    16 => 24,
    23 => 24,
    24 => 32,
    47 => 48,
    48 => 48,
    80 => 48,
] as $maxPlaceId => $expectedSlots) {
    assertHeroInventoryGrid(
        AuctionModel::visibleInventorySlotsForMaxPlaceId($maxPlaceId) === $expectedSlots,
        sprintf('Unexpected visible inventory size for occupied slot %d.', $maxPlaceId)
    );
}

$crypt = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');
foreach ([$crypt] as $inventoryClient) {
    assertHeroInventoryGrid(
        is_string($inventoryClient)
            && str_contains($inventoryClient, 'this.root.on("mousedown touchstart", ".heroItem"')
            && str_contains($inventoryClient, 'jQuery(window).on("mousemove touchmove", this.onMove)')
            && str_contains($inventoryClient, 'jQuery(window).on("mouseup touchend", this.onRelease)')
            && str_contains($inventoryClient, 'highlightDropTarget')
            && str_contains($inventoryClient, 'hero/v2/inventory/move-item'),
        'Inventory drag must bind the .heroItem tile (its .item child has pointer-events:none) and post move-item on release.'
    );
}

foreach ([$crypt] as $inventoryClient) {
    assertHeroInventoryGrid(
        is_string($inventoryClient)
            && str_contains($inventoryClient, 'cssClass: "basic resourceTransferDialog",' . "\n" . '          contentVersion: 2,')
            && str_contains($inventoryClient, '2 === this.options.contentVersion')
            && str_contains($inventoryClient, 'addClass("contentV2")'),
        'The resource transfer dialog must render #dialogContent as div.contentV2 so the Gpack progress-bar CSS applies.'
    );
}

echo "Hero inventory grid contract passed.\n";
