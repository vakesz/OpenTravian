<?php

declare(strict_types=1);

/**
 * Contract: rally send village input uses name="villagename".
 */

function failRallypointSendVillagenameContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$send = file_get_contents($root . '/game/include/resources/Templates/rallypoint/sendTroops.php');
$prepare = file_get_contents($root . '/game/include/resources/Templates/rallypoint/prepareSend.php');
$controller = file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');

if ($send === false || $prepare === false || $controller === false) {
    failRallypointSendVillagenameContract('Unable to read rally send sources.');
}

if (!str_contains($send, 'id="enterVillageName"')
    || !str_contains($send, 'name="villagename"')
    || !str_contains($send, 'autocomplete="off"')
    || str_contains($send, 'name="dname"')) {
    failRallypointSendVillagenameContract('sendTroops.php must use name="villagename" (not dname).');
}

if (!str_contains($prepare, 'name="villagename"')
    || str_contains($prepare, 'name="dname"')) {
    failRallypointSendVillagenameContract('prepareSend.php hidden village field must be villagename.');
}

if (!str_contains($controller, "\$_REQUEST['villagename']")
    || !str_contains($controller, "\$_REQUEST['dname']")) {
    failRallypointSendVillagenameContract(
        'sendTroops controller must prefer villagename with dname fallback.'
    );
}

echo "Rallypoint send villagename contract passed.\n";
