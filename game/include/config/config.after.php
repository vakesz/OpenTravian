<?php
global $config;
global $connection;

use Game\WorldSpeedProfile;

/**
 * High-speed worlds intentionally use a moderated movement multiplier for
 * troops and heroes rather than a raw 1:1 copy of the economic rate (at 100x,
 * troop travel is 20x). Merchants use full economic speed (official x10 blog:
 * troops 4× / merchants 10×; product decision 2026-11-04 extends that to all
 * configured speeds). See Formulas::merchantSpeed().
 */
function travian_default_movement_speed_increase(int $speed): int
{
    return WorldSpeedProfile::movementSpeedMultiplier($speed);
}

function travian_default_daily_quest_interval(int $speed): int
{
    return WorldSpeedProfile::dailyQuestInterval($speed);
}

$worldSpeed = max(1, (int)$config->game->speed);
if ((float)$config->game->movement_speed_increase <= 0) {
    $config->game->movement_speed_increase = WorldSpeedProfile::movementSpeedMultiplier($worldSpeed);
}
if ((int)$config->game->storage_multiplier <= 0) {
    $config->game->storage_multiplier = WorldSpeedProfile::storageMultiplier($worldSpeed);
}
if ((int)$config->game->cage_multiplier <= 0) {
    $config->game->cage_multiplier = WorldSpeedProfile::cageMultiplier($worldSpeed);
}
if ((int)$config->game->trap_multiplier <= 0) {
    $config->game->trap_multiplier = WorldSpeedProfile::trapMultiplier($worldSpeed);
}
if ((int)$config->game->cranny_multiplier <= 0) {
    $config->game->cranny_multiplier = WorldSpeedProfile::crannyMultiplier($worldSpeed);
}
if ((int)$config->game->protection_time <= 0) {
    $config->game->protection_time = WorldSpeedProfile::protectionTime($worldSpeed);
}
if ((int)$config->game->oasisInitialProtectionTime <= 0) {
    $config->game->oasisInitialProtectionTime = WorldSpeedProfile::oasisInitialProtectionTime($worldSpeed);
}
if ((int)$config->game->dailyQuestInterval <= 0) {
    $config->game->dailyQuestInterval = WorldSpeedProfile::dailyQuestInterval($worldSpeed);
}
if ((float)$config->game->heroRegenerationMultiplier <= 0) {
    $config->game->heroRegenerationMultiplier = WorldSpeedProfile::heroRegenerationMultiplier($worldSpeed);
}
if ((int)$config->game->oasisAnimalCap <= 0) {
    $config->game->oasisAnimalCap = WorldSpeedProfile::oasisAnimalCap(
        $worldSpeed,
        (float)$config->game->movement_speed_increase
    );
}
if ((int)$config->game->oasisRespawnInterval <= 0) {
    $config->game->oasisRespawnInterval = WorldSpeedProfile::oasisRespawnInterval(
        $worldSpeed,
        (float)$config->game->movement_speed_increase
    );
}

// Per-world overrides chosen in the setup wizard (whitelisted keys only).
if (isset($connection['overrides']) && is_array($connection['overrides'])) {
    \Game\WorldOverrides::apply($config, $connection['overrides']);
}

/**
 * @param float $releaseTime
 * @param array<mixed> $badHours
 * @return mixed
 */
function handleBadHourReleaseTime($releaseTime, $badHours = [23, 00, 1, 2, 3, 4, 5, 6, 7, 8])
{
    $good = array_values(array_diff(range(0, 23), $badHours));
    $releaseHour = date("H", (int) $releaseTime);
    if (in_array($releaseHour, $badHours)) {
        //it's a bad hour (we need to find first good hour after that
        if (in_array($releaseHour, [23, 00])) {
            $hour = $good[sizeof($good) - 1]; // last hour
            $releaseTime -= (($releaseHour == 00 ? 24 : $releaseHour) - $hour) * 3600;
        } else {
            $hour = $good[0]; // first hour after that time
            $releaseTime += ($hour - (int) $releaseHour) * 3600;
        }
    }
    return $releaseTime;
}

