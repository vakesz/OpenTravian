<?php

declare(strict_types=1);

function assertHeroNavigationContract(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$navigation = file_get_contents($root . '/game/include/resources/Templates/hero/navigation.php');
$adventureController = file_get_contents($root . '/game/include/Controller/HeroAdventureCtrl.php');
$adventureModel = file_get_contents($root . '/game/include/Model/AdventureModel.php');
$auctionController = file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php');
$inventoryController = file_get_contents($root . '/game/include/Controller/HeroInventoryCtrl.php');
$faceController = file_get_contents($root . '/game/include/Controller/HeroFaceCtrl.php');
$router = file_get_contents($root . '/game/include/mainInclude.php');
$inventoryTemplate = file_get_contents($root . '/game/include/resources/Templates/hero/hero_inventory.php');
$topBar = file_get_contents($root . '/game/include/resources/Templates/layout/topBarBoxHero.php');
$adventureTemplate = file_get_contents($root . '/game/include/resources/Templates/hero/HeroAdventure.php');
$adventureStartedTemplate = file_get_contents($root . '/game/include/resources/Templates/hero/AdventureStarted.php');
$englishTranslations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

assertHeroNavigationContract(
    $navigation !== false
        && $adventureController !== false
        && $adventureModel !== false
        && $auctionController !== false
        && $inventoryController !== false
        && $faceController !== false
        && $router !== false
        && $inventoryTemplate !== false
        && $topBar !== false
        && $adventureTemplate !== false
        && $englishTranslations !== false,
    'Could not read the shared Hero navigation sources.'
);

assertHeroNavigationContract(
    str_contains($navigation, "1 => ['label' => T('HeroGlobal', 'Inventory'), 'url' => '/hero/inventory']")
        && str_contains($navigation, "2 => ['label' => T('HeroGlobal', 'Attributes'), 'url' => '/hero/attributes']")
        && str_contains($navigation, "3 => ['label' => T('HeroGlobal', 'Appear'), 'url' => '/hero/appearance']")
        && str_contains($navigation, '<a id="tabFavorButton" class="icon contentTitleButton buttonFramed green withIcon rectangle" title="')
        && !str_contains($navigation, 'favorIcon')
        && !str_contains($navigation, 'favorKey')
        && !str_contains($navigation, "'hero.php?t=3'"),
    'Shared Hero navigation must contain only the Inventory, Attributes, and Appearance tabs.'
);
assertHeroNavigationContract(
    str_contains($router, "\$requestPath = '/hero/inventory';")
        && str_contains($navigation, "replaceState(null, '', '/hero/inventory'")
        && !str_contains($router, '$opensAttributes')
        && !str_contains($router, "'/hero' => ['t' => '1']"),
    'Clean /hero must serve the inventory and rewrite the address, as the reference does, without a legacy attribute-panel query.'
);
assertHeroNavigationContract(
    str_contains($inventoryController, "'hero hero_editor'")
        && str_contains($inventoryController, "'hero hero_inventory'")
        && str_contains($inventoryController, "=== 'attributes'")
        && str_contains($inventoryController, "'heroV2 heroV2Inventory'")
        && str_contains($inventoryController, "'heroV2 heroV2Attributes'")
        && str_contains($faceController, "'hero hero_editor'")
        && str_contains($faceController, "'heroV2 heroV2Appearance'"),
    'Inventory uses hero_editor, Attributes uses hero_inventory, Appearance uses hero_editor.'
);
assertHeroNavigationContract(
    str_contains($faceController, "T('HeroInventory', 'level')")
        && !str_contains($faceController, 'T("Buildings", "level")'),
    'Hero Appearance must use the lower-case hero level heading.'
);
assertHeroNavigationContract(
    str_contains($topBar, 'id="heroImageButton" href="/hero"')
        && !str_contains($topBar, 'flagAttributesBoxOpen'),
    'The HUD hero portrait must use the reference /hero link without a legacy attribute-panel query.'
);
assertHeroNavigationContract(
    str_contains($inventoryTemplate, 'href="/auctions"')
        && str_contains($inventoryTemplate, 'class="tradeItems lightColor"')
        && str_contains($inventoryTemplate, 'class="greenInner"')
        && !str_contains($inventoryTemplate, 'tradeItemsArrow')
        && !str_contains($inventoryTemplate, '▶')
        && str_contains($topBar, 'href="/auctions"')
        && str_contains($topBar, '"redirectUrl":"/auctions"'),
    'Auction shortcuts must prefer the reference /auctions default without a Trade items ▶ prefix.'
);
$tabStrip = file_get_contents($root . '/game/include/resources/Templates/layout/tabStrip.php');
assertHeroNavigationContract(
    $tabStrip !== false
        && str_contains($navigation, "render('layout/tabStrip'")
        && str_contains($navigation, "'wrapperClass' => 'tabFavorWrapper'")
        && str_contains($navigation, "'reactState' => true")
        && str_contains($navigation, "'ids' => false")
        && str_contains($navigation, "'bareAnchors' => true")
        && str_contains($navigation, "window.location.href = routes[index]")
        && str_contains($tabStrip, 'class="scrollFrom"')
        && str_contains($tabStrip, 'class="scrollTo"')
        && str_contains($tabStrip, 'TabScrollNavigation'),
    'Hero navigation must render the shared tab strip (React state, no ids) with the Gpack scroll-control shell.'
);
assertHeroNavigationContract(
    str_contains($adventureController, "titleInHeader'] = T('HeroAdventure', 'Adventures', 'Adventures')")
        && str_contains($adventureController, "new PHPBatchView(\"hero/HeroAdventure\")")
        && str_contains($adventureController, "bodyCssClass'] = 'heroAdventure'")
        && str_contains($adventureController, "contentCssClass'] = 'heroAdventure heroAdventureAdventures'")
        && !str_contains($adventureController, "new PHPBatchView('hero/navigation')"),
    'Hero Adventure must be its own Adventures page rather than a stale fourth Hero-editor tab.'
);
assertHeroNavigationContract(
    str_contains($adventureTemplate, 'id="heroAdventure" class="contentV2"')
        && str_contains($adventureTemplate, 'class="borderGap adventureList"')
        && !str_contains($adventureTemplate, 'id="adventureListForm"')
        && !str_contains($adventureTemplate, 'class="scene"')
        && !str_contains($adventureTemplate, 'adventureStartedHtml')
        && str_contains($adventureTemplate, 'class="heroState"')
        && str_contains($adventureStartedTemplate, 'class="adventureStarted ')
        && str_contains($adventureStartedTemplate, '<button type="button" class="textButtonV2 buttonFramed continue rectangle withText green">')
        && str_contains($adventureStartedTemplate, "T('HeroAdventure', 'Continue', 'Continue')"),
    'Adventures markup must use #heroAdventure.contentV2, the list table, and the on-adventure Continue shell.'
);
assertHeroNavigationContract(
    // Adventures never expire, so every unfinished adventure counts, as on the Adventures page.
    str_contains($adventureModel, 'SELECT COUNT(id) FROM adventure WHERE uid=$uid AND end=0')
        && !str_contains($adventureModel, 'time>$now'),
    'The Hero HUD must count only adventures still available on the Adventures page.'
);
assertHeroNavigationContract(
    str_contains($auctionController, 'new PHPBatchView("hero/AuctionMenu")')
        && str_contains($auctionController, "['buy', 'sell', 'bids']")
        && str_contains($router, "#^/auctions/buy/(?P<type>[1-9][0-9]*)$#D")
        && str_contains($router, "setRouteParam('auctionType', \$auctionDetailMatch['type']);")
        && str_contains($auctionController, "'/auctions/buy/' . (int)\$row['type']")
        && !str_contains($auctionController, "['buy', 'sell', 'bids', 'accounting',]"),
    'Hero Auction must retain the reference tabs and the native item-detail clean route.'
);
assertHeroNavigationContract(
    str_contains($adventureTemplate, "T('HeroAdventure', 'location')")
        && str_contains($adventureTemplate, "T('map', 'Distance')")
        && str_contains($adventureTemplate, "T('HeroAdventure', 'moveTime')")
        && str_contains($adventureTemplate, "T('HeroAdventure', 'difficulty')")
        && str_contains($englishTranslations, "['location'] = 'Place';")
        && str_contains($englishTranslations, "['moveTime'] = 'Duration';")
        && str_contains($englishTranslations, "['difficulty'] = 'Danger';"),
    'The Adventures table must use the reference Place, Distance, Duration, and Danger terminology.'
);
assertHeroNavigationContract(
    str_contains($adventureController, 'Formulas::getDistance($this->session->hero->hero[\'kid\'], $row[\'kid\'])')
        && str_contains($adventureController, "T('map', 'fields')")
        && str_contains($adventureController, 'clock_medium')
        && str_contains($adventureController, "sprintf('%02d:%02d:%02d'")
        && str_contains($adventureController, '<div class="iconWrapper"><i class="difficulty_')
        && str_contains($adventureController, "default => ['grassland', 'Plains']")
        && str_contains($adventureController, '<button type="button" class="textButtonV2 buttonFramed rectangle withText green\'')
        && !str_contains($adventureController, '<a class="textButtonV2 buttonFramed rectangle withText green"')
        && str_contains($englishTranslations, "['gotoAdventure'] = 'Explore';")
        && str_contains($englishTranslations, "['grassland'] = 'Plains';"),
    'Adventure rows must use the native clock, padded duration, Plains label, and Explore button markup.'
);
assertHeroNavigationContract(
    str_contains($topBar, "adventureAttention = (int)(\$vars['adventureWhiteButton']['adventureCount'] ?? 0) > 0")
        && str_contains($topBar, "attention' : ''")
        && str_contains($topBar, 'href="/hero/adventures"'),
    'The adventure HUD badge must only add attention when available adventures remain.'
);

echo "Hero navigation contract passed.\n";
