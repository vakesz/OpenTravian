<?php

declare(strict_types=1);

function failStatisticsGeneralOverview(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/statistics/general.php');

if ($controller === false || $template === false) {
    failStatisticsGeneralOverview('Could not read the statistics overview sources.');
}

$methodStart = strpos($controller, 'private function processGeneralOverview');
$methodEnd = strpos($controller, 'private function processPlayersOverview', $methodStart ?: 0);
if ($methodStart === false || $methodEnd === false) {
    failStatisticsGeneralOverview('Could not isolate the General statistics overview route.');
}

$overview = substr($controller, $methodStart, $methodEnd - $methodStart);
if (!str_contains($overview, "'statistics/general'")
    || str_contains($overview, "'statistics/general2'")) {
    failStatisticsGeneralOverview('The General statistics tab must render the live overview, not the static demo view.');
}

if (str_contains($controller, 'statistics/general2')) {
    failStatisticsGeneralOverview('The retired static General statistics demo must not remain in the runtime.');
}

foreach ([
    '/game/include/resources/Templates/statistics/general2.php',
    '/game/include/resources/Templates/statistics/general_translations_en.json',
    '/game/include/resources/Templates/statistics/general_translations_ir.json',
] as $obsoleteArtifact) {
    if (file_exists($root . $obsoleteArtifact)) {
        failStatisticsGeneralOverview('The retired static General statistics demo must not remain in the runtime.');
    }
}

$generalTabStart = strrpos($controller, 'case 5:');
$generalTabEnd = strpos($controller, 'case 6:', $generalTabStart ?: 0);
if ($generalTabStart === false || $generalTabEnd === false
    || !str_contains(
        substr($controller, $generalTabStart, $generalTabEnd - $generalTabStart),
        'return $this->processGeneralOverview'
    )) {
    failStatisticsGeneralOverview('The General statistics tab must route to its live overview.');
}

if (!str_contains($overview, 'StatisticsGeneralModel') || !str_contains($overview, 'viewData(')) {
    failStatisticsGeneralOverview('The General statistics overview must be built from StatisticsGeneralModel::viewData().');
}

$model = file_get_contents($root . '/game/include/Model/StatisticsGeneralModel.php');
foreach ([
    'getTribesStatus()',
    'statistics_daily',
    'stat_trade_in',
    'uCombatStats(',
    'cp_prod',
] as $requiredDataSource) {
    if ($model === false || !str_contains($model, $requiredDataSource)) {
        failStatisticsGeneralOverview(sprintf('The General statistics model must derive its series from %s.', $requiredDataSource));
    }
}

foreach ([
    'RechartsMarkup::pie(',
    'RechartsMarkup::area(',
    'id="serverProgression"',
    'id="populationRank"',
    'id="resourceRank"',
    'id="militaryStrengthRank"',
    'id="culturePointsRank"',
] as $requiredBinding) {
    if (!str_contains($template, $requiredBinding)) {
        failStatisticsGeneralOverview(sprintf('The live statistics overview must render %s.', $requiredBinding));
    }
}

foreach (['statistics_daily', 'stat_trade_in', 'stat_trade_out'] as $column) {
    if (!str_contains((string) file_get_contents($root . '/database/world.sql'), $column)) {
        failStatisticsGeneralOverview(sprintf('database/world.sql must define %s.', $column));
    }
}

echo "Statistics General overview contract passed.\n";
