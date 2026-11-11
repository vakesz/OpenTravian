#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: Alliance overview events use #allyInfoNews shell.
 */

function failAllianceEventsWrapper(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$bbcode = (string) file_get_contents($root . '/game/include/Core/Helper/BBCode.php');

if (!preg_match('/function news\s*\(/', $bbcode)) {
    failAllianceEventsWrapper('BBCode::news must exist.');
}

$newsStart = strpos($bbcode, 'function news(');
$newsChunk = substr($bbcode, $newsStart, 1800);
foreach ([
    "id=\"allyInfoNews\"",
    'chartHeadline',
    'Alliance Events',
    'class="events"',
    'id="allyInfoNewsPaginator"',
    "class=\"clear\"",
] as $needle) {
    if (!str_contains($newsChunk, $needle)) {
        failAllianceEventsWrapper("BBCode::news must emit Alliance events chrome ({$needle}).");
    }
}

echo "Alliance events wrapper contract passed.\n";
