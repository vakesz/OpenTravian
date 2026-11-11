<?php

declare(strict_types=1);

use Game\PremiumDuration;

require dirname(__DIR__, 2) . '/game/include/Game/PremiumDuration.php';
require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';

function expectPremiumDuration(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$day = 86400;
foreach ([2, 3, 5, 10] as $speed) {
    expectPremiumDuration(
        PremiumDuration::officialDefault($speed, 10800) === 3 * $day,
        "Published x{$speed} worlds must use the official three-day duration."
    );
}
expectPremiumDuration(
    PremiumDuration::officialDefault(1, 10800) === 7 * $day,
    'Published x1 worlds must use the official seven-day duration.'
);
foreach ([4, 6, 9, 11, 20, 100] as $speed) {
    expectPremiumDuration(
        PremiumDuration::officialDefault($speed, 10800) === $day,
        "Unlisted x{$speed} worlds use their local duration, never under one day."
    );
    expectPremiumDuration(
        PremiumDuration::officialDefault($speed, 2 * $day) === 2 * $day,
        "Unlisted x{$speed} worlds retain a local duration above one day."
    );
}
// OpenTravian Plus: 7 days at x1, 3 days from x2 through x10 (also the unpublished speeds in between), then
// 3 days x 10 / speed with a one-day floor (user rule 2026-11-08: every bonus lasts at least one day).
expectPremiumDuration(PremiumDuration::plusDefault(1) === 7 * $day, 'Plus lasts seven days at x1.');
foreach ([2, 3, 4, 5, 6, 9, 10] as $speed) {
    expectPremiumDuration(PremiumDuration::plusDefault($speed) === 3 * $day, "Plus lasts three days at x{$speed}.");
}
foreach ([20 => 129600, 29 => 89379, 30 => 86400, 100 => 86400, 250 => 86400, 1000 => 86400, 1000000 => 86400] as $speed => $seconds) {
    expectPremiumDuration(PremiumDuration::plusDefault($speed) === $seconds, "Plus at x{$speed} must last {$seconds} seconds.");
}
expectPremiumDuration(PremiumDuration::plusDefault(11) === 235636, 'Plus just above x10 shrinks continuously.');
expectPremiumDuration(PremiumDuration::configuredPlusDuration(100, 0) === $day, 'No override gives the speed default (one day at x100).');
expectPremiumDuration(PremiumDuration::configuredPlusDuration(100, 7 * $day) === 7 * $day, 'An override above one day wins over the speed default.');
expectPremiumDuration(PremiumDuration::configuredPlusDuration(100, 7200) === $day, 'An override below one day is clamped to one day.');
expectPremiumDuration(
    PremiumDuration::configuredDuration(2, 5 * $day, 10800) === 5 * $day,
    'An explicit duration override must take precedence over the official default.'
);
expectPremiumDuration(PremiumDuration::configuredDuration(2, 3600, 10800) === $day, 'A resource bonus override below one day is clamped to one day.');
expectPremiumDuration(PremiumDuration::configuredDuration(100, 0, 3600) === $day, 'A local default below one day is clamped to one day.');
expectPremiumDuration(PremiumDuration::configuredDuration(100, 0, 3 * $day) === 3 * $day, 'A local default above one day is kept.');
expectPremiumDuration(
    PremiumDuration::atLeastOneDay(1) === $day && PremiumDuration::atLeastOneDay(0) === $day
        && PremiumDuration::atLeastOneDay(2 * $day + 0.4) === 2 * $day + 1,
    'The floor lifts anything shorter than one day and never shortens a longer one.'
);
expectPremiumDuration(
    PremiumDuration::dailyQuestRewardSeconds() === $day,
    'Published-speed daily-quest Plus and production-bonus rewards must last one day.'
);
expectPremiumDuration(PremiumDuration::hoursToSeconds('0') === 0, 'Zero must restore automatic duration.');
expectPremiumDuration(PremiumDuration::hoursToSeconds('72') === 259200, 'Hours must persist as seconds.');
expectPremiumDuration(PremiumDuration::hoursToSeconds('8760') === 31536000, 'The maximum duration must be accepted.');
foreach (['', '-1', '1.5', '8761', '1e2'] as $invalidHours) {
    expectPremiumDuration(
        PremiumDuration::hoursToSeconds($invalidHours) === null,
        "Invalid duration input was accepted: {$invalidHours}"
    );
}

$root = dirname(__DIR__, 2);
$worldSchema = (string)file_get_contents($root . '/database/world.sql');
$update = (string)file_get_contents($root . '/game/include/update.php');
$schemaVersion = (string)file_get_contents($root . '/game/include/Game/WorldSchemaVersion.php');
$runtimeConfig = (string)file_get_contents($root . '/game/include/config/config.after.php');
$quest = (string)file_get_contents($root . '/game/include/Model/Quest.php');
$admin = (string)file_get_contents($root . '/game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
expectPremiumDuration(
    str_contains($worldSchema, "`plusAccountDurationSeconds` INT(10) UNSIGNED NOT NULL DEFAULT '0'")
        && str_contains($worldSchema, "`productionBoostDurationSeconds` INT(10) UNSIGNED NOT NULL DEFAULT '0'"),
    'Fresh worlds must include both duration override settings.'
);
expectPremiumDuration(
    preg_match('/public const CURRENT = (\d+);/', $schemaVersion, $patchVersion) === 1
        && (int)$patchVersion[1] >= 100
        && str_contains($update, 'if ($currentVersion < 100)')
        && str_contains($update, 'ADD COLUMN IF NOT EXISTS `plusAccountDurationSeconds`')
        && str_contains($update, 'ADD COLUMN IF NOT EXISTS `productionBoostDurationSeconds`'),
    'Existing worlds must be migrated by patch 100.'
);
expectPremiumDuration(
    !str_contains($runtimeConfig, 'plusAccountDurationLocalDefaultSeconds')
        && str_contains($runtimeConfig, 'productionBoostDurationLocalDefaultSeconds')
        && str_contains($runtimeConfig, 'PremiumDuration::configuredPlusDuration')
        && str_contains($runtimeConfig, 'PremiumDuration::configuredDuration'),
    'Plus follows the speed rule; the resource bonus keeps its local fallback; both apply database overrides.'
);
expectPremiumDuration(
    str_contains($quest, "if (\$type === 'plus' || \$type === 'productionBoost') {\n            \$eff = PremiumDuration::atLeastOneDay(\$eff);")
        && str_contains((string)file_get_contents($root . '/game/include/Model/DailyQuestModel.php'), 'PremiumDuration::atLeastOneDay($amount)')
        && str_contains($worldConfigDefault = (string)file_get_contents($root . '/game/include/config.php'), "'productionBoostDurationSeconds' => 86400,"),
    'Quest rewards, the daily-quest grants and the local resource-bonus default must last at least one day.'
);
expectPremiumDuration(
    str_contains($admin, 'PremiumDuration::atLeastOneDay($plusHours)')
        && str_contains($admin, 'PremiumDuration::atLeastOneDay($boostHours)')
        && str_contains($admin, 'an override of 1 to 23 hours is saved as 24 hours'),
    'Admin overrides below one day are clamped and the help text says so.'
);
expectPremiumDuration(
    substr_count($quest, 'PremiumDuration::dailyQuestRewardSeconds()') === 1
        && str_contains($quest, 'WorldSpeedProfile::isPublishedSpeed((int)getGameSpeed())'),
    'Published-speed daily rewards must be fixed while unlisted-speed behavior remains local.'
);
expectPremiumDuration(
    str_contains($admin, 'PremiumDuration::hoursToSeconds')
        && str_contains($admin, 'plusAccountDurationSeconds=?, productionBoostDurationSeconds=?, needsRestart=1')
        && str_contains($admin, 'Session::validateChecker()')
        && str_contains($admin, 'name="plusDurationHours"')
        && str_contains($admin, 'name="productionBoostDurationHours"'),
    'Admin duration settings must be validated, CSRF protected, persisted, and restart engine workers.'
);

// Prices at x2: Plus 10 gold, Gold club 200 gold.
$worldConfig = (string)file_get_contents($root . '/game/include/config.php');
expectPremiumDuration(
    str_contains($worldConfig, "'plusGold'                       => 10,")
        && str_contains($worldConfig, "'goldClubGold'                   => 200,")
        && !str_contains($worldConfig, "'plusAccountDurationSeconds'     => 7200"),
    'Plus costs 10 gold and the Gold club 200 gold, and Plus has no fixed local duration.'
);

echo "Premium duration contract passed.\n";
