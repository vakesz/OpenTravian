<?php

declare(strict_types=1);

/**
 * Contract: Troop building training queue rows use absolute /img/spacer.gif unit sprites
 * (same pattern as TroopBuildingTrainingWrapper and HospitalCtrl heal queue).
 */

function failTroopQueueMarkupContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$troopBuilding = file_get_contents($root . '/game/include/Controller/Build/TroopBuilding.php');
$wrapper = file_get_contents($root . '/game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php');
$hospital = file_get_contents($root . '/game/include/Controller/Build/HospitalCtrl.php');

if ($troopBuilding === false || $wrapper === false || $hospital === false) {
    failTroopQueueMarkupContract('Unable to read troop queue markup sources.');
}

if (str_contains($troopBuilding, 'src="img/spacer.gif"')) {
    failTroopQueueMarkupContract('TroopBuilding queue must not use relative src="img/spacer.gif".');
}

if (!preg_match(
    '/class="unit u\' \. \$unitId \. \'" src="\/img\/spacer\.gif"/',
    $troopBuilding
)) {
    failTroopQueueMarkupContract('TroopBuilding training row must use class="unit u{id}" with src="/img/spacer.gif".');
}

foreach ([$wrapper, $hospital] as $source) {
    if (!str_contains($source, 'src="/img/spacer.gif"')) {
        failTroopQueueMarkupContract('Reference troop queue markup must use src="/img/spacer.gif".');
    }
}

fwrite(STDOUT, "Troop queue markup contract passed.\n");
