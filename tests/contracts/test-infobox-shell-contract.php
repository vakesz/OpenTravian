<?php

declare(strict_types=1);

/**
 * Contract: Infobox shell is always toggleable, with .toggle,
 * title+messageShortInfo in .boxTitleWrapper, en-US "Info box".
 */

function failInfoboxShellContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxInfoBox.php');
$enUs = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($template === false || $enUs === false) {
    failInfoboxShellContract('Unable to read infobox sources.');
}

if (!str_contains($template, 'class="sidebarBox toggleable')
    || str_contains($template, "total'] > 1 ? 'toggleable'")) {
    failInfoboxShellContract('Infobox must always emit sidebarBox toggleable (not gated on total > 1).');
}

if (!preg_match('/class="boxTitleWrapper"[\s\S]*class="boxTitle"[\s\S]*class="inlineIcon messageShortInfo"/', $template)) {
    failInfoboxShellContract('Infobox title and messageShortInfo must sit inside .boxTitleWrapper.');
}

if (!str_contains($template, '<div class="toggle">')
    || str_contains($template, "if (\$vars['total'] > 1)")) {
    failInfoboxShellContract('Infobox must always emit .toggle (not gated on total > 1).');
}

if (!str_contains($enUs, "\$Definition['inGame']['InfoBox'] = 'Info box';")) {
    failInfoboxShellContract('en-US inGame.InfoBox must be "Info box".');
}

fwrite(STDOUT, "Infobox shell contract passed.\n");
