#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: PageNavigator always emits single-page chrome
 * (first/prev/current/next/last disabled imgs) even when total size fits one page.
 */

function failPaginatorSinglePageContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$nav = new \Core\Helper\PageNavigator(1, 1, 10, [], '/messages');
$html = (string)$nav->get();

foreach ([
    'class="paginator"',
    'class="first disabled"',
    'class="previous disabled"',
    'class="number currentPage"',
    'class="next disabled"',
    'class="last disabled"',
] as $needle) {
    if (!str_contains($html, $needle)) {
        failPaginatorSinglePageContract('Single-page paginator missing chrome: ' . $needle . ' in ' . $html);
    }
}

echo "Paginator single-page contract passed.\n";
