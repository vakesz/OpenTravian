<?php

declare(strict_types=1);

function failStatisticsBonusIdContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/statistics/bonus.php');
if ($template === false
    || str_contains($template, 'id="table"')
    || substr_count($template, 'id="bonusRewards"') !== 1
    || substr_count($template, 'id="serverStates"') !== 1
    || !str_contains($template, 'table#bonusRewards td')
    || !str_contains($template, 'table#serverStates td')) {
    failStatisticsBonusIdContract('Statistics bonus tables must have distinct IDs and retain their shared layout rule.');
}

echo "Statistics bonus ID contract passed.\n";
