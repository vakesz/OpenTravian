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
    'Tribes::ROMANS => Gid::CITY_WALL,',
    'Tribes::TEUTONS => Gid::EARTH_WALL,',
    'Tribes::GAULS => Gid::PALISADE,',
    'Tribes::EGYPTIANS => Gid::STONE_WALL,',
    'Tribes::HUNS => Gid::MAKESHIFT_WALL,',
    'Tribes::SPARTANS => Gid::DEFENSIVE_WALL,',
    'Tribes::VIKINGS => Gid::BARRICADE,',
    'u.race AS player_race',
    "T('Buildings', \$imageGid . '.title', \$fallbackLabel)",
    "count(\$gids) === 1 || !in_array(Gid::CITY_WALL, \$gids, true) ? 'building-' . \$gids[0] : 'building-city-wall'",
] as $needle) {
    if (!str_contains($source, $needle)) {
        failTaskBuildingTitleContract('Task building labels must use each tribe\'s actual defensive building without changing existing claim keys.');
    }
}

echo "Task building-title contract passed.\n";
