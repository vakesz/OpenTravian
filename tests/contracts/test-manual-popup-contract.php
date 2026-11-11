<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$manual = file_get_contents($root . '/game/include/Game/Manual/ManualContent.php');
$controller = file_get_contents($root . '/game/include/Controller/Ajax/manual/troop.php');
$overviewController = file_get_contents($root . '/game/include/Controller/Ajax/manual/overview.php');
$buildingController = file_get_contents($root . '/game/include/Controller/Ajax/manual/building.php');
$ajaxController = file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');
$overview = file_get_contents($root . '/game/include/resources/Templates/manual/overview.php');
$unitTemplate = file_get_contents($root . '/game/include/resources/Templates/manual/unit.php');
$buildingTemplate = file_get_contents($root . '/game/include/resources/Templates/manual/building.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');

if ($manual === false
    || $controller === false
    || $overviewController === false
    || $buildingController === false
    || $ajaxController === false
    || $overview === false
    || $unitTemplate === false
    || $buildingTemplate === false
    || $routes === false
    || !str_contains($manual, 'function renderUnit(int $unitId): string')
    || !str_contains($manual, 'function renderOverview(): string')
    || !str_contains($manual, "PHPBatchView::render('manual/overview')")
    || !str_contains($manual, "PHPBatchView('manual/unit')")
    || !str_contains($manual, 'function unitPrerequisites(array $unit, int $unitNumber, int $tribe): string')
    || !str_contains($controller, 'final class troop extends AjaxBase')
    || !str_contains($controller, 'renderUnit($unitId)')
    || !is_file($root . '/game/include/Controller/Ajax/manual/overview.php')
    || !str_contains($routes, "'pattern' => '#^manual/0/0$#D'")
    || !str_contains($overview, 'class="instantTabs"')
    || !str_contains($overview, 'class="tabContainer')
    || !str_contains($overview, "Travian.Game.Manual.open('troop'")
    || !str_contains($overview, "Travian.Game.Manual.open('building'")
    || !str_contains($overview, '\\Game\\Tribes::isPlayable($tribe)')
    || !str_contains($overview, '[4, 5]')
    || !str_contains($unitTemplate, 'onclick="Travian.Game.Manual.open()"')
    || !str_contains($buildingTemplate, 'onclick="Travian.Game.Manual.open()"')
    || !str_contains($routes, "'target' => ['manual', 'troop']")) {
    fwrite(STDERR, "Unit manual controls must resolve through the maintained manual dialog API.\n");
    exit(1);
}

if (!str_contains($ajaxController, '$rawHtml = !empty($response[\'_rawHtml\']);')
    || !str_contains($ajaxController, 'if ($rawHtml && isset($response[\'html\'])')) {
    fwrite(STDERR, "Ajax response handling must default to JSON HTML while retaining its explicit raw-HTML opt-out.\n");
    exit(1);
}

foreach ([$manual, $overview, $unitTemplate, $buildingTemplate, $routes] as $source) {
    if (str_contains($source, 'manual.php')) {
        fwrite(STDERR, "The player-facing manual must not retain the retired full-page fallback.\n");
        exit(1);
    }
}

foreach ([$manual, $overview, $unitTemplate, $buildingTemplate] as $source) {
    if (str_contains($source, 'data-manual-') || str_contains($source, 'href="#"')) {
        fwrite(STDERR, "Manual controls must use the inline callbacks instead of local data attributes.\n");
        exit(1);
    }
}

if (str_contains($routes, "'pattern' => '#^manual/overview/0$#D'")) {
    fwrite(STDERR, "The retired local-only manual overview route must not remain alongside the manual/0/0 endpoint.\n");
    exit(1);
}

if (!str_contains($overview, "T('RallyPoint', 'troops')")
    || !str_contains($overview, "T('inGame', 'Navigation.Buildings')")
    || str_contains($overview, 'uunits"> Troops</h3>')
    || str_contains($overview, 'building_small"></i> Buildings</h3>')) {
    fwrite(STDERR, "Manual overview category headings must use the existing canonical locale keys.\n");
    exit(1);
}

foreach (['game-client.js'] as $clientName) {
    $client = file_get_contents($root . '/game/deployment/public/' . $clientName);
    if ($client === false
        || !str_contains($client, 'iPopup: function (e, t)')
        || !str_contains($client, 'Travian.Game.Manual.open("troop", e)')
        || !str_contains($client, 'Travian.Game.Manual.open("building", e)')
        || !str_contains($client, 'Travian.Game.Manual.open()')
        || !str_contains($client, 'a = "manual/" + e + "/" + t;')
        || !str_contains($client, 'Travian.api.get(')
        || str_contains($client, 'Manual.open("unit"')
        || !str_contains($client, 'initializeContent: function ()')
        || !str_contains($client, 'new Travian.Game.InstantTabs(jQuery(this))')
        || !str_contains($client, 'n.initializeContent()')
        || str_contains($client, 'Travian.Game.Manual.open("overview")')) {
        fwrite(STDERR, "{$clientName} must keep every manual info control actionable.\n");
        exit(1);
    }
}

echo "Manual popup contract passed.\n";
