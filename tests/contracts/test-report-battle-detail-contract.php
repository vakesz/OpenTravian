<?php

declare(strict_types=1);

/**
 * Contract: battle report details use the reference's inline SVG role/outcome icons with titles, the
 * troopCount_small/troopDead_small tables, the shared information table and the Statistics block; the
 * report list filters by `villageId`/`playerId` through the local "Player or village" autocomplete.
 */
function failReportBattleDetailContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failReportBattleDetailContract("Cannot read $path.");
    }

    return $source;
};
$controller = $read('game/include/Controller/ReportsCtrl.php');
$troopsTable = $read('game/include/resources/Templates/reports/TroopsTable.php');
$filterTemplate = $read('game/include/resources/Templates/reports/All.php');
$model = $read('game/include/Model/ReportsModel.php');
$routes = $read('game/deployment/include/api_legacy_routes.php');
$endpoint = $read('game/include/Controller/Ajax/autocomplete/villageorplayer.php');

foreach (['troopCount_small', 'troopDead_small', 'additionalInformation', 'combatStatistic', 'inlineIcon carry', "'lumber', 'clay', 'iron', 'crop'"] as $needle) {
    if (!str_contains($controller, $needle)) {
        failReportBattleDetailContract("Battle report markup must contain $needle.");
    }
}
foreach (['class="troopCount"', 'class="troopDead"', "' none'", "'none'", 'class="attacker"', 'lootClass'] as $legacy) {
    if (str_contains($controller, $legacy) || str_contains($troopsTable, $legacy)) {
        failReportBattleDetailContract("Battle report must not keep the legacy markup $legacy.");
    }
}
if (str_contains($troopsTable, '<img') && str_contains($troopsTable, 'img/svg/combat')) {
    failReportBattleDetailContract('Role and outcome icons must be inline SVG, not <img> files.');
}
if (!str_contains($troopsTable, 'CombatIcon::svg')) {
    failReportBattleDetailContract('The role icon must come from the shared CombatIcon helper.');
}

define('PUBLIC_INTERNAL_PATH', $root . '/game/deployment/public/');
require_once $root . '/game/include/Core/Helper/CombatIcon.php';
$icon = \Core\Helper\CombatIcon::svg('svg-attack.svg', 'losses attack', 'Attacker with no losses', 'attack');
foreach (['class="losses attack"', 'icon="attack"', '<title>Attacker with no losses</title>', 'fill-opacity="0"'] as $needle) {
    if (!str_contains($icon, $needle)) {
        failReportBattleDetailContract("Inline combat icon must contain $needle.");
    }
}
if (str_contains(\Core\Helper\CombatIcon::svg('svg-skull.svg', 'skull'), '<title>')) {
    failReportBattleDetailContract('An icon without a known reference title must not render a title.');
}

foreach (['VillageOrPlayerName', 'autocomplete/villageorplayer', 'Travian.Game.Reports.filter.icons', 'iReport'] as $needle) {
    if (!str_contains($filterTemplate, $needle)) {
        failReportBattleDetailContract("Report filter bar must contain $needle.");
    }
}
if (str_contains($filterTemplate, 'graphQL') || str_contains($filterTemplate, 'findPlayers')) {
    failReportBattleDetailContract('The filter bar must use the local autocomplete API, not GraphQL.');
}
if (!str_contains($routes, "'autocomplete/villageorplayer' => ['autocomplete', 'villageorplayer']")) {
    failReportBattleDetailContract('The villageorplayer autocomplete route must be registered.');
}
foreach (['publicUserCondition', 'REPORT_FILTER_LIMIT', 'likePattern'] as $needle) {
    if (!str_contains($endpoint, $needle)) {
        failReportBattleDetailContract("The autocomplete endpoint must use $needle.");
    }
}
foreach (['entityCondition', 'villageId', 'playerId'] as $needle) {
    if (!str_contains($model, $needle)) {
        failReportBattleDetailContract("The report model must filter by $needle.");
    }
}
if (!str_contains($controller, 'SETTLING_FILTER_ICON') || !str_contains($controller, 'OTHER_TAB_HIDDEN_TYPES')) {
    failReportBattleDetailContract('The Other tab must carry its hidden settling filter ids.');
}

$adventure = $read('game/include/Model/Movements/AdventureProcessor.php');
if (!str_contains($adventure, "'adventureNumber' => \$success_adventures + 1") || !str_contains($controller, 'adventureTitle')) {
    failReportBattleDetailContract('Adventure reports must store and show the per-hero adventure number.');
}

echo "Report battle detail contract passed.\n";
