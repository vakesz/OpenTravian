<?php

global $config, $globalConfig;
$timeline = \Game\WorldSpeedProfile::officialRoundTimeline((int)$config->game->speed);
if ($timeline !== null) {
    $config->timers->ArtifactsReleaseTime = $config->game->start_time + $timeline['artifactsReleaseAfter'];
    $config->timers->wwPlansReleaseTime = $config->game->start_time + $timeline['plansReleaseAfter'];
    $config->timers->WWConstructStartTime = $config->timers->wwPlansReleaseTime;
    $config->timers->WWUpLvlInterval = intdiv(
        $timeline['natarWwFinishAfter'] - $timeline['plansReleaseAfter'],
        100
    );
    $config->timers->AutoFinishTime = $config->game->start_time + $timeline['natarWwFinishAfter'];
} elseif ($config->game->speed == 50) {
    $config->timers->ArtifactsReleaseTime = $config->game->start_time + 7 * 86400;
    $config->timers->wwPlansReleaseTime = $config->game->start_time + 14 * 86400;
    $config->timers->WWConstructStartTime = $config->game->start_time + 18 * 86400;
    $config->timers->WWUpLvlInterval = 2592;
    $config->timers->AutoFinishTime = ceil($config->timers->WWConstructStartTime + 100 * $config->timers->WWUpLvlInterval);
} else {
    $config->timers->ArtifactsReleaseTime = $config->game->start_time + ceil(100 / $config->game->speed * 86400);
    $config->timers->wwPlansReleaseTime = $config->game->start_time + ceil(200 / $config->game->speed * 86400);
    $config->timers->WWConstructStartTime = $config->game->start_time + ceil(250 / $config->game->speed * 86400);
    $config->timers->WWUpLvlInterval = ceil(86400 / $config->game->speed);
    $config->timers->AutoFinishTime = ceil($config->timers->WWConstructStartTime + 100 * $config->timers->WWUpLvlInterval);
}
