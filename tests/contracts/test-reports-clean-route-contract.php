<?php

declare(strict_types=1);

/**
 * Contract: Reports use the reference's clean /report route family in every
 * player-facing link while the existing controller continues to receive its
 * tab state through the internal t query value.
 */
function failReportsCleanRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'game/include/mainInclude.php',
    'game/include/resources/Templates/reports/menu.php',
    'game/include/resources/Templates/reports/All.php',
    'game/include/resources/Templates/reports/overviewLayout.php',
    'game/include/resources/Templates/reports/report_layout.php',
    'game/include/Controller/ReportsCtrl.php',
    'game/include/Model/ReportsModel.php',
    'game/include/Core/Helper/BBCode.php',
    'game/include/Controller/Ajax/map/tileDetails.php',
];

$contents = [];
foreach ($sources as $source) {
    $contents[$source] = file_get_contents($root . '/' . $source);
    if ($contents[$source] === false) {
        failReportsCleanRouteContract("Cannot read {$source}.");
    }
}

foreach ([
    'game/include/resources/Templates/reports/overviewLayout.php',
    'game/include/resources/Templates/reports/report_layout.php',
    'game/include/Controller/ReportsCtrl.php',
] as $source) {
    // `&s=<page>` is the reference's back-to-page marker on report links; `t=`/`s=` tab state is legacy.
    if (preg_match('/[?&](?:t=|s=(?!\' \. max\())/', $contents[$source]) === 1) {
        failReportsCleanRouteContract("{$source} must not emit legacy report-tab query state.");
    }
}

foreach ([
    "'/report/overview' => 0",
    "'/report/offensive' => 1",
    "'/report/defensive' => 2",
    "'/report/scouting' => 3",
    "'/report/other' => 4",
    "'/report/archive' => 5",
    "'/report/surrounding' => 6",
    "setRouteParam('t', \$cleanReportRoutes[\$requestPathForReports]);",
    "\$page = 'reports.php';",
] as $needle) {
    if (!str_contains($contents['game/include/mainInclude.php'], $needle)) {
        failReportsCleanRouteContract('The clean report route map is incomplete.');
    }
}

foreach ([
    'name="ids[]"',
    'Travian.Game.Reports.processDeleteIds([',
] as $needle) {
    if (!str_contains($contents['game/include/resources/Templates/reports/overviewLayout.php'] . $contents['game/include/resources/Templates/reports/report_layout.php'], $needle)) {
        failReportsCleanRouteContract('Report selection and detail actions must use the reference-shaped ids[] payload.');
    }
}

if (!str_contains((string)file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/report/deleteBulk.php'), "\$this->integerList(\$this->body()['ids'] ?? [])")) {
    failReportsCleanRouteContract('The bulk delete API must consume the canonical selected report ids.');
}

foreach ([
    '/report/overview',
    '/report/offensive',
    '/report/defensive',
    '/report/scouting',
    '/report/other',
    '/report/archive',
    '/report/surrounding',
] as $path) {
    if (!str_contains($contents['game/include/resources/Templates/reports/menu.php'], $path)) {
        failReportsCleanRouteContract("Reports menu must emit {$path}.");
    }
}

foreach ([
    "\$rpt->vars['reportUrl'] = \$this->getReportTabPath(\$this->selectedTabIndex)",
    "'?id=' . \$row['id'] . '%7C' . \$row['private_key']",
    "\$this->getReportTabPath((int)\$this->content->vars['Tabs']['selectedTab']) .\n                '?id='",
] as $needle) {
    if (!str_contains($contents['game/include/Controller/ReportsCtrl.php'], $needle)) {
        failReportsCleanRouteContract('Report detail links and quick navigation must retain the selected clean tab route.');
    }
}

if (str_contains($contents['game/include/resources/Templates/reports/overviewLayout.php'], 'href="/report?id=')) {
    failReportsCleanRouteContract('A report opened from a tab must retain that tab in its pathname, not reset to /report.');
}

// Disabled report arrows remain anchors to preserve the reference layout, but
// under the shared root base a keyboard activation of href="#" would otherwise
// leave the report and reach the login front controller.
if (preg_match_all(
    '/<a href="#" class="reportQuickNavigation disabled" title="<\?= \$(?:previous|next)Title; \?>" onclick="return false;">/',
    $contents['game/include/resources/Templates/reports/report_layout.php']
) !== 4) {
    failReportsCleanRouteContract(
        'Every disabled report quick-navigation arrow must cancel its hash fallback.'
    );
}

foreach ($sources as $source) {
    if ($source === 'game/include/mainInclude.php') {
        continue;
    }

    if (str_contains($contents[$source], 'reports.php')) {
        failReportsCleanRouteContract("{$source} still emits a legacy reports.php player route.");
    }
}

foreach ([
    "new PageNavigator(\n            \$page,",
    "\$this->getReportTabPath(\$view->vars['selectedTabId'])",
    "'/report/surrounding'",
] as $needle) {
    if (!str_contains($contents['game/include/Controller/ReportsCtrl.php'], $needle)) {
        failReportsCleanRouteContract('Reports pagination must retain the active clean tab route.');
    }
}

$pagePrefixInitialization = strpos($contents['game/include/Controller/ReportsCtrl.php'], '$prefix = [];');
$pageNavigator = strpos($contents['game/include/Controller/ReportsCtrl.php'], 'new PageNavigator(');
if ($pagePrefixInitialization === false || $pageNavigator === false || $pagePrefixInitialization > $pageNavigator) {
    failReportsCleanRouteContract('Reports pagination must initialize its query prefix before building the navigator.');
}

foreach ([
    '<button type="submit" value="<?=$cancelLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey"',
    '<button type="submit" value="<?=$markLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey confirm negativeAction"',
    '<button type="submit" value="<?=$deleteLabel;?>" id="<?=get_button_id();?>" class="textButtonV1 grey confirm negativeAction"',
] as $needle) {
    if (!str_contains($contents['game/include/resources/Templates/reports/All.php'], $needle)) {
        failReportsCleanRouteContract('Report confirmation controls must keep the reference submit semantics.');
    }
}

echo "Reports clean route contract passed.\n";
