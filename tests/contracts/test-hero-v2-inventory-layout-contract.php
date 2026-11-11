<?php

declare(strict_types=1);

function failHeroV2InventoryLayoutContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/hero/hero_inventory.php');
$css = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($template === false || $css === false) {
    failHeroV2InventoryLayoutContract('Hero V2 inventory sources must be readable.');
}

foreach ([
    "'helmet' => ['FilterHelmets', 1, -1]",
    "'body' => ['FilterBodyArmor', 2, -4]",
    "'shoes' => ['FilterShoes', 3, -5]",
    "'leftHand' => ['FilterLeftHandItems', 4, -2]",
    "'rightHand' => ['FilterRightHandItems', 5, -3]",
    "'horse' => ['FilterHorses', 6, -7]",
    "'bag' => ['FilterConsumables', 7, -6]",
] as $requiredFragment) {
    if (!str_contains($template, $requiredFragment)) {
        failHeroV2InventoryLayoutContract("Hero V2 equipment slot mapping is missing {$requiredFragment}.");
    }
}

if (!str_contains($template, 'class="heroBodyImage heroBodyImage-')
    || str_contains($template, 'id="hero_body_container"')
    || str_contains($template, 'id="hero_body"')) {
    failHeroV2InventoryLayoutContract('Hero V2 inventory must use the native hero-stage markup and image selector.');
}

if (!str_contains($template, "var inventoryPage = document.querySelector('.inventoryPageWrapper')")
    || !str_contains($template, "inventoryPage.addEventListener('click'")
    || str_contains($template, "bodyOptions.addEventListener('click'")) {
    failHeroV2InventoryLayoutContract('Hero V2 inventory activation must cover both equipment and bag items.');
}

foreach (['#bodyOptions', '#hero_inventory', '#itemsToSale'] as $legacyId) {
    if (str_contains($css, $legacyId) || str_contains($template, 'id="' . ltrim($legacyId, '#') . '"')) {
        failHeroV2InventoryLayoutContract("Hero V2 inventory must not use the legacy {$legacyId} id; the reference DOM has no ids.");
    }
}

foreach ([
    '<div class="filterSlot <?=$filter; ?>',
    'data-key="<?=$filter; ?>" title="<?=htmlspecialchars((string)T(\'Auction\', $filterTitles[$filter]), ENT_QUOTES, \'UTF-8\'); ?>"><i></i></div>',
    '<div class="heroItem empty neutral heroItemV1"',
] as $requiredFragment) {
    if (!str_contains($template, $requiredFragment)) {
        failHeroV2InventoryLayoutContract("Hero V2 inventory is missing the reference markup {$requiredFragment}.");
    }
}
if (str_contains($template, '<button type="button"
                                    class="filterSlot')) {
    failHeroV2InventoryLayoutContract('Hero inventory filter slots must be divs as in the reference.');
}

foreach (['game-client.js'] as $bundle) {
    $js = file_get_contents($root . '/game/deployment/public/' . $bundle);
    if ($js === false
        || !str_contains($js, 'Inventory.prototype.syncFigure')
        || !str_contains($js, 'this.syncFigure();')
        || !str_contains($js, 'self.syncImages(response.hero);')
        || !str_contains($js, '#topBarHero .heroImage')
        || !str_contains($js, 'figure.addClass("horse horse" + horse.typeId)')) {
        failHeroV2InventoryLayoutContract("{$bundle} must swap the hero figure horse overlay after every equip, unequip and refresh.");
    }
}

echo "Hero V2 inventory layout contract passed.\n";
