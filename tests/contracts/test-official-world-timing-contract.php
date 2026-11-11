<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';

function assertOfficialWorldTiming(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function officialWorldTimingFor(int $speed): array
{
    global $config, $globalConfig;

    $config = (object)[
        'game' => (object)[
            'speed' => $speed,
            'start_time' => 0,
        ],
        'timers' => (object)[],
    ];
    $globalConfig = [];
    include dirname(__DIR__, 2) . '/game/include/config/timers/official.php';

    return [
        'artifacts' => (int)$config->timers->ArtifactsReleaseTime,
        'plans' => (int)$config->timers->wwPlansReleaseTime,
        'natarStart' => (int)$config->timers->WWConstructStartTime,
        'levelInterval' => (int)$config->timers->WWUpLvlInterval,
        'natarFinish' => (int)$config->timers->AutoFinishTime,
    ];
}

$automation = file_get_contents(dirname(__DIR__, 2) . '/game/include/Core/Automation.php');
assertOfficialWorldTiming(
    $automation !== false
        && str_contains($automation, 'WorldSpeedProfile::officialRoundTimeline')
        && str_contains($automation, "\$timeline['registrationCloseAfter']"),
    'Automatic registration closing must use the official speed-profile milestone.'
);

foreach ([1, 2, 3, 5, 10] as $speed) {
    assertOfficialWorldTiming(
        \Game\WorldSpeedProfile::dailyQuestInterval($speed) === 24 * 3600,
        sprintf('Daily quests must retain their 24-hour reset on %dx worlds.', $speed)
    );
}

assertOfficialWorldTiming(
    \Game\WorldSpeedProfile::dailyQuestInterval(100) === 12 * 3600
        && \Game\WorldSpeedProfile::dailyQuestInterval(1000) === 12 * 3600
        && \Game\WorldSpeedProfile::dailyQuestInterval(5000) === 6 * 3600,
    'Custom high-speed worlds must use the shorter 12-hour or 6-hour quest cycle.'
);

foreach ([
    1 => [70, 90, 180, 180, 16.8 * 3600, 250],
    2 => [35, 45, 90, 90, 16.8 * 3600, 160],
    3 => [28, 30, 60, 60, 8.4 * 3600, 95],
    5 => [15, 18, 36, 36, 8.4 * 3600, 71],
    10 => [7, 9, 18, 18, 4.2 * 3600, 35.5],
] as $speed => [$registrationClose, $artifacts, $plans, $natarStart, $levelInterval, $natarFinish]) {
    $timing = officialWorldTimingFor($speed);
    $timeline = \Game\WorldSpeedProfile::officialRoundTimeline($speed);
    assertOfficialWorldTiming(
        $timeline !== null
            && $timeline['registrationCloseAfter'] === (int)($registrationClose * 86400)
            && \Game\WorldSpeedProfile::roundLengthDays($speed) === (int)ceil($natarFinish)
            && $timing['artifacts'] === (int)($artifacts * 86400)
            && $timing['plans'] === (int)($plans * 86400)
            && $timing['natarStart'] === (int)($natarStart * 86400)
            && $timing['levelInterval'] === (int)$levelInterval
            && $timing['natarFinish'] === (int)($natarFinish * 86400),
        sprintf('Official %dx timer milestones must match the OpenTravian speed table.', $speed)
    );
}

$customTiming = officialWorldTimingFor(20);
assertOfficialWorldTiming(
    $customTiming === [
        'artifacts' => 5 * 86400,
        'plans' => 10 * 86400,
        'natarStart' => (int)(12.5 * 86400),
        'levelInterval' => (int)ceil(86400 / 20),
        'natarFinish' => (int)(17.5 * 86400),
    ],
    'Unsupported custom speeds must retain the existing timer behavior.'
);

echo "Official world timing contract passed.\n";
