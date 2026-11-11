#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Automation;
use Core\Config;
use Core\Database\DB;

function failHighSpeedTrainingExecution(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$db = DB::getInstance();
$config = Config::getInstance();
$kid = (int)$db->fetchScalar('SELECT kid FROM units WHERE race=4 ORDER BY kid LIMIT 1');
if ($kid < 1) {
    failHighSpeedTrainingExecution('The local world needs an oasis units row for the high-speed training scratch check.');
}

$originalSpeed = $config->game->speed;
$originalMilliseconds = $config->game->useMilSeconds;
$originalNanoseconds = $config->game->useNanoseconds;
$shipsBefore = (int)$db->fetchScalar("SELECT ship4 FROM units WHERE kid=$kid");
$trainingId = 0;

try {
    $config->game->speed = 1000000;
    $config->game->useMilSeconds = true;
    $config->game->useNanoseconds = true;

    $trainingTime = 1600000;
    $now = (int)nanoseconds();
    $commence = $now - $trainingTime;
    // item_id is an unsigned TINYINT; 255 is outside every maintained training building ID.
    $db->query("INSERT INTO training (kid, nr, num, item_id, training_time, commence, end_time) VALUES ($kid, 104, 1, 255, $trainingTime, $commence, $now)");
    $trainingId = (int)$db->fetchScalar('SELECT LAST_INSERT_ID()');
    if ($trainingId < 1) {
        failHighSpeedTrainingExecution('Could not create the isolated high-speed training row.');
    }

    (new Automation())->trainingComplete($trainingId);

    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM training WHERE id=$trainingId") !== 0) {
        failHighSpeedTrainingExecution('A due 1.6ms high-speed training row was not consumed immediately by the training worker.');
    }
    if ((int)$db->fetchScalar("SELECT ship4 FROM units WHERE kid=$kid") !== $shipsBefore + 1) {
        failHighSpeedTrainingExecution('The due high-speed training row was not credited exactly once.');
    }
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->useMilSeconds = $originalMilliseconds;
    $config->game->useNanoseconds = $originalNanoseconds;
    if ($trainingId > 0) {
        $db->query("DELETE FROM training WHERE id=$trainingId");
    }
    $db->query("UPDATE units SET ship4=$shipsBefore WHERE kid=$kid");
}

echo "High-speed training execution passed (isolated row cleaned up).\n";
