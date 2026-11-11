<?php

declare(strict_types=1);

function failStatisticsWonderTitleContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/statistics/WonderOfTheWorld.php');
if ($template === false
    || str_contains($template, 'T("Statistics", "WonderOfTheWorld")')
    || !str_contains($template, 'T("Statistics", "tabs.WonderOfTheWorld", "Wonder of the World")')) {
    failStatisticsWonderTitleContract('The World Wonder heading must resolve to a string tab label, not the nested translation array.');
}

echo "Statistics World Wonder title contract passed.\n";
