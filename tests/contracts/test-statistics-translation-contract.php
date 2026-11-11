<?php

declare(strict_types=1);

function failStatisticsTranslationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$table = file_get_contents($root . '/game/include/resources/Templates/statistics/table.php');
$worldWonder = file_get_contents($root . '/game/include/resources/Templates/statistics/WonderOfTheWorld.php');
if ($controller === false || $table === false || $worldWonder === false) {
    failStatisticsTranslationContract('Statistics sources must be readable.');
}

$main = file_get_contents($root . '/game/include/resources/Templates/statistics/main.php');
$enUs = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($main === false || str_contains($main, 'statisticsV2')) {
    failStatisticsTranslationContract('Statistics must not wrap its content in a local-only statisticsV2 root (has none).');
}
if ($enUs === false
    || !str_contains($enUs, "\$Definition['Global']['General']['ok'] = 'OK';")) {
    failStatisticsTranslationContract('en-US Global.General.ok must be OK for the statistics search button.');
}
foreach ([
    "\$Definition['Statistics']['errors']['userNotFound'] = 'The player %s does not exist.';",
    "\$Definition['Statistics']['errors']['allianceNotFound'] = 'The alliance %s does not exist.';",
    "\$Definition['Statistics']['errors']['villageNotFound'] = 'The village %s does not exist.';",
] as $expected) {
    if (!str_contains($enUs, $expected)) {
        failStatisticsTranslationContract('Statistics search error copy must match ' . $expected);
    }
}
if (preg_match('/value="ok"/i', $table) === 1
    || preg_match('/>\s*ok\s*</', $table) === 1) {
    failStatisticsTranslationContract('Statistics search must not hardcode a lowercase ok button label.');
}

foreach ([
    '<table class="transparent">',
    'class="row_table_data borderGap"',
    'inputmode="numeric"',
    'General.or',
    'action="<?= $formAction; ?>"',
] as $needle) {
    if (!str_contains($table, $needle)) {
        failStatisticsTranslationContract('Statistics search must match the reference transparent table + borderGap layout.');
    }
}
if (str_contains($table, 'statistiken.php') || !str_contains($table, '<div class="searchWrapper">')
    || !str_contains($table, '<h4 class="roundV2">') || !str_contains($table, '<tbody class="hoverable">')) {
    failStatisticsTranslationContract('Statistics search must post to clean nested paths inside the reference searchWrapper, below an h4.roundV2 and a tbody.hoverable.');
}

if (str_contains($table, 'boxes boxesColor gray') || str_contains($table, 'padding: 0 10px\n                                    px')) {
    failStatisticsTranslationContract('Statistics search must not retain malformed legacy box markup.');
}

foreach ([
    [$controller, "\$statisticsTabLabel('subTabs.overview', 'Overview')"],
    [$controller, "\$statisticsTabLabel('subTabs.Top 10', 'Top 10')"],
    [$controller, 'T("Statistics", "tabs.alliances", "Alliance")'],
    [$controller, 'T("Statistics", "tabs.players", "Player(s)")'],
    [$controller, 'T("Statistics", "points", "Points")'],
    [$table, 'T("Global", "General.ok", "OK")'],
    [$table, 'T("Global", "General.or", "or")'],
    [$controller, 'T("Statistics", "WonderOfTheWorld.name", "Name")'],
    [$controller, 'T("Statistics", "WonderOfTheWorld.level", "Level")'],
    [$worldWonder, 'T("Statistics", "tabs.WonderOfTheWorld", "Wonder of the World")'],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        failStatisticsTranslationContract('Statistics labels must use their nested translation paths with English fallbacks.');
    }
}

echo "Statistics translation contract passed.\n";
