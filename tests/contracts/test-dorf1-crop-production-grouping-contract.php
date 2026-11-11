<?php

declare(strict_types=1);

/**
 * Hourly production and troop counts on the resource-fields panel group thousands with a space.
 */

function failDorf1CropProductionGroupingContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function T(string $base, string $code): string
{
    return $code;
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/resource-fields/main.php');
if ($template === false) {
    failDorf1CropProductionGroupingContract('Unable to read the resource-fields template.');
}

$panelStart = strpos($template, '<div class="villageInfoWrapper">');
if ($panelStart === false) {
    failDorf1CropProductionGroupingContract('The dorf1 village-information panel is missing.');
}
$panel = substr($template, $panelStart);

$productionBoostArrow = static fn (bool $boosted): string => '';

/**
 * @param list<int|float> $production
 * @return list<string>
 */
$figures = static function (array $production) use ($panel, $productionBoostArrow): array {
    $vars = [
        'onLoadBuildings' => '',
        'movements' => '',
        'productionBoost' => [false, false, false, false],
        'production' => $production,
        'goldProductionBoostButton' => '',
        'unitsSummary' => 0,
        'units' => [],
    ];
    ob_start();
    eval('?>' . $panel);
    $rendered = (string) ob_get_clean();
    if (!preg_match_all('/<td class="num">\s*\x{202D}(.*?)\x{202C}\s*<\/td>/u', $rendered, $matches)) {
        failDorf1CropProductionGroupingContract('Production figures were not rendered.');
    }

    return $matches[1];
};

$cases = [
    [[621438, 728625.4, 728625, 11327038], ['621 438', '728 625', '728 625', '11 327 038']],
    [[1, 20, 999, 1000], ['1', '20', '999', '1 000']],
    [[1, 20, 999, -11327038], ['1', '20', '999', '-11 327 038']],
    [[1, 20, 999, -999], ['1', '20', '999', '-999']],
];

foreach ($cases as [$production, $expected]) {
    $actual = $figures($production);
    if ($actual !== $expected) {
        failDorf1CropProductionGroupingContract(
            'Hourly production must group thousands with spaces. Expected '
            . json_encode($expected) . ', got ' . json_encode($actual) . '.'
        );
    }
}

$vars = [
    'onLoadBuildings' => '',
    'movements' => '',
    'productionBoost' => [false, false, false, false],
    'production' => [0, 0, 0, 0],
    'goldProductionBoostButton' => '',
    'unitsSummary' => 3,
    'units' => [1 => 2537, 4 => 1000, 10 => 6],
];
ob_start();
eval('?>' . $panel);
$rendered = (string) ob_get_clean();
if (!preg_match('/<table id="troops"[\s\S]*?<\/table>/', $rendered, $troops)) {
    failDorf1CropProductionGroupingContract('The troop panel was not rendered.');
}
preg_match_all('/<td class="num">(.*?)<\/td>/', $troops[0], $troopFigures);
if (($troopFigures[1] ?? []) !== ['2 537', '1 000', '6']) {
    failDorf1CropProductionGroupingContract(
        'Troop counts must group thousands with spaces. Got ' . json_encode($troopFigures[1] ?? []) . '.'
    );
}

echo "Dorf1 crop production grouping contract passed.\n";
