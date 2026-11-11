<?php

declare(strict_types=1);

/**
 * Contract: adventures never expire (the reference shows no expiry and the vendor says they stay), so free spawning
 * only rebases a lifetime total that sits above the entitlement curve and grants what is owed. The `created` column
 * records the day an adventure appeared, which decides the item tier of its reward.
 */

function failAdventureSpawnContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/game/include/Model/AdventureModel.php');
$schema = file_get_contents($root . '/database/world.sql');
$processor = file_get_contents($root . '/game/include/Model/Movements/AdventureProcessor.php');
$profile = file_get_contents($root . '/game/include/Game/WorldSpeedProfile.php');
if ($source === false || $schema === false || $processor === false || $profile === false) {
    failAdventureSpawnContract('Unable to read the adventure sources.');
}

foreach ([
    'if ($totalAdventures > $entitled)',
    'UPDATE users SET total_adventures={$entitled}',
] as $needle) {
    if (!str_contains($source, $needle)) {
        failAdventureSpawnContract('checkForNewAdventures must rebase over-grant totals.');
    }
}

foreach (['refillBought', 'getAdventureExpireTime', 'time>', 'time >', 'time<='] as $removed) {
    if (str_contains($source, $removed) || str_contains($processor, 'AND time >')) {
        failAdventureSpawnContract("Adventure expiry is gone: found '{$removed}'.");
    }
}
if (str_contains($profile, 'adventureExpireTime')) {
    failAdventureSpawnContract('WorldSpeedProfile no longer has an adventure lifetime.');
}
if (!preg_match('/CREATE TABLE `adventure` \((?:(?!CREATE TABLE).)*`created` INT\(10\) UNSIGNED NOT NULL/s', $schema)) {
    failAdventureSpawnContract('The adventure table records `created`, not an expiry time.');
}
if (!str_contains($processor, 'heroItemsTierAt(')) {
    failAdventureSpawnContract('The reward tier of an adventure follows the day it appeared.');
}

if (str_contains($source, 'min($adventures_until_now - $row[\'total_adventures\'], 10)')) {
    failAdventureSpawnContract('Remove the legacy entitlement subtraction that stalls forever when totals exceed the curve.');
}

echo "Adventure spawn contract passed.\n";
