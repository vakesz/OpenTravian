<?php

declare(strict_types=1);

function failProductionBonusRowContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$templatePath = dirname(__DIR__, 2) . '/game/include/resources/Templates/production/res.php';
$template = file_get_contents($templatePath);

if ($template === false) {
    failProductionBonusRowContract('Unable to read the production resource template.');
}

if (!str_contains($template, 'if ($level <= 0)')) {
    failProductionBonusRowContract('Production bonus rows must omit absent level-zero bonus buildings.');
}

if (!str_contains($template, 'if ($waterWorksLevel > 0)')) {
    failProductionBonusRowContract('Production bonus rows must omit an absent level-zero Waterworks.');
}

$zeroLevelGuard = strpos($template, 'if ($level <= 0)');
$bonusBuildingRow = strpos($template, "T(\"Buildings\", \$boost . '.title')");
if ($zeroLevelGuard === false || $bonusBuildingRow === false || $zeroLevelGuard >= $bonusBuildingRow) {
    failProductionBonusRowContract('A positive production bonus building must still render after the zero-level guard.');
}

echo "Production bonus row contract passed.\n";
