<?php

declare(strict_types=1);

use Core\Config;
use Model\ArtefactsModel;

function assertArtefactSpeedTiming(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;

try {
    // Travian support article 101: 24/16/12/8/4h.
    foreach ([
        1 => 24 * 3600,
        2 => 16 * 3600,
        3 => 12 * 3600,
        5 => 8 * 3600,
        10 => 4 * 3600,
    ] as $speed => $seconds) {
        $config->game->speed = $speed;
        assertArtefactSpeedTiming(
            (int) ArtefactsModel::getArtifactActivationTime() === $seconds,
            sprintf('Artefact activation must use the published %dx duration.', $speed)
        );
    }

    // Travian support article 102: Fool effects change every
    // 24 hours regardless of server speed on all published speeds.
    foreach ([1, 2, 3, 5, 10] as $speed) {
        $config->game->speed = $speed;
        assertArtefactSpeedTiming(
            (int) ArtefactsModel::getFoolArtifactChangeEffectInterval() === 24 * 3600,
            sprintf('Fool artefacts must retain their fixed published 24-hour interval at %dx.', $speed)
        );
    }

    // Unsupported speeds deliberately retain the local bounded policy.
    $config->game->speed = 4;
    assertArtefactSpeedTiming(
        (int) ArtefactsModel::getArtifactActivationTime() === 8 * 3600
            && (int) ArtefactsModel::getFoolArtifactChangeEffectInterval() === 6 * 3600,
        'Unlisted 4x worlds must retain the existing local artefact timing policy.'
    );
    $config->game->speed = 1000000;
    assertArtefactSpeedTiming(
        (int) ArtefactsModel::getArtifactActivationTime() === 600
            && (int) ArtefactsModel::getFoolArtifactChangeEffectInterval() === 1800,
        'One-million-speed worlds must retain bounded local artefact timing.'
    );
} finally {
    $config->game->speed = $originalSpeed;
}

echo "Artefact speed timing contract passed.\n";
