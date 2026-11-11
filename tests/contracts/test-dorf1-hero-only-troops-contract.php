<?php

declare(strict_types=1);

namespace {
    function T(string $base, string $code): string
    {
        return match ([$base, $code]) {
            ['Dorf1', 'production.production per hour'] => 'Production per hour',
            ['Dorf1', 'production.resources.1'] => 'Lumber',
            ['Dorf1', 'production.resources.2'] => 'Clay',
            ['Dorf1', 'production.resources.3'] => 'Iron',
            ['Dorf1', 'production.resources.4'] => 'Crop',
            ['Dorf1', 'units'] => 'Troops',
            ['Dorf1', 'none'] => 'none',
            ['Troops', 'hero.title'] => 'Hero',
            default => $code,
        };
    }

    function number_format_x(int|float $number, int|float $limit): string
    {
        return (string) $number;
    }

    function failDorf1HeroOnlyTroopsContract(string $message): never
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    $template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/resource-fields/main.php');
    $controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/ResourceFieldsCtrl.php');
    $model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/ResourceFieldsModel.php');
    $unitFilter = file_get_contents(dirname(__DIR__, 2) . '/game/include/functions.general.php');
    if ($template === false || $controller === false || $model === false || $unitFilter === false) {
        failDorf1HeroOnlyTroopsContract('Unable to read the Dorf1 troop-panel sources.');
    }

    $panelStart = strpos($template, '<div class="villageInfoWrapper">');
    if ($panelStart === false) {
        failDorf1HeroOnlyTroopsContract('The dorf1 village-information panel is missing.');
    }
    if (str_contains($template, 'heroOnly')) {
        failDorf1HeroOnlyTroopsContract('Hero-only villages must not use a separate empty-state branch.');
    }

    $unitsActionStart = strpos($controller, 'private function unitsAction(');
    $unitsActionEnd = strpos($controller, 'private function movementsAction(', $unitsActionStart ?: 0);
    $unitsAction = $unitsActionStart === false || $unitsActionEnd === false
        ? ''
        : substr($controller, $unitsActionStart, $unitsActionEnd - $unitsActionStart);
    $summary = strpos($unitsAction, '$unitsSummary += $num;');
    $unitMapping = strpos($unitsAction, 'nrToUnitId($i, $units[\'race\'])');
    if (!str_contains($model, 'array_filter_units($units)')
        || !str_contains($unitFilter, 'for ($i = 1; $i <= 11; ++$i)')
        || $summary === false
        || $unitMapping === false
        || $summary > $unitMapping) {
        failDorf1HeroOnlyTroopsContract('The Dorf1 controller must count the u11 Hero before choosing the no-troops state.');
    }

    $panel = substr($template, $panelStart);
    $vars = [
        'onLoadBuildings' => '',
        'movements' => '',
        'productionBoost' => [false, false, false, false],
        'production' => [0, 0, 0, 0],
        'goldProductionBoostButton' => '',
        'unitsSummary' => 1,
        'units' => [98 => 1],
    ];

    $productionBoostArrow = static fn (bool $boosted): string => '';
    ob_start();
    eval('?>' . $panel);
    $rendered = (string) ob_get_clean();

    if (!str_contains($rendered, '>Hero<')) {
        failDorf1HeroOnlyTroopsContract('A home Hero must be rendered in the troop panel.');
    }
    if (str_contains($rendered, '>none.<')) {
        failDorf1HeroOnlyTroopsContract('A Hero-only troop panel must not append the no-troops message.');
    }

    echo "Dorf1 hero-only troops contract passed.\n";
}
