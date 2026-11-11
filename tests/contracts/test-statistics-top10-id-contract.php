<?php

declare(strict_types=1);

function failStatisticsTop10IdContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/statistics/top10.php');
$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/StatisticsCtrl.php');
if ($template === false || $controller === false
    || !str_contains($template, 'id="<?= $table[\'id\']; ?>"')
    || str_contains($template, 'top10_offHammers')
    || str_contains($template, 'top10_defHammers')) {
    failStatisticsTop10IdContract('Top 10 tables must render their id from the table list and carry no hammer tables.');
}
foreach (['top10_offs', 'top10_climbers', 'top10_defs', 'top10_raiders'] as $id) {
    if (substr_count($controller, "'" . $id . "'") < 1) {
        failStatisticsTop10IdContract('Top 10 definitions must keep the reference table id ' . $id . '.');
    }
}

echo "Statistics Top 10 ID contract passed.\n";
