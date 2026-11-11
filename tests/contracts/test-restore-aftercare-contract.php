<?php

declare(strict_types=1);

/**
 * Contract: the restore aftercare hands whole seconds to the infobox. At the high speeds the round timers are floats
 * (ceil of a fraction of a day), and InfoBoxModel::addInfo takes integers, so a restore of a 100x world stopped after
 * the databases were back with a TypeError.
 */

function failRestoreAftercareContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$aftercare = file_get_contents($root . '/game/include/Core/Database/RestoreAftercare.php');
$infobox = file_get_contents($root . '/game/include/Model/InfoBoxModel.php');
if ($aftercare === false || $infobox === false) {
    failRestoreAftercareContract('Unable to read the restore aftercare sources.');
}
if (preg_match('/function addInfo\([^)]*int \$showFrom/', $infobox) !== 1) {
    failRestoreAftercareContract('InfoBoxModel::addInfo must keep its integer showFrom.');
}
if (preg_match_all('/->addInfo\(([^;]*)\);/', $aftercare, $calls) !== 3) {
    failRestoreAftercareContract('The aftercare adds three announcements.');
}
foreach ($calls[1] as $arguments) {
    if (substr_count($arguments, '(int)') < 2) {
        failRestoreAftercareContract('Each announcement must cast its two timestamps to int: ' . $arguments);
    }
}

echo "Restore aftercare contract passed.\n";
