<?php

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'WorldSchemaVersion', 'SupportContact', 'SmtpConfig', 'Installer/InstallInput', 'Installer/WorldInstaller'] as $installerClass) {
    require_once dirname(__DIR__, 2) . '/game/include/Game/' . $installerClass . '.php';
}

function assertInstallerValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assertInstallerValue(250, \Game\WorldSpeedProfile::roundLengthDays(1), '1x worlds must use the official World Wonder finish day.');
assertInstallerValue(160, \Game\WorldSpeedProfile::roundLengthDays(2), '2x worlds must use the official World Wonder finish day.');
assertInstallerValue(1, \Game\WorldSpeedProfile::roundLengthDays(1000), 'High-speed worlds must use the shortened round length.');
assertInstallerValue(1, \Game\WorldSpeedProfile::movementSpeedMultiplier(1), '1x worlds must retain normal troop movement.');
assertInstallerValue(2, \Game\WorldSpeedProfile::movementSpeedMultiplier(2), '2x worlds must use the official 2x movement band.');
assertInstallerValue(2, \Game\WorldSpeedProfile::movementSpeedMultiplier(5), '5x worlds must use the official 2x movement band.');
assertInstallerValue(4, \Game\WorldSpeedProfile::movementSpeedMultiplier(10), '10x worlds must use the official 4x movement band.');
assertInstallerValue(8, \Game\WorldSpeedProfile::movementSpeedMultiplier(20), '20x worlds must use the established 8x movement band.');
assertInstallerValue(20, \Game\WorldSpeedProfile::movementSpeedMultiplier(100), '100x worlds must use the 20x high-speed troop movement profile.');
assertInstallerValue(50, \Game\WorldSpeedProfile::movementSpeedMultiplier(250), '250x worlds must use the round(speed/5) troop movement band.');
assertInstallerValue(50, \Game\WorldSpeedProfile::movementSpeedMultiplier(251), '251x movement must not drop below the 250x band.');
assertInstallerValue(3000, \Game\WorldSpeedProfile::movementSpeedMultiplier(100000), 'Movement speed must stay capped at the supported high-speed limit.');
assertInstallerValue(3000, \Game\WorldSpeedProfile::movementSpeedMultiplier(1000000), 'One-million-speed worlds must retain the bounded movement profile.');
assertInstallerValue(3 * 3600, \Game\WorldSpeedProfile::protectionTime(1000000), 'Extreme speeds must keep at least a 3-hour beginner-protection window.');
assertInstallerValue(1000000, \Game\WorldSpeedProfile::storageMultiplier(1000000), 'One-million-speed worlds must retain enough warehouse and granary capacity for their economic profile.');
assertInstallerValue(825, \Game\Installer\InstallInput::effectiveProfile(['speed' => 1000000])['oasisAnimalCap'], 'One-million-speed worlds must retain the bounded oasis-animal cap.');
assertInstallerValue(50, \Game\WorldSpeedProfile::storageMultiplier(100), '100x worlds must scale warehouse and granary capacity by the high-speed profile.');
assertInstallerValue(13, \Game\WorldSpeedProfile::crannyMultiplier(100), '100x worlds must scale cranny capacity by the high-speed profile.');
assertInstallerValue(25, \Game\WorldSpeedProfile::trapMultiplier(100), '100x worlds must scale traps by the high-speed profile.');
assertInstallerValue(25, \Game\WorldSpeedProfile::cageMultiplier(100), '100x worlds must scale cages by the high-speed profile.');
assertInstallerValue(72 * 3600, \Game\WorldSpeedProfile::protectionTime(100), '100x worlds must use the high-speed beginner-protection window.');
assertInstallerValue(72 * 3600, \Game\WorldSpeedProfile::oasisInitialProtectionTime(100), '100x worlds must protect neutral oasis animals during the initial world window.');
assertInstallerValue(86400, \Game\WorldSpeedProfile::dailyQuestInterval(1), '1x worlds must keep the daily quest cycle.');
assertInstallerValue(43200, \Game\WorldSpeedProfile::dailyQuestInterval(100), '100x worlds must use the shortened high-speed quest cycle.');
assertInstallerValue(21600, \Game\WorldSpeedProfile::dailyQuestInterval(5000), '5000x worlds must use the six-hour quest cycle.');
assertInstallerValue(1.5, \Game\WorldSpeedProfile::heroRegenerationMultiplier(100), '100x worlds must use the continuous hero regeneration curve.');
assertInstallerValue(75, \Game\Installer\InstallInput::effectiveProfile(['speed' => 100])['oasisAnimalCap'], '100x worlds must use the bounded oasis-animal cap.');
assertInstallerValue(900, \Game\Installer\InstallInput::effectiveProfile(['speed' => 100])['oasisRespawnInterval'], '100x worlds must retain the oasis respawn floor.');

