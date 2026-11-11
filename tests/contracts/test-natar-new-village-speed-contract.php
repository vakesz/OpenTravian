<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';

use Game\WorldSpeedProfile;

function assertNatarSpeedValue($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

assertNatarSpeedValue(1, WorldSpeedProfile::natarNewVillageStrengthMultiplier(1, false), '1x Natar waves must retain their base strength.');
assertNatarSpeedValue(1, WorldSpeedProfile::natarNewVillageStrengthMultiplier(10, true), 'Official-speed Natar waves must retain their base strength.');
assertNatarSpeedValue(20, WorldSpeedProfile::natarNewVillageStrengthMultiplier(1000, false), '1000x Natar waves must use the normal high-speed multiplier.');
assertNatarSpeedValue(34, WorldSpeedProfile::natarNewVillageStrengthMultiplier(1000, true), 'Instant-finish worlds must use the established Natar multiplier.');
assertNatarSpeedValue(2000, WorldSpeedProfile::natarNewVillageStrengthMultiplier(100000, false), 'Natar scaling must cover the installer speed limit.');
assertNatarSpeedValue(3334, WorldSpeedProfile::natarNewVillageStrengthMultiplier(100000, true), 'Instant-finish Natar scaling must cover the installer speed limit.');
assertNatarSpeedValue(86400.0, WorldSpeedProfile::natarNewVillageTravelTime(1), '1x Natar attacks must keep their full-day travel time.');
assertNatarSpeedValue(600.0, WorldSpeedProfile::natarNewVillageTravelTime(1000), 'Natar attacks must retain their ten-minute travel floor.');

$natars = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/NatarsModel.php');
if ($natars === false
    || !str_contains($natars, 'WorldSpeedProfile::natarNewVillageStrengthMultiplier')
    || !str_contains($natars, 'WorldSpeedProfile::natarNewVillageTravelTime')) {
    fwrite(STDERR, "New-village Natar attacks must use the shared speed policy.\n");
    exit(1);
}

echo "Natar new-village speed contract passed.\n";
