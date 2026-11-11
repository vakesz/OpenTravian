<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';

use Game\WorldSpeedProfile;

$oases = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/OasesModel.php');

if (WorldSpeedProfile::oasisInitialProtectionTime(1) !== 5 * 24 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(2) !== 3 * 24 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(3) !== 3 * 24 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(5) !== 2 * 24 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(10) !== 24 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(100) !== 72 * 3600
    || WorldSpeedProfile::oasisInitialProtectionTime(1000) !== 16 * 3600) {
    fwrite(STDERR, "Initial oasis protection must follow the world-speed profile.\n");
    exit(1);
}

if ($oases === false
    || !str_contains($oases, 'getInitialProtectionEndTime')
    || !str_contains($oases, 'if ($now < self::getInitialProtectionEndTime())')
    || !str_contains($oases, 'if (time() < self::getInitialProtectionEndTime())')) {
    fwrite(STDERR, "Oasis training must observe initial protection for direct and scheduled respawns.\n");
    exit(1);
}

echo "Oasis initial-protection contract passed.\n";
