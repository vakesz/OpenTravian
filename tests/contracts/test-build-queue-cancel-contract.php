<?php

declare(strict_types=1);

/**
 * Contract: build-queue cancel links must survive <base href="/">.
 * Bare "?d=…&a=0&c=…" resolves to "/?…" → LoginCtrl (looks like logout).
 */

function failBuildQueueCancelContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/OnLoadBuildingsDorfCtrl.php');
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');

if ($ctrl === false || $head === false) {
    failBuildQueueCancelContract('Unable to read build-queue cancel sources.');
}

if (!str_contains($head, '<base href="/"/>') && !str_contains($head, '<base href="/"')) {
    failBuildQueueCancelContract('Expected layout head to keep <base href="/"> (cancel URLs must be path-absolute).');
}

if (str_contains($ctrl, '?d=') || str_contains($ctrl, 'a=0')) {
    failBuildQueueCancelContract('OnLoadBuildingsDorfCtrl still emits the old d/a/c cancel link.');
}

if (!str_contains($ctrl, "'/' . \$this->villagePage() . '?cancel=") || !str_contains($ctrl, 'getChecker()')) {
    failBuildQueueCancelContract('The cancel URL must be path-absolute (/dorf1.php or /dorf2.php) and carry the village checker token.');
}

if (!str_contains($ctrl, 'showCancelBuildingDialog(')) {
    failBuildQueueCancelContract('A queued order must open the abort dialog before cancelling.');
}

foreach (['resource-fields', 'village-center'] as $page) {
    $template = file_get_contents($root . '/game/include/resources/Templates/' . $page . '/buildingQueue.php');
    if ($template === false || !str_contains($template, 'function showCancelBuildingDialog(eventId, title, text)')
        || !str_contains($template, "<?=\$vars['cancelUrl'];?>")) {
        failBuildQueueCancelContract($page . ' buildingQueue.php must define showCancelBuildingDialog with the root-absolute cancel URL.');
    }
}

$request = file_get_contents($root . '/game/include/Game/BuildRequest.php');
if ($request === false || !str_contains($request, "\$_GET['cancel']")) {
    failBuildQueueCancelContract('BuildRequest must handle the cancel parameter.');
}

echo "Build queue cancel contract passed.\n";
