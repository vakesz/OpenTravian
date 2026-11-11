<?php

declare(strict_types=1);

/**
 * Contract: stock bar productionBoost uses the dorf1 advantageBonusArrow SVG,
 * not the legacy 15×15 plus-path glyph.
 */

function failStockbarProductionBoostArrowContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$dorf1 = file_get_contents($root . '/game/include/resources/Templates/resource-fields/main.php');
// Both templates draw the shared Game\ProductionBoostArrow, which owns the svg.
$arrowSource = file_get_contents($root . '/game/include/Game/ProductionBoostArrow.php');
if ($layout === false || $dorf1 === false || $arrowSource === false) {
    failStockbarProductionBoostArrowContract('Unable to read stock-bar / dorf1 sources.');
}

$arrowClass = "' advantageBonusArrow productionBoost' . (\$boosted ? ' premiumFeature' : '')";
$arrowViewBox = 'viewBox="0 0 160 180"';
$pathClasses = ['class="border"', 'class="sideShadow"', 'class="bottomShadow"', 'class="topShadow"', 'class="inner"'];

if (!str_contains($dorf1, 'ProductionBoostArrow::forResourceBar($boosted)') || !str_contains($arrowSource, $arrowClass) || !str_contains($arrowSource, $arrowViewBox)) {
    failStockbarProductionBoostArrowContract('dorf1 must define the advantageBonusArrow productionBoost SVG.');
}

if (!str_contains($layout, '$productionBoostArrow')
    || substr_count($layout, '<?= $productionBoostArrow((bool)') < 4) {
    failStockbarProductionBoostArrowContract('Layout stock bar must emit $productionBoostArrow for all four resources.');
}

if (!str_contains($layout, 'ProductionBoostArrow::forResourceBar($boosted)') || !str_contains($arrowSource, $arrowViewBox)) {
    failStockbarProductionBoostArrowContract('Layout stock bar must use advantageBonusArrow viewBox 0 0 160 180.');
}

foreach ($pathClasses as $pathClass) {
    if (!str_contains($arrowSource, $pathClass)) {
        failStockbarProductionBoostArrowContract("Layout stock-bar arrow missing path {$pathClass}.");
    }
}

if (str_contains($layout, 'viewBox="0 0 15 15"')
    || str_contains($layout, 'M5 0L5 5L0 5L0 10L5 10L5 15L10 15L10 10L15 10L15 5L10 5L10 0Z')) {
    failStockbarProductionBoostArrowContract('Remove the legacy 15×15 plus-path productionBoost SVG from Layout.');
}

fwrite(STDOUT, "Stockbar production-boost arrow contract passed.\n");
