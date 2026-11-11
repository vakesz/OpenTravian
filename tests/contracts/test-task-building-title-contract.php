<?php

declare(strict_types=1);

function failTaskBuildingTitleContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/TasksModel.php');
if ($source === false) {
    failTaskBuildingTitleContract('Task model must be readable.');
}

foreach ([
    'private const WALL_GID_BY_RACE',
    '1 => 31,',
    '2 => 32,',
    '3 => 33,',
    '6 => 42,',
    '7 => 43,',
    '8 => 47,',
    '9 => 50,',
    'u.race AS player_race',
    "T('Buildings', \$imageGid . '.title', \$fallbackLabel)",
    "count(\$gids) === 1 ? 'building-' . \$gids[0] : 'building-city-wall'",
] as $needle) {
    if (!str_contains($source, $needle)) {
        failTaskBuildingTitleContract('Task building labels must use each tribe\'s actual defensive building without changing existing claim keys.');
    }
}

echo "Task building-title contract passed.\n";
