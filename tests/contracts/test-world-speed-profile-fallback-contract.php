<?php

declare(strict_types=1);

function assertWorldSpeedProfileFallback(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$config = file_get_contents($root . '/game/include/config.php');
$fallbacks = file_get_contents($root . '/game/include/config/config.after.php');
$reconciliation = file_get_contents($root . '/tools/reconcile-world-speed-profile.php');

assertWorldSpeedProfileFallback(
    $config !== false && $fallbacks !== false && $reconciliation !== false,
    'Could not read the world-speed profile configuration.'
);

$profileFields = [
    'movement_speed_increase',
    'storage_multiplier',
    'cranny_multiplier',
    'trap_multiplier',
    'cage_multiplier',
    'protection_time',
    'oasisInitialProtectionTime',
    'dailyQuestInterval',
    'heroRegenerationMultiplier',
    'oasisAnimalCap',
    'oasisRespawnInterval',
];

foreach ($profileFields as $field) {
    $connectionLine = null;
    foreach (explode("\n", $config) as $line) {
        if (str_contains($line, "'$field'")) {
            $connectionLine = $line;
            break;
        }
    }

    assertWorldSpeedProfileFallback(
        $connectionLine !== null
            && str_contains($connectionLine, "isset(\$connection['$field'])")
            && str_contains($connectionLine, 'max(0,'),
        sprintf('Explicit zero %s values must reach the world-speed fallback profile.', $field)
    );
    assertWorldSpeedProfileFallback(
        str_contains($fallbacks, "\$config->game->$field <= 0"),
        sprintf('The world-speed fallback must handle zero %s values.', $field)
    );
}

assertWorldSpeedProfileFallback(
    str_contains($reconciliation, "require_once INCLUDE_PATH . 'fixes/fixStorages.php';")
        && str_contains($reconciliation, "require_once INCLUDE_PATH . 'fixes/fixAllResources.php';"),
    'Applying a changed speed profile must recalculate both storage capacity and persisted resource production.'
);

assertWorldSpeedProfileFallback(
    str_contains($reconciliation, '--from-speed=')
        && str_contains($reconciliation, 'heroExpLevelMultiplierForSpeed')
        && str_contains($reconciliation, 'SELECT uid, exp FROM hero FOR UPDATE')
        && str_contains($reconciliation, '$scaledExperience !== (int)$hero[\'exp\']'),
    'A speed-profile reconciliation must support preserving existing hero levels when the world speed changes.'
);

echo "World-speed profile fallback contract passed.\n";
