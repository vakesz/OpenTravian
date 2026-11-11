<?php

declare(strict_types=1);

function failTippyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failTippyContract("Cannot read {$path}.");
    }
    return $source;
};

$head = $read('game/include/resources/Templates/layout/head.php');
$village = $read('game/include/Core/Village.php');
$en = $read('game/include/resources/Translation/en-US.php');

// Self-hosted reference script set, in the reference load order.
$order = [
    'js/default/jquery-',
    'js/default/d3/d3.js',
    'js/default/d3/d3pie.js',
    'js/default/ChartJs/Chart.js',
    'js/default/simplebar.js',
    'js/default/popper.js',
    'js/default/tippy.js',
    'js/default/PixiJS/pixi.js',
    'get_crypt_js_link()',
];
$last = -1;
foreach ($order as $needle) {
    $offset = strpos($head, $needle);
    if ($offset === false || $offset < $last) {
        failTippyContract("head.php must load {$needle} in reference order.");
    }
    $last = $offset;
}
if (preg_match('#src="(?:https?:)?//#', $head) === 1) {
    failTippyContract('head.php must not hotlink remote scripts.');
}
foreach (['simplebar.js', 'PixiJS/pixi.js', 'popper.js', 'tippy.js'] as $lib) {
    if (!is_file($root . '/game/deployment/public/js/default/' . $lib)) {
        failTippyContract("Missing self-hosted library {$lib}.");
    }
}
// Local shell policy removes the generic Knowledge Base opener from every
// dialog. Contextual building/troop manual popups remain separate.
foreach ([
    "'ButtonV1' => \"<button >",
    '<div class="dialogCancelButton smallIconButton cancel"></div>',
    '<div class="dialogCancelButton buttonFramed green withIcon rectangle cancel" title="Close">',
] as $fragment) {
    if (!str_contains($head, $fragment)) {
        failTippyContract("head.php dialog templates are missing {$fragment}");
    }
}
foreach (['smallIconButton info', 'rectangle info', 'title="Knowledge Base"'] as $forbidden) {
    if (str_contains($head, $forbidden)) {
        failTippyContract("head.php dialog templates must not expose generic Knowledge Base chrome: {$forbidden}");
    }
}
foreach (['game-client.js'] as $name) {
    $client = $read('game/deployment/public/' . $name);
    foreach (['tip-container', 'elementTitle', '_travianTooltip', 'Travian.Tip.show(', 'Travian.Tip.load('] as $legacy) {
        if (str_contains($client, $legacy)) {
            failTippyContract("{$name} still contains the legacy tooltip path: {$legacy}");
        }
    }
    foreach ([
        'PLACEMENT_DEFAULT: "bottom-start"',
        'theme: "travian"',
        'allowHTML: !0',
        'offset: [16, 16]',
        'e._tooltipName = e.reference.dataset.loadTooltip',
        'updateContentOfInstance: function',
        'clearOnElement: function',
        'Travian.api.get = function',
        'Travian.isNumeric = ',
    ] as $needle) {
        if ($needle === 'Travian.isNumeric = ') {
            $needle = 'isNumeric: function';
        }
        if (!str_contains($client, $needle)) {
            failTippyContract("{$name} must contain {$needle}.");
        }
    }
    if (str_contains($client, 'jQuery.isNumeric(')) {
        failTippyContract("{$name} must not use jQuery.isNumeric (removed in jQuery 4).");
    }
}

// Ajax tooltips are declared with data attributes, not mouseover scripts.
foreach ([
    'game/include/functions.general.php',
    'game/include/resources/Templates/layout/topBarBoxHero.php',
    'game/include/resources/Templates/layout/sidebarBoxVillageList.php',
] as $file) {
    if (str_contains($read($file), 'Tip.load')) {
        failTippyContract("{$file} must use data-load-tooltip instead of Travian.Tip.load.");
    }
}
foreach (['data-load-tooltip="incomingTroops"'] as $needle) {
    if (!str_contains($read('game/include/resources/View/GameView.php'), $needle)) {
        failTippyContract("GameView.php must emit {$needle}.");
    }
}

// Building / resource-field hover tooltip matches the reference DOM and copy.
foreach ([
    "' <span class=\"level\">'",
    'renderUpgradeCostTooltip',
    "'Big\"></i>'",
    'class="inlineIcon duration" title=""><i class="clock_medium"></i>',
    'Formulas::buildingUpgradeTime($gid, $nextLevel',
] as $needle) {
    if (!str_contains($village, $needle)) {
        failTippyContract("Village.php building tooltip is missing {$needle}.");
    }
}
if (!str_contains($en, "= 'Cost for upgrading building to level %s:';")) {
    failTippyContract('en-US must use the reference upgrade-cost copy.');
}
if (!str_contains($en, "['Alliance overview'] = 'Alliance Page';")) {
    failTippyContract('The alliance sidebar tooltip title must read "Alliance Page".');
}

