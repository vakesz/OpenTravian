<?php

declare(strict_types=1);

/**
 * Contract: Academy/Smithy research rows use Manual.open troop links,
 * absolute /img/spacer.gif sprites, unitZoom on big portrait only (no separate zoom link).
 */

function failResearchManualMarkupContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$paths = [
    'AcademyResearch' => $root . '/game/include/resources/Templates/build/building/AcademyResearch.php',
    'SmithyResearch' => $root . '/game/include/resources/Templates/build/building/SmithyResearch.php',
];

foreach ($paths as $label => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failResearchManualMarkupContract('Unable to read ' . $label . '.');
    }

    if (!str_contains($source, "Travian.Game.Manual.open('troop'")) {
        failResearchManualMarkupContract($label . ' must use Travian.Game.Manual.open(\'troop\', ...).');
    }

    if (str_contains($source, 'Travian.Game.iPopup')) {
        failResearchManualMarkupContract($label . ' must not use Travian.Game.iPopup.');
    }

    if (!str_contains($source, 'src="/img/spacer.gif"')) {
        failResearchManualMarkupContract($label . ' must use src="/img/spacer.gif".');
    }

    if (str_contains($source, 'src="img/spacer.gif"')) {
        failResearchManualMarkupContract($label . ' must not use bare src="img/spacer.gif".');
    }

    if (!str_contains($source, 'class="unitZoom"')) {
        failResearchManualMarkupContract($label . ' must use unitZoom on big portrait link.');
    }

    if (preg_match('/class="zoom"/', $source) === 1) {
        failResearchManualMarkupContract($label . ' must not include a separate zoom link (class="zoom").');
    }

    if (!str_contains($source, 'information contractWrapper')) {
        failResearchManualMarkupContract($label . ' must keep information contractWrapper.');
    }
}

fwrite(STDOUT, "Research manual markup contract passed.\n");
