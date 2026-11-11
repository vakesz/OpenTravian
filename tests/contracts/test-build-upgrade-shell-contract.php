<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$wrapper = file_get_contents($root . '/game/include/resources/Templates/build/buildingReadyWrapper.php');
$buildCtrl = file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$rallyCtrl = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointCtrl.php');
$sendTroops = file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');
$sendTemplate = file_get_contents($root . '/game/include/resources/Templates/rallypoint/sendTroops.php');
$gpack = file_get_contents($root . '/assets/gpack/travian/mainPage/css_ltr/imports.css');

foreach ([
    'buildingReadyWrapper.php' => $wrapper,
    'BuildCtrl.php' => $buildCtrl,
    'RallyPointCtrl.php' => $rallyCtrl,
    'sendTroops.php' => $sendTroops,
    'sendTroops template' => $sendTemplate,
    'imports.css' => $gpack,
] as $label => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$label}.\n");
        exit(1);
    }
}

foreach ([
    ' levelSpecificInfo',
    'buildingBenefits',
    'iconWrapper',
] as $fragment) {
    if (!str_contains($wrapper, $fragment)) {
        fwrite(STDERR, "buildingReadyWrapper.php must expose reference fragment: {$fragment}\n");
        exit(1);
    }
}

if (str_contains($wrapper, 'class="culturePointsAndPopulation')
    || str_contains($wrapper, "class='culturePointsAndPopulation")) {
    fwrite(STDERR, "buildingReadyWrapper.php must use buildingBenefits (Gpack has no culturePointsAndPopulation rules).\n");
    exit(1);
}

if (str_contains($buildCtrl, '<tr class="overall"><th></th><td></td></tr>')) {
    fwrite(STDERR, "BuildCtrl must not emit an empty overall production row.\n");
    exit(1);
}

if (!str_contains($gpack, 'div#build .buildingBenefits') && !str_contains($gpack, '.buildingBenefits{')) {
    fwrite(STDERR, "Gpack must style buildingBenefits.\n");
    exit(1);
}

if (!str_contains($rallyCtrl, "' buildRallyPointFarmList' : ' buildRallyPoint'")
    || !str_contains($rallyCtrl, '$tt = (int)$tt')) {
    fwrite(STDERR, "Rally Point must cast tt and append buildRallyPoint to #content.\n");
    exit(1);
}

if (!str_contains($sendTroops, 'span class="none"')
    || str_contains($sendTroops, 'errorMessage"> / 0')) {
    fwrite(STDERR, "Send-troops zero counts must use span.none like the reference.\n");
    exit(1);
}

if (str_contains($sendTroops, 'if ($i == "hero" && $value <= 0)')) {
    fwrite(STDERR, "Send-troops must always render the hero cell.\n");
    exit(1);
}

if (str_contains($sendTemplate, 'boxes boxesColor gray')) {
    fwrite(STDERR, "Send-troops destination must not wrap coordinates in legacy gray boxes.\n");
    exit(1);
}

echo "Build upgrade shell contract passed.\n";
