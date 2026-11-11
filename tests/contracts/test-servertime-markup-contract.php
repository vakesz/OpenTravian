<?php

declare(strict_types=1);

/**
 * Contract: #servertime matches — spans + Server time / (UTC),
 * counting-up timer with format="24h", no .stime wrapper.
 */

function failServertimeMarkupContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$functions = file_get_contents($root . '/game/include/functions.general.php');
if ($layout === false || $functions === false) {
    failServertimeMarkupContract('Unable to read servertime sources.');
}

if (!str_contains($layout, 'id="servertime"')
    || !str_contains($layout, 'format="24h" class="timer" counting="up" value="<?= (int) $vars["dateTime"] ?>"')
    || !str_contains($layout, '(UTC)')) {
    failServertimeMarkupContract('Layout must render #servertime as a 24-hour counting-up UTC timer.');
}

if (str_contains($layout, 'class="stime"') || str_contains($layout, "class='stime'")) {
    failServertimeMarkupContract('Remove the legacy .stime wrapper around server time.');
}

if (!str_contains($functions, 'format="24h" class="timer')
    || !str_contains($functions, 'counting="up"')) {
    failServertimeMarkupContract('appendTimer type=1 (server clock) must emit format="24h".');
}

echo "Servertime markup contract passed.\n";
