<?php

declare(strict_types=1);

function failDorf1ProductionBoostIconContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/resource-fields/main.php');
if ($template === false) {
    failDorf1ProductionBoostIconContract('Dorf1 template must be readable.');
}

// The arrow is the shared Game\ProductionBoostArrow (the resource bars and the shop cards draw the same svg).
$arrow = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/ProductionBoostArrow.php');
if ($arrow === false) {
    failDorf1ProductionBoostIconContract('The shared arrow must be readable.');
}
foreach ([
    'ProductionBoostArrow::forResourceBar($boosted)',
    '$productionBoostArrow',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failDorf1ProductionBoostIconContract("Dorf1 production bonus must use the shared arrow: {$needle}");
    }
}
foreach ([
    "' advantageBonusArrow productionBoost' . (\$boosted ? ' premiumFeature' : '')",
    '<path class="border"',
    '<path class="sideShadow"',
    '<path class="bottomShadow"',
    '<path class="topShadow"',
    '<path class="inner"',
] as $needle) {
    if (!str_contains($arrow, $needle)) {
        failDorf1ProductionBoostIconContract("Dorf1 production bonus must keep SVG markup: {$needle}");
    }
}

if (str_contains($template, '<img src="/img/spacer.gif" class="productionBoost"')) {
    failDorf1ProductionBoostIconContract('Dorf1 must not retain the legacy production-boost sprite placeholder.');
}

echo "Dorf1 production-boost icon contract passed.\n";
