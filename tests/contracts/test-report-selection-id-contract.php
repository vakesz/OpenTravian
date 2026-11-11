<?php

declare(strict_types=1);

function failReportSelectionIdContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$overview = file_get_contents($root . '/game/include/resources/Templates/reports/All.php');
$stylesheets = [
    $root . '/assets/gpack/travian/mainPage/css_ltr/imports.css',
    $root . '/assets/gpack/travian/mainPage/css_rtl/imports.css',
];

// renders #markAll in both footers; the checkboxes keep distinct sAll1/sAll2 IDs.
if ($overview === false
    || substr_count($overview, '<div id="markAll">') !== 2
    || substr_count($overview, 'id="sAll1"') !== 1
    || substr_count($overview, 'id="sAll2"') !== 1
    || str_contains($overview, 'markAllReports')) {
    failReportSelectionIdContract('Report selection controls must use the #markAll wrapper with distinct checkbox IDs.');
}

foreach ($stylesheets as $stylesheetPath) {
    $stylesheet = file_get_contents($stylesheetPath);
    if ($stylesheet === false || !str_contains($stylesheet, 'div.reports #markAll')) {
        failReportSelectionIdContract('Report selection controls must retain their direction-aware styling.');
    }
}

echo "Report selection ID contract passed.\n";