if ($config->game->speed <= 20 || $config->game->round_length == 'auto') {
    require_once __DIR__ . "/timers/official.php";
} else {
    if($config->game->speed > 500){
        require_once __DIR__ . "/timers/unofficial_method_2.php";
    } else {
        require_once __DIR__ . "/timers/official.php";
    }
}
$config->game->round_length_real = ceil(($config->timers->wwPlansReleaseTime - $config->game->start_time) / 86400);
$config->heroConfig->resourcesMultiplier = getGameSpeed();
$customConfigFile = dirname(GLOBAL_CONFIG_FILE) . "/config.custom.php";
if (is_file($customConfigFile)) {
    require $customConfigFile;
}
$config->game->useMilSeconds = $config->game->speed > 20;
$config->game->useNanoseconds = $config->game->speed > 20000;
$config->heroConfig->heroItemsSettings->heroIncreaseSpeed = $config->game->movement_speed_increase;
$config->heroConfig->waterBucketsPerDay = $config->game->movement_speed_increase;
if ($config->timers->AutoFinishTime == 0) {
    $config->game->round_length = ($config->timers->wwPlansReleaseTime - $config->game->start_time) / 86400;
} else {
    $config->game->round_length = ($config->timers->AutoFinishTime - $config->game->start_time) / 86400;
}

if ($config->game->speed <= 20) {
    $config->game->round_length = WorldSpeedProfile::roundLengthDays($worldSpeed);
}
if ($config->game->speed > 20) {
    $config->game->auction_package_multiplier *= min(ceil($config->game->speed / 100), 1000);
}
$config->auction->auctionTime = WorldSpeedProfile::auctionDuration((int)$config->game->speed);
function multiply_for_other_servers(): void
{
    global $config;
    if (property_exists($config->settings, 'multiplyCalled')) {
        return;
    }
    /*if ($config->game->speed <> 5000) {
        multiply_packages(getGameSpeed() / 5000);
    }*/
}

if (property_exists($config->dynamic, 'delayTime')) {
    $config->timers->ArtifactsReleaseTime += $config->dynamic->delayTime;
    $config->timers->wwPlansReleaseTime += $config->dynamic->delayTime;
    if ($config->timers->WWConstructStartTime > 0) {
        $config->timers->WWConstructStartTime += $config->dynamic->delayTime;
        $config->timers->AutoFinishTime += $config->dynamic->delayTime;
    }
}
if (in_array($config->settings->worldId, ['dev'])) {
    require __DIR__ . "/config.dev.php";
}
if (defined("CONFIG_CUSTOM_FILE") && is_file(CONFIG_CUSTOM_FILE)) {
    require(CONFIG_CUSTOM_FILE);
}
$config->timers->ArtifactsReleaseTime = max($config->timers->ArtifactsReleaseTime, $config->dynamic->installationTime + 60);
$config->timers->wwPlansReleaseTime = max($config->timers->wwPlansReleaseTime, $config->dynamic->installationTime + 300);
multiply_for_other_servers();

if (function_exists('set_config_after')) {
    set_config_after();
}

$config->gold->productionBoostDurationLocalDefaultSeconds = (int)$config->gold->productionBoostDurationSeconds;
$config->gold->plusAccountDurationSeconds = \Game\PremiumDuration::configuredPlusDuration(
    $worldSpeed,
    (int)($config->dynamic->plusAccountDurationSeconds ?? 0)
);
$config->gold->productionBoostDurationSeconds = \Game\PremiumDuration::configuredDuration(
    $worldSpeed,
    (int)($config->dynamic->productionBoostDurationSeconds ?? 0),
    (int)$config->gold->productionBoostDurationLocalDefaultSeconds
);
