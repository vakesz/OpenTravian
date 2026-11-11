<?php

declare(strict_types=1);

function failReportsNavigationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/reports/menu.php');
$translations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if ($controller === false || $template === false || $translations === false) {
    failReportsNavigationContract('Unable to read the reports navigation sources.');
}

foreach ([
    "1 => T('Reports', 'Tabs.Attacks')",
    "2 => T('Reports', 'Tabs.Defense')",
    "3 => T('Reports', 'Tabs.Spy')",
    "6 => T('Reports', 'Tabs.Surrounding')",
    "\$reportTabLabels[\$selectedTabIndex] ?? \$reportTabLabels[0]",
] as $requiredFragment) {
    if (!str_contains($controller, $requiredFragment)) {
        failReportsNavigationContract("Reports favourite-tab text is missing {$requiredFragment}.");
    }
}

foreach ([
    "\$Definition['Reports']['Tabs']['Attacks'] = 'Offensive';",
    "\$Definition['Reports']['Tabs']['Defense'] = 'Defensive';",
    "\$Definition['Reports']['Tabs']['Spy'] = 'Scouting';",
] as $requiredFragment) {
    if (!str_contains($translations, $requiredFragment)) {
        failReportsNavigationContract("Reports English tab label is missing {$requiredFragment}.");
    }
}

if (str_contains($template, 'reports.php?t=7') || str_contains($controller, 'function management()')) {
    failReportsNavigationContract('Reports must not retain the obsolete Management tab or controller path.');
}

foreach ([
    '/report/offensive',
    '/report/defensive',
    '/report/scouting',
    '/report/surrounding',
] as $requiredFragment) {
    if (!str_contains($template, $requiredFragment)) {
        failReportsNavigationContract("Reports navigation is missing {$requiredFragment}.");
    }
}

// Detail-page previous/next controls reuse the selected overview tab.  "Other"
// is tab 4; Archive is tab 5.  Do not let one route walk into the other.
if (!str_contains($controller, "elseif (\$this->content->vars['Tabs']['selectedTab'] == 5) {\n            return 'archive=1 AND deleted=0';")
    || str_contains($controller, "elseif (\$this->content->vars['Tabs']['selectedTab'] == 4) {\n            return 'archive=1 AND deleted=0';")) {
    failReportsNavigationContract('Report-detail previous/next navigation must apply the archive condition only to the Archive tab.');
}

echo "Reports native tabs contract passed.\n";
