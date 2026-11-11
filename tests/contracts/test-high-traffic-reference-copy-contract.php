#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: active messages, marketplace, hero/auction and alliance copy
 * keeps the English route bundles and rendered routes. The rendered
 * route is authoritative where a bundle contains an unused or stale key.
 *
 * Bundles covered:
 * - js/en-US/nachrichten.json
 * - js/en-US/gid17.json
 * - js/en-US/hero.json (supplemented
 *   by the rendered /hero/attributes route for the active hero labels)
 * - js/en-US/allianz.json
 */

function failHighTrafficReferenceCopyContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$messagesMenu = file_get_contents($root . '/game/include/resources/Templates/messages/menu.php');
$messageController = file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
$messageDetail = file_get_contents($root . '/game/include/resources/Templates/messages/showMsg.php');
$marketPrepare = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/prepare.php');
$marketSend = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceSendResources.php');
$marketRoutes = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceTradeRouteShow.php');
$marketBuy = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceBuy.php');
$marketController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
$heroInventory = file_get_contents($root . '/game/include/resources/Templates/hero/hero_inventory.php');
$auctionMenu = file_get_contents($root . '/game/include/resources/Templates/hero/AuctionMenu.php');
$allianceAttacks = file_get_contents($root . '/game/include/resources/Templates/alliance/attacks.php');

if (in_array(false, [
    $translation,
    $messagesMenu,
    $messageController,
    $messageDetail,
    $marketPrepare,
    $marketSend,
    $marketRoutes,
    $marketBuy,
    $marketController,
    $heroInventory,
    $auctionMenu,
    $allianceAttacks,
], true)) {
    failHighTrafficReferenceCopyContract('Unable to read the high-traffic reference-copy sources.');
}

foreach ([
    "\$Definition['Messages']['Read'] = 'read';",
    "\$Definition['Messages']['Unread'] = 'unread';",
    "\$Definition['Messages']['back to send box'] = 'back to the sent folder';",
    "\$Definition['Messages']['no subject'] = 'no subject';",
    "\$Definition['Messages']['- saved -'] = '- saved -';",
    "\$Definition['Messages']['online now'] = 'now online';",
    "\$Definition['Messages']['active players'] = 'max. 24 h';",
    "\$Definition['MarketPlace'][\"noVillageWithName\"] = 'A village with the name [NAME] has not been found.';",
    "\$Definition['MarketPlace'][\"enterVillageNameOrCoordinate\"] = 'Enter village name or coordinates, please.';",
    "\$Definition['MarketPlace'][\"invalidCoordinates\"] = 'Invalid coordinates';",
    "\$Definition['MarketPlace'][\"Offered to me\"] = 'Offered';",
    "\$Definition['MarketPlace'][\"Wanted from me\"] = 'Wanted';",
    "\$Definition['MarketPlace'][\"noOffers\"] = 'Currently no offers available';",
    "\$Definition['MarketPlace']['noRoute'] = 'No active trade routes.';",
    "\$Definition['MarketPlace']['tradeRouteDesc'] = 'Trade routes allow you to send resources on a regular basis to your villages, to Wonder of the World villages and artefact villages within your alliance/confederacy.';",
    '"save" => "Save changes",',
    "\$Definition['HeroGlobal']['HeroProduction'] = 'Hero production';",
    "\$Definition['Alliance']['Don´t show attacks of own alliance (under 100 units, no losses)'] = 'Don´t show attacks of own alliance (under 100 units, no losses).';",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failHighTrafficReferenceCopyContract('Missing high-traffic copy: ' . $expected);
    }
}

foreach ([
    "T('Messages', \$row['viewed'] == 1 ? 'Read' : 'Unread')",
    "T('Messages', 'back to send box')",
] as $expected) {
    if (!str_contains($messageController . $messageDetail, $expected)) {
        failHighTrafficReferenceCopyContract('Messages no longer renders the covered copy: ' . $expected);
    }
}
if (str_contains($messagesMenu, "'infoIcon' => '/help.php'")) {
    failHighTrafficReferenceCopyContract('The message Gold Club dialog must not reintroduce generic help chrome.');
}

foreach ([
    "str_replace(\n                    '[NAME]',",
    "T('MarketPlace', 'noVillageWithName')",
    "\$hasCoordinateInput",
    "T('MarketPlace', 'enterVillageNameOrCoordinate')",
    "T('MarketPlace', 'invalidCoordinates')",
] as $expected) {
    if (!str_contains($marketPrepare, $expected)) {
        failHighTrafficReferenceCopyContract('Marketplace target validation is missing copy behavior: ' . $expected);
    }
}
if (!str_contains($marketSend, 'validation.text(response.errorMessage);')) {
    failHighTrafficReferenceCopyContract('Marketplace target errors must remain inserted as text, not HTML.');
}
if (substr_count($marketSend, "T('MarketPlace', 'enterCoordinatesOrVillageName')") !== 2) {
    failHighTrafficReferenceCopyContract('Marketplace client validation must use the target prompt for both coordinates.');
}
if (!str_contains($marketRoutes, "T('MarketPlace', 'noRoute')")
    || !str_contains($marketController, 'T("MarketPlace", "noOffers")')
    || !str_contains($marketBuy, "T(\"MarketPlace\", 'Offered to me')")
    || !str_contains($marketBuy, "T(\"MarketPlace\", 'Wanted from me')")) {
    failHighTrafficReferenceCopyContract('Marketplace empty states no longer render their covered copy keys.');
}

if (!str_contains($heroInventory, "T('HeroInventory', 'save')")
    || !str_contains($heroInventory, "T('HeroGlobal', 'HeroProduction')")) {
    failHighTrafficReferenceCopyContract('Hero inventory no longer renders its covered copy keys.');
}
if (!str_contains($auctionMenu, "'bids' => ['label' => T('Auction', 'overview'), 'path' => '/auctions/overview']")) {
    failHighTrafficReferenceCopyContract('Auction navigation must render the Auction overview label.');
}
if (!str_contains($allianceAttacks, 'T("Alliance", "Don´t show attacks of own alliance (under 100 units, no losses)")')
    || str_contains($allianceAttacks, "ENT_QUOTES, 'UTF-8') ?>.\"")) {
    failHighTrafficReferenceCopyContract('Alliance attacks must render the canonical filter title with one terminal period.');
}

echo "High-traffic reference copy contract passed.\n";
