#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Core\Database\DB;
use Game\QuickLink;

function failHighSpeedQueuePresentation(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$quickLink = file_get_contents($root . '/game/include/Game/QuickLink.php');
$dorf3 = file_get_contents($root . '/game/include/Controller/VillageStatisticsCtrl.php');
$launcher = file_get_contents($root . '/game/include/Core/Jobs/Launcher.php');
if (in_array(false, [$quickLink, $dorf3, $launcher], true)) {
    failHighSpeedQueuePresentation('High-speed queue presentation sources must be readable.');
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$db = DB::getInstance();
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;
$trainingId = 0;

try {
    $config->game->useMilSeconds = true;
    $config->game->useNanoseconds = false;
    if (trainingTimeDisplaySeconds(715) !== 1 || trainingTimeDisplaySeconds(0) !== 0) {
        failHighSpeedQueuePresentation('A positive sub-second millisecond training duration must remain visible as one second.');
    }
    if (secondsToString(5943, true) !== '0:00:06' || str_contains(secondsToString(5943, true), 'ms')) {
        failHighSpeedQueuePresentation('Training-time labels must show whole seconds, never a (+ms) remainder.');
    }

    $config->game->useMilSeconds = false;
    $config->game->useNanoseconds = true;
    if (trainingTimeDisplaySeconds(71_595) !== 1 || trainingTimeDisplaySeconds(1_000_000_000) !== 1) {
        failHighSpeedQueuePresentation('A positive sub-second nanosecond training duration must remain visible as one second.');
    }
    if (secondsToString(1_500_000_000, true) !== '0:00:02' || str_contains(secondsToString(71_595, true), 'ms')) {
        failHighSpeedQueuePresentation('Nanosecond training-time labels must also show whole seconds only.');
    }

    if (!str_contains(appendTimer(-1), 'value="0">0:00:00</span>')) {
        failHighSpeedQueuePresentation('An overdue server-rendered countdown must clamp to zero instead of rendering a negative clock.');
    }

    $kid = (int)$db->fetchScalar('SELECT kid FROM units WHERE race=4 ORDER BY kid LIMIT 1');
    if ($kid < 1) {
        failHighSpeedQueuePresentation('The local world needs an oasis units row for the high-speed quick-link scratch check.');
    }
    $duration = 71_595;
    $now = (int)nanoseconds();
    $db->query("INSERT INTO training (kid, nr, num, item_id, training_time, commence, end_time) VALUES ($kid, 104, 1, 255, $duration, " . ($now + $duration) . ", " . ($now + $duration) . ')');
    $trainingId = (int)$db->fetchScalar('SELECT LAST_INSERT_ID()');
    $trainingText = (new ReflectionMethod(QuickLink::class, 'trainingText'))->invoke(null, $kid, 255);
    if (str_contains($trainingText, T('inGame', 'noTroopInBeingTrained')) || !str_contains($trainingText, '0:00:01')) {
        failHighSpeedQueuePresentation('A persisted positive sub-second queue must remain visible in the training quick link.');
    }
} finally {
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
    if ($trainingId > 0) {
        $db->query("DELETE FROM training WHERE id=$trainingId");
    }
}

foreach ([
    'trainingTimeDisplaySeconds((new TrainingModel())->getTotalTrainingTime($kid, $gid))',
    "T('inGame', 'noTroopInBeingTrained')",
] as $needle) {
    if (!str_contains($quickLink, $needle)) {
        failHighSpeedQueuePresentation("QuickLink must preserve active sub-second training queues ({$needle}).");
    }
}
if (!str_contains($dorf3, 'secondsToString(trainingTimeDisplaySeconds($trainingTime))')) {
    failHighSpeedQueuePresentation('Dorf3 troop tooltips must format a live high-resolution queue through the shared display helper.');
}
if (!str_contains($launcher, "new Job('marketComplete:researchComplete', getGameSpeed() > 20 ? 1 : 3, \$job)")) {
    failHighSpeedQueuePresentation('High-speed research must be consumed on a one-second cadence.');
}

echo "High-speed queue presentation contract passed.\n";