$installerSource = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Installer/InstallInput.php')
    . file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/setup.php')
    . file_get_contents(dirname(__DIR__, 2) . '/game/deployment/include/setup-view.php');
assertInstallerValue(true, $installerSource !== false && str_contains($installerSource, "'/^[1-9][0-9]{0,9}$/'") && str_contains($installerSource, '(int)$speed <= self::MAX_SPEED') && str_contains($installerSource, "'max' => 1000000000"), 'The installer must accept worlds up to one billion times speed and reject a larger server-side value.');
assertInstallerValue(true, $installerSource !== false && str_contains($installerSource, "6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'"), 'The installer must label tribes 6-9 as Egyptians, Huns, Spartans and Vikings.');
assertInstallerValue(true, $installerSource !== false && str_contains($installerSource, '<option value="<?= $tribeId ?>"') && str_contains($installerSource, 'name="playable_tribes[]"'), 'The installer must render the starter tribe and playable-tribe controls from one tribe list.');

$connection = \Game\Installer\WorldInstaller::buildConnection([
    'speed' => 1000,
    'starting_gold' => 250,
    'world_id' => 'test',
    'title' => 'Test World',
    'world_url' => 'http://localhost:8080/',
    'database_host' => 'db',
    'database_user' => 'root',
    'database_password' => 'test-password',
    'world_database' => 'test_world',
]);
assertInstallerValue(true, str_contains($connection, "'speed' => 1000"), 'The selected speed must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'movement_speed_increase' => 80"), 'The derived troop movement rate must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'storage_multiplier' => 500"), 'The derived storage multiplier must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'cranny_multiplier' => 125"), 'The derived cranny multiplier must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'trap_multiplier' => 250"), 'The derived trap multiplier must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'cage_multiplier' => 250"), 'The derived cage multiplier must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'oasisInitialProtectionTime' => 57600"), 'The derived oasis-protection time must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'dailyQuestInterval' => 43200"), 'The derived daily quest interval must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'heroRegenerationMultiplier' => 2.66"), 'The derived hero regeneration multiplier must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'oasisAnimalCap' => 135"), 'The derived oasis cap must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'oasisRespawnInterval' => 900"), 'The derived oasis interval must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'round_length' => 1"), 'The calculated round length must be written to the world connection.');
assertInstallerValue(true, str_contains($connection, "'startGold' => 250"), 'The selected starting gold must be written to the world connection.');

$millionConnection = \Game\Installer\WorldInstaller::buildConnection([
    'speed' => 1000000,
    'starting_gold' => 250,
    'world_id' => 'test',
    'title' => 'Test World',
    'world_url' => 'http://localhost:8080/',
    'database_host' => 'db',
    'database_user' => 'root',
    'database_password' => 'test-password',
    'world_database' => 'test_world',
]);
foreach ([
    "'speed' => 1000000",
    "'movement_speed_increase' => 3000",
    "'storage_multiplier' => 1000000",
    "'cranny_multiplier' => 125000",
    "'trap_multiplier' => 250000",
    "'cage_multiplier' => 250000",
    "'protection_time' => 10800",
    "'dailyQuestInterval' => 21600",
    "'heroRegenerationMultiplier' => 14.95",
    "'oasisAnimalCap' => 825",
    "'oasisRespawnInterval' => 900",
    "'round_length' => 1",
] as $expectedProfileValue) {
    assertInstallerValue(
        true,
        str_contains($millionConnection, $expectedProfileValue),
        'One-million-speed installer connections must persist ' . $expectedProfileValue . '.'
    );
}

$globalConfig = \Game\Installer\WorldInstaller::buildGlobalConfig([
    'index_url' => 'https://example.test/',
    'world_url' => 'https://different-world.example.test/',
    'database_host' => 'db',
    'database_user' => 'root',
    'database_password' => 'test-password',
    'account_database' => 'test_account',
]);
assertInstallerValue(true, str_contains($globalConfig, "'gpackBaseUrl' => '/gpack/'"), 'The installer must keep the Gpack same-origin.');

echo "Installer world-setting checks passed.\n";
