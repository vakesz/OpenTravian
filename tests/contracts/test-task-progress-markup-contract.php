<?php

declare(strict_types=1);

/**
 * The progress of an open task is `.progress > .progressBar (.bar only)` followed by the sibling `.label`, in the
 * card (group) and in the level rows of the detail view, as on the reference. The Gpack places the bar in grid row
 * 2 and the label in row 3 of `.progress`; with the label inside `.progressBar` the bar column grows to 260 px and
 * the 130 px bar sticks out of the 140 px column and the dialog.
 */
function failTaskProgressMarkupContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use resources\View\PHPBatchView;

$open = [
    'key' => 'general-culture', 'label' => 'Culture point production', 'progress' => 360, 'target' => 500, 'milestone' => 500, 'stage' => 5,
    'type' => 'culturePointsProductionProgress', 'image' => 'culture', 'eligible' => false, 'claimed' => false, 'alwaysOnTop' => false,
    'reward' => ['resources' => 16080, 'experience' => 804],
];
$vars = [
    'group' => ['key' => $open['key'], 'label' => $open['label'], 'type' => $open['type'], 'image' => $open['image'],
        'milestones' => [$open], 'current' => $open, 'completed' => false],
    'tribe' => 'roman', 'scope' => 'general', 'heroBonus' => ['level' => 1, 'bonusPercent' => 0, 'nextBonusPercent' => 5],
];

$pages = ['card' => PHPBatchView::render('tasks/row', $vars), 'detail level row' => PHPBatchView::render('tasks/detail', $vars)];
$shapes = [];
foreach ($pages as $name => $html) {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
    $xpath = new DOMXPath($document);
    $progress = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " progress ")][div[contains(@class, "progressBar")]]');
    if ($progress === false || $progress->length !== 1) {
        failTaskProgressMarkupContract("The {$name} must have exactly one open progress block.");
    }
    $children = [];
    foreach ($progress->item(0)?->childNodes ?? [] as $child) {
        if ($child instanceof DOMElement) {
            $children[] = explode(' ', trim($child->getAttribute('class')))[0];
        }
    }
    if ($children !== ['progressBar', 'label']) {
        failTaskProgressMarkupContract("The {$name} progress children must be progressBar then label as siblings, got: " . implode(', ', $children));
    }
    $bar = $xpath->query('.//div[contains(@class, "progressBar")]/div', $progress->item(0));
    if ($bar === false || $bar->length !== 1 || !str_contains($bar->item(0)?->getAttribute('class') ?? '', 'bar')) {
        failTaskProgressMarkupContract("The {$name} progressBar must hold only the bar.");
    }
    $shapes[$name] = $children;
}
if ($shapes['card'] !== $shapes['detail level row']) {
    failTaskProgressMarkupContract('The card and the detail level row must share one progress structure.');
}

echo "Task progress markup contract passed.\n";