// Lazy tooltips: free-silver (header + auction filter) and distance, with server handlers and routes.
$routes = (require $root . '/game/deployment/include/api_legacy_routes.php')['routes'] ?? [];
foreach (['tooltip/free-silver' => 'free-silver', 'tooltip/distance' => 'distance', 'tooltip/incomingTroops' => 'incoming-troops'] as $endpoint => $command) {
    if (($routes[$endpoint] ?? null) !== ['tooltip', $command]) {
        failTippyContract("Missing legacy API route {$endpoint}.");
    }
}
$freeSilver = $read('game/include/Controller/Ajax/tooltip/freeSilver.php');
$distance = $read('game/include/Controller/Ajax/tooltip/distance.php');
foreach (["'Free silver'", 'silverBoundInAuctions', '&#x202d;&#x202d;', 'getInBidSilver'] as $needle) {
    if (!str_contains($freeSilver, $needle)) {
        failTippyContract("freeSilver tooltip handler is missing {$needle}.");
    }
}
foreach (['kid2xy', 'getDistance', "number_format(\$distance, 2, '.', '')", "\$this->response['title'] = null"] as $needle) {
    if (!str_contains($distance, $needle)) {
        failTippyContract("distance tooltip handler is missing {$needle}.");
    }
}
if (!str_contains($en, "['distanceTooltip'] = 'Distance: <strong>%s</strong>';")
    || !str_contains($en, "['silverBoundInAuctions'] = 'Currently %s of your silver is bound in active auctions';")) {
    failTippyContract('en-US must carry the reference free-silver and distance tooltip copy.');
}
if (!str_contains($read('game/include/resources/Templates/layout/Layout.php'), 'ajaxReplaceableSilverAmount" data-load-tooltip="free-silver"')) {
    failTippyContract('The header silver amount must lazy-load the free-silver tooltip.');
}
foreach (['game-client.js'] as $name) {
    if (!str_contains($read('game/deployment/public/' . $name), 'Travian.Tip.resetAjaxTipsByType("free-silver")')) {
        failTippyContract("{$name} must reset the free-silver tooltip when the silver amount changes.");
    }
}

// Quick links: reference type keys, attributes and the tooltip/quickLink handler.
$quickLink = $read('game/include/Game/QuickLink.php');
$quickHandler = $read('game/include/Controller/Ajax/tooltip/quickLink.php');
$list = $read('game/include/resources/Templates/layout/sidebarBoxVillageList.php');
foreach (['tooltip/quickLink' => 'quick-link'] as $endpoint => $command) {
    if (($routes[$endpoint] ?? null) !== ['tooltip', $command]) {
        failTippyContract("Missing maintained tooltip API route {$endpoint}.");
    }
}
foreach ([
    'BlacksmithManagement', 'RallyPointOverview', 'RallyPointSendTroops', 'RallyPointFarmList',
    'MarketplaceSendResources', 'MarketplaceTradeRoutes', 'MarketplaceBuy', 'BarracksManagement',
    'GreatBarracksManagement', 'StableManagement', 'GreatStableManagement', 'WorkshopManagement',
    'TownHallCelebration', 'TrapperManagement', 'HerosMansionManagement', 'HospitalManagement', 'ExpansionTrain',
] as $type) {
    if (!str_contains($read('game/include/Core/Helper/PreferencesHelper.php'), "'{$type}'")) {
        failTippyContract("Quick-link preference whitelist must use the reference type {$type}.");
    }
}
foreach (["'&tt=99'", "'&t=5'", "'&t=3'", "'&s=1'", "'/build.php?gid='"] as $needle) {
    if (!str_contains($read('game/include/resources/View/GameView.php'), $needle)) {
        failTippyContract("Quick-link hrefs must use the reference gid/suffix scheme: {$needle}");
    }
}
foreach (["data-load-tooltip'] = 'quickLink'", 'data-load-tooltip-data', "'villageQuickLinks' + slotIndex"] as $needle) {
    if (!str_contains($list, $needle)) {
        failTippyContract("Quick-link buttons must carry the reference attributes: {$needle}");
    }
}
if (!str_contains($quickHandler, 'isKnownType') || !str_contains($quickLink, 'noTroopInBeingTrained')
    || !str_contains($quickLink, 'availableMerchants')) {
    failTippyContract('The quickLink tooltip handler is incomplete.');
}
if (!str_contains($en, "['toMarketplaceSendResources'] = 'To the Marketplace - Send Resources';")
    || !str_contains($en, "['noTroopInBeingTrained'] = 'No troops in training.';")) {
    failTippyContract('en-US must carry the reference quick-link copy.');
}

// Every page, including the admin panel, uses the self-hosted jQuery 4.
if (!str_contains($read('game/include/admin/include/templates/layout.php'), '/js/default/jquery-4.0.0.js')
    || is_file($root . '/game/deployment/public/js/default/jquery-3.5.1.min.js')) {
    failTippyContract('The admin panel must load jquery-4.0.0 and jquery-3.5.1 must be deleted.');
}

// Legacy .tip CSS (nine-slice) is gone from every stylesheet.
foreach (['css_ltr', 'css_rtl'] as $dir) {
    foreach (glob($root . "/assets/gpack/travian/mainPage/{$dir}/*.css") ?: [] as $css) {
        $source = file_get_contents($css);
        if ($source !== false && preg_match('/(^|[\s,>])\.tip(\s|\.|,|\{)(?!.*level)/m', preg_replace('/body\.village[12] \.tip \.level/', '', $source) ?? '') === 1) {
            failTippyContract(basename($css) . " still carries legacy .tip rules ({$dir}).");
        }
    }
}

echo "Tooltip tippy contract passed.\n";
