<?php

declare(strict_types=1);

/**
 * the Reports implementation clones the hidden dialog markup without
 * event listeners. The inline controls are therefore the sole action path;
 * adding a second jQuery listener causes bulk report mutations to run twice.
 */
function failReportsDialogSingleActionContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/reports/All.php');
if ($template === false) {
    failReportsDialogSingleActionContract('Reports dialog template must be readable.');
}

$client = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');
if (!str_contains($client, 'jQuery("#content .dialogTemplates ." + e).clone()')) {
    failReportsDialogSingleActionContract('Reports dialogs must use the plain template clone.');
}

foreach ([
    'onclick="Travian.Game.Reports.closeDialog()"',
    'onclick="Travian.Game.Reports.processMarkAsRead()"',
    'onclick="Travian.Game.Reports.processDelete(false)"',
    "onchange=\"Travian.Game.Preferences.set('hideMarkAsReadDialog', this.checked)\"",
    "onchange=\"Travian.Game.Preferences.set('hideDeleteDialog', this.checked)\"",
] as $inlineControl) {
    if (!str_contains($template, $inlineControl)) {
        failReportsDialogSingleActionContract('Reports dialog control must keep its native inline action: ' . $inlineControl);
    }
}

foreach ([
    'clone(true, true)',
    'reportsDialog',
    "off('click.reportsDialog')",
    "on('click.reportsDialog'",
] as $duplicateHandler) {
    if (str_contains($template, $duplicateHandler)) {
        failReportsDialogSingleActionContract('Reports dialog controls must have exactly one action path: ' . $duplicateHandler);
    }
}

echo "Reports dialog single-action contract passed.\n";
