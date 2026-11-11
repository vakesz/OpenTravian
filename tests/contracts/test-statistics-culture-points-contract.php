#!/usr/bin/env php
<?php

declare(strict_types=1);

function failStatisticsCulturePointsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../../game/include/Core/Helper/RechartsMarkup.php';

$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/game/include/Model/StatisticsGeneralModel.php');
$template = file_get_contents($root . '/game/include/resources/Templates/statistics/general.php');
$client = file_get_contents($root . '/game/deployment/public/statistics-general.js');
$bonus = file_get_contents($root . '/game/include/Game/AllianceBonus/AllianceBonus.php');
$repair = file_get_contents($root . '/game/include/fixes/fixPopCp.php');
$schema = file_get_contents($root . '/database/world.sql');
$updater = file_get_contents($root . '/game/include/update.php');

if ($model === false || $template === false || $client === false || $bonus === false || $repair === false || $schema === false || $updater === false) {
    failStatisticsCulturePointsContract('Unable to read the Culture Points statistics sources.');
}

foreach ([
    'SELECT owner, COALESCE(SUM(cp), 0) AS production FROM vdata',
    'SELECT h.uid, i.helmet FROM hero h LEFT JOIN inventory i ON i.uid=h.uid WHERE h.health>0',
    'AllianceBonus::culturePointProductionMultiplier(',
    'cp_per_day, cp_so_far',
    'AND cp_per_day IS NOT NULL AND cp_so_far IS NOT NULL',
] as $needle) {
    if (!str_contains($model, $needle)) {
        failStatisticsCulturePointsContract("Culture Points model missing canonical source: $needle");
    }
}

if (str_contains($model, "(int)\$mine['cp_prod']")) {
    failStatisticsCulturePointsContract('Culture Points rank must not use the potentially helmet-inclusive users.cp_prod cache.');
}
if (!str_contains($repair, 'SELECT COALESCE(SUM(cp), 0) FROM vdata') || str_contains($repair, 'CulturePointsHelper')) {
    failStatisticsCulturePointsContract('CP repair must cache buildings only; the live ticker owns the hero-helmet contribution.');
}

if (!str_contains($bonus, 'public static function culturePointProductionMultiplier')) {
    failStatisticsCulturePointsContract('Culture Points statistics must share the cooldown-aware alliance multiplier with gameplay.');
}

foreach (['`cp_per_day` BIGINT(20) UNSIGNED NULL DEFAULT NULL', '`cp_so_far` BIGINT(20) UNSIGNED NULL DEFAULT NULL'] as $column) {
    if (!str_contains($schema, $column) || !str_contains($updater, $column)) {
        failStatisticsCulturePointsContract("Culture Points daily snapshot column is missing from schema or update migration: $column");
    }
}

foreach (['data-statistics-cp-period-panel', "'cpProduced', 'CP produced so far:'", 'id="cpProgressionChart"', 'RechartsMarkup::areaValues(', 'chartWithLegend chartIncluded'] as $needle) {
    if (!str_contains($template, $needle)) {
        failStatisticsCulturePointsContract("Culture Points comparison template missing: $needle");
    }
}

if (str_contains($template, 'role="button"') || str_contains($template, 'tabindex="0"')) {
    failStatisticsCulturePointsContract('Reference Culture Points legend tabs are click-only divs, not keyboard-button widgets.');
}
if (!str_contains($client, "querySelectorAll('[data-statistics-cp-period-panel]')") || str_contains($client, "addEventListener('keydown', activate)")) {
    failStatisticsCulturePointsContract('Culture Points period controls must switch their panels on click without non-reference key handling.');
}

$referenceProgression = \Core\Helper\RechartsMarkup::areaValues(100, 99, [['day' => 12, 'value' => 85]], 12);
foreach (['>0</tspan>', '>25</tspan>', '>50</tspan>', '>85</tspan>'] as $tick) {
    if (!str_contains($referenceProgression, $tick)) {
        failStatisticsCulturePointsContract("Culture Points progression must retain the y-axis tick $tick for an 85-CP series.");
    }
}
if (str_contains($referenceProgression, '>75</tspan>')) {
    failStatisticsCulturePointsContract('Culture Points progression must not add a non-reference 75-CP tick to the 85-CP series.');
}

echo "Statistics Culture Points contract passed.\n";
