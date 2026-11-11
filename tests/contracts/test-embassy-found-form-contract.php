<?php

declare(strict_types=1);

/**
 * Contract: Embassy founding form uses newTag/newName field names + textButtonV1.
 */

function failEmbassyFoundFormContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/EmbassyFound.php');
$info = file_get_contents($root . '/game/include/resources/Templates/build/building/EmbassyInfo.php');
$ctrl = file_get_contents($root . '/game/include/Controller/Build/EmbassyCtrl.php');
if ($template === false || $info === false || $ctrl === false) {
    failEmbassyFoundFormContract('Unable to read Embassy founding sources.');
}

foreach (['name="newTag"', 'name="newName"', 'maxlength="5"', 'maxlength="15"', 'textButtonV1 green'] as $fragment) {
    if (!str_contains($template, $fragment)) {
        failEmbassyFoundFormContract("EmbassyFound template missing fragment: {$fragment}");
    }
}

foreach (['name="ally1"', 'name="ally2"', "\$_POST['ally1']", "\$_POST['ally2']"] as $legacy) {
    if (str_contains($template, $legacy) || str_contains($ctrl, $legacy)) {
        failEmbassyFoundFormContract("Embassy founding still uses legacy fragment: {$legacy}");
    }
}

if (!str_contains($ctrl, "\$_POST['newTag']") || !str_contains($ctrl, "\$_POST['newName']")) {
    failEmbassyFoundFormContract('EmbassyCtrl::found must read newTag/newName.');
}

if (!str_contains($ctrl, 'strlen($view->vars[\'tag\']) > 5')
    || !str_contains($ctrl, 'strlen($view->vars[\'name\']) > 15')) {
    failEmbassyFoundFormContract('EmbassyCtrl founding length checks must match 5/15 limits.');
}

if (!str_contains($info, 'textButtonV1 green')
    || str_contains($info, "'class' => 'green'")) {
    failEmbassyFoundFormContract('EmbassyInfo “to the alliance” must use textButtonV1 green.');
}

echo "Embassy found form contract passed.\n";
