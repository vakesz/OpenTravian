<?php

$profile = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php');
$tool = file_get_contents(dirname(__DIR__, 2) . '/tools/set-world-speed.php');
$reconcile = file_get_contents(dirname(__DIR__, 2) . '/tools/reconcile-world-speed-profile.php');
$resources = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/ResourcesHelper.php');

function assertWorldSpeedChange(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assertWorldSpeedChange(
    $profile !== false && str_contains($profile, 'public static function roundLengthDays')
        && str_contains($profile, "\$timeline['natarWwFinishAfter']"),
    'Speed profiles must expose the published official round length.'
);
assertWorldSpeedChange(
    $tool !== false && str_contains($tool, "UPDATE gameServers SET speed=?, roundLength=?")
        && str_contains($tool, 'rename($temporaryFile, $connectionFile)')
        && str_contains($tool, 'fileowner($connectionFile)')
        && str_contains($tool, 'chown($temporaryFile, $connectionOwner)')
        && str_contains($tool, "if (\$fromSpeed !== \$currentSpeed)")
        && str_contains($tool, '--from-speed must match the current installed speed')
        && str_contains($tool, 'reconcile-world-speed-profile.php'),
    'Changing an installed world speed must retain readable generated state, update settings, and reconcile world rows.'
);
assertWorldSpeedChange(
    $resources !== false
        && str_contains($resources, 'function recalculateVillageProduction')
        && str_contains($resources, 'updateVillageResources($kid, false, false)'),
    'A speed change must rebuild production without first applying a stale cached rate.'
);
assertWorldSpeedChange(
    $reconcile !== false
        && str_contains($reconcile, 'ResourcesHelper::recalculateVillageProduction')
        && str_contains($reconcile, '$fromSpeed === null'),
    'The profile reconciler must select stale-rate-safe production rebuilding after a speed change.'
);

echo "World-speed change contract passed.\n";
