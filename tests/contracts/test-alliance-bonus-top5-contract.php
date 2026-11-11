<?php

declare(strict_types=1);

/**
 * Contract: Alliance bonus contributor top-5 tables match DOM
 * (class order, thead <td>, Resources val, empty archived placeholders).
 */

function failAllianceBonusTop5Contract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$path = $root . '/game/include/Controller/Ajax/alliance/bonusOverview.php';
$source = file_get_contents($path);
if ($source === false) {
    failAllianceBonusTop5Contract('Unable to read bonusOverview.php.');
}

$methodStart = strpos($source, 'function renderContributors');
if ($methodStart === false) {
    failAllianceBonusTop5Contract('renderContributors method not found.');
}
$methodEnd = strpos($source, "\n    public ", $methodStart + 1);
$method = $methodEnd === false
    ? substr($source, $methodStart)
    : substr($source, $methodStart, $methodEnd - $methodStart);

foreach ([
    'class="top5 row_table_data"',
    'cellspacing="1"',
    'cellpadding="1"',
    "\$headClass('ra')",
    "\$headClass('pla')",
    "\$headClass('val')",
    'class="ra fc"',
    'class="val lc"',
    '<span class="archived">-</span>',
    '.&nbsp;',
] as $needle) {
    if (!str_contains($method, $needle)) {
        failAllianceBonusTop5Contract('renderContributors missing piece: ' . $needle);
    }
}

if (preg_match('/<th[\s>]/', $method) === 1) {
    failAllianceBonusTop5Contract('renderContributors thead must use <td>, not <th>.');
}
if (str_contains($method, 'class="re"') || str_contains($method, "class='re'")) {
    failAllianceBonusTop5Contract('Resources column must use class val, not re.');
}
if (str_contains($method, 'row_table_data top5')) {
    failAllianceBonusTop5Contract('Table class order must be top5 row_table_data.');
}
if (!str_contains($method, "profileUrl(") || !str_contains($method, 'hl own')) {
    failAllianceBonusTop5Contract('Contributor profile links and own-rank row must remain.');
}

fwrite(STDOUT, "Alliance bonus top5 contract passed.\n");
