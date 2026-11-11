<?php

declare(strict_types=1);

/**
 * Contract: the Residence, Palace and Command Center expansion tab renders the reference's expansion slots (a
 * free slot with the settle and conquer descriptions, later slots waiting for it), not a table of founded
 * villages, and the slot levels have one source.
 */

function failResidenceExpansionSlotsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/ResidenceExpansion.php');
$helper = file_get_contents($root . '/game/include/Game/TrainingHelper.php');
if ($template === false || $helper === false) {
    failResidenceExpansionSlotsContract('Unable to read the expansion template or TrainingHelper.');
}
foreach (['id="expansionTab"', 'class="prerequisites"', 'class="unlockLevel"', 'class="freeExpansionSlot"', 'class="nextExpansionSlot"', 'class="noHover"', 'class="description settler"', 'class="description senator"'] as $needle) {
    if (!str_contains($template, $needle)) {
        failResidenceExpansionSlotsContract('The expansion tab is missing: ' . $needle);
    }
}
if (str_contains($template, '<table')) {
    failResidenceExpansionSlotsContract('The expansion tab must not render the old founded-villages table.');
}
if (!str_contains($helper, '[25 => [10, 20], 26 => [10, 15, 20], 44 => [10, 15, 20]]')) {
    failResidenceExpansionSlotsContract('The expansion slot levels must be defined once in TrainingHelper.');
}
if (preg_match_all('/\$(?:residence|palace|commandCenter) (?:>=|==) (?:10|15|20)/', $helper) > 0) {
    failResidenceExpansionSlotsContract('TrainingHelper must not repeat the slot level thresholds.');
}

fwrite(STDOUT, "Residence expansion slots contract passed.\n");
