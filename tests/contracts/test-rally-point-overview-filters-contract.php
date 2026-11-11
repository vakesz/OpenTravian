<?php

declare(strict_types=1);

/**
 * Rally Point overview filters share the reference page's lightweight,
 * right-aligned filter wrapper. Reintroducing the retired boxes chrome moves
 * the controls to the left and shortens the overview by six pixels.
 */
function failRallyPointOverviewFiltersContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/rallypoint/overview.php');

if ($controller === false || $template === false) {
    failRallyPointOverviewFiltersContract('Cannot read the Rally Point overview implementation.');
}

if (!str_contains($controller, "new PHPBatchView('rallypoint/overview')")
    || !str_contains($template, '<div class="data rallyPointOverviewContainer">')
    || !str_contains($template, '<div class="filterWrapper">')
    || substr_count($template, '<div class="filterWrapper">') !== 1
    || !str_contains($template, "if (\$vars['filter'] > 0 && \$vars['filter'] < 3):")
    || str_contains($template, 'boxesColor gray')
    || str_contains($template, 'class="filter">')) {
    failRallyPointOverviewFiltersContract(
        'Rally Point overview filters must use the canonical right-aligned filterWrapper markup without retired boxes chrome.'
    );
}

$model = file_get_contents($root . '/game/include/Model/RallyPoint/RallyPointModel.php');
if ($model === false
    || !str_contains($template, '<img src="/img/spacer.gif" class="filterCategory sortOrder">')
    || !str_contains($template, '&amp;filter=2&amp;sortOrder=')
    || str_contains($template, 'subFilterCategory6')
    || str_contains($controller . $model, '[6]')
    || !str_contains($model, "'start_time DESC, id DESC'")) {
    failRallyPointOverviewFiltersContract(
        'The outgoing list has the reference sub-filters 4 and 5 and a sortOrder button (earliestArrivingFirst, latestSentFirst), and no sub-filter 6.'
    );
}

echo "Rally Point overview filters contract passed.\n";
