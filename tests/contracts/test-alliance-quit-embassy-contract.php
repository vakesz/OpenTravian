<?php

declare(strict_types=1);

/**
 * Contract: Alliance Options "Quit alliance" deep-links to the embassy leave
 * page (/build.php?newdid=&tt=leave&id=).
 */

function failAllianceQuitEmbassyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/game/include/Model/AllianceModel.php');
$options = file_get_contents($root . '/game/include/resources/Templates/alliance/options.php');
$ctrl = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$embassy = file_get_contents($root . '/game/include/Controller/Build/EmbassyCtrl.php');

foreach ([$model, $options, $ctrl, $embassy] as $source) {
    if ($source === false) {
        failAllianceQuitEmbassyContract('Unable to read alliance quit sources.');
    }
}

foreach ([
    'function getQuitAllianceHref',
    "'&tt=leave&id='",
    'fieldId',
] as $needle) {
    if (!str_contains($model, $needle)) {
        failAllianceQuitEmbassyContract('AllianceModel missing quit embassy helper: ' . $needle);
    }
}

if (!str_contains($options, "\$vars['quitHref']")) {
    failAllianceQuitEmbassyContract('alliance/options.php must prefer the embassy quitHref.');
}

if (!str_contains($ctrl, 'getQuitAllianceHref')) {
    failAllianceQuitEmbassyContract('AllianceCtrl must pass/use getQuitAllianceHref.');
}

if (!str_contains($embassy, "'leave'") || !str_contains($embassy, "case 'leave':")) {
    failAllianceQuitEmbassyContract('EmbassyCtrl must accept tt=leave as the quit deep link.');
}

fwrite(STDOUT, "Alliance quit→embassy contract passed.\n");
