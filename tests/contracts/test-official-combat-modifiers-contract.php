<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$updater = file_get_contents($root . '/game/include/update.php');
$schemaVersion = file_get_contents($root . '/game/include/Game/WorldSchemaVersion.php');
if ($updater === false || $schemaVersion === false) {
    throw new RuntimeException('Cannot read the world update sources.');
}
if (preg_match('/public const CURRENT = (\d+);/', $schemaVersion, $patchVersion) !== 1
    || (int)$patchVersion[1] < 102
    || !str_contains($updater, 'if ($currentVersion < 102)')
    || !str_contains($updater, 'DROP COLUMN IF EXISTS `atkBonusExpireTime`, DROP COLUMN IF EXISTS `defBonusExpireTime`')) {
    $failures[] = 'Patch 102 must remove both retired expiry columns idempotently.';
}
foreach ([
    'game/include/Model/BattleModel.php',
    'game/include/Model/BattleSetter.php',
    'game/include/Controller/Ajax/premium.php',
    'game/include/config.php',
    'database/world.sql',
] as $file) {
    $source = file_get_contents($root . '/' . $file);
    if ($source === false) {
        throw new RuntimeException("Cannot read $file");
    }
    foreach (['atkBonus', 'defBonusExpireTime', 'defBonusRate', 'function defBonus(', 'extraPower'] as $retired) {
        if (str_contains($source, $retired)) {
            $failures[] = "$file retains retired paid combat feature $retired";
        }
    }
}
foreach (['game/include/Model/BattleModel.php', 'game/include/Controller/RallyPoint/Simulator.php'] as $file) {
    $source = file_get_contents($root . '/' . $file);
    if (str_contains($source, "\$offense['b'] *= 1.015")) {
        $failures[] = "$file retains the unexplained Natar adjustment";
    }
    if (!str_contains($source, '// natarian horns')) {
        $failures[] = "$file must preserve the official Natarian horn effect";
    }
}
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Official combat modifier removal contract passed.\n";
