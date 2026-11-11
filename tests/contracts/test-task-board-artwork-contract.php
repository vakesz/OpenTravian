<?php

declare(strict_types=1);

/**
 * Every task of the board draws its own artwork. The card (`group`), the level rows and the description picture of
 * the detail view are `img.summer <type token>` elements whose picture comes only from a Gpack rule such as
 * `#tasks .taskImage.group img.populationProgress`. A missing space ("summerpopulationProgress") or a token without
 * a rule leaves the plain grass of `.taskImage:before` behind, with no failing request, so this renders the real
 * templates for every task type the board lists and resolves each picture in both text directions.
 */
function failTaskBoardArtworkContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use resources\View\PHPBatchView;

/**
 * @return list<array{selector: string, url: string}>
 */
$rules = static function (string $direction) use ($root): array {
    $dir = $root . '/assets/gpack/travian/mainPage/css_' . $direction;
    $css = (string) file_get_contents($dir . '/imports.css');
    $found = [];
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
    foreach ($blocks as $block) {
        if (!preg_match('/background-image:\s*url\(([^)]+)\)/', $block[2], $url)) {
            continue;
        }
        foreach (explode(',', $block[1]) as $selector) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
            if (str_contains($selector, 'taskImage') || preg_match('/^body img\.g\d+\./', $selector)) {
                $found[] = ['selector' => $selector, 'url' => $dir . '/' . trim($url[1], "'\" ")];
            }
        }
    }

    return $found;
};

/**
 * The file of the picture a task image gets, or null when no rule gives it one.
 *
 * @param list<array{selector: string, url: string}> $rules
 * @param list<string> $classes
 */
$artwork = static function (array $rules, string $context, array $classes) use ($root): ?string {
    $picture = null;
    foreach ($rules as $rule) {
        if (!preg_match('/^(?:body |#tasks \.taskImage\.(\w+) )img((?:\.[\w-]+)+)(:not\(\.big\))?$/', $rule['selector'], $m)) {
            continue;
        }
        if ($m[1] !== '' && $m[1] !== $context) {
            continue;
        }
        $needed = array_filter(explode('.', $m[2]));
        if (array_diff($needed, $classes) !== [] || (($m[3] ?? '') !== '' && in_array('big', $classes, true))) {
            continue;
        }
        $picture = $rule['url'];
    }

    return $picture;
};

$trees = ['ltr' => $rules('ltr'), 'rtl' => $rules('rtl')];

$task = static fn (string $key, string $type, string $image, int $stage = 1): array => [
    'key' => $key, 'label' => $key, 'progress' => 0, 'target' => 10 * $stage, 'milestone' => 10 * $stage, 'stage' => $stage,
    'type' => $type, 'image' => $image, 'eligible' => false, 'claimed' => false, 'alwaysOnTop' => false,
    'reward' => ['resources' => 100, 'experience' => 10],
];

// Every type and image the task model lists, per scope: the village scope has the "InVillage" population and culture
// tokens, the general scope the plain ones (as on the reference, read 2026-11-08).
$model = (string) file_get_contents($root . '/game/include/Model/TasksModel.php');
$expectedTokens = [
    'village' => ['populationProgressInVillage', 'culturePointsProductionProgressInVillage'],
    'general' => ['populationProgress', 'culturePointsProductionProgress', 'freeGold'],
];
foreach ($expectedTokens as $scope => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($model, "'" . $token . "'")) {
            failTaskBoardArtworkContract("The {$scope} scope must list the reference task type {$token}.");
        }
    }
}

$cases = [];
foreach (['populationProgressInVillage' => 'village', 'culturePointsProductionProgressInVillage' => 'village',
    'populationProgress' => 'general', 'culturePointsProductionProgress' => 'general', 'freeGold' => 'general'] as $type => $scope) {
    $cases[] = [$scope, $task($scope . '-' . $type, $type, $type)];
}
$cases[] = ['village', $task('even-growth', 'resourceBuildingsLevelOfOneOfTheBuildings', 'oneEachResource')];
$cases[] = ['village', $task('all-resources', 'resourceBuildingsAllLevels', 'allResources')];
foreach ([1, 2, 3, 4] as $gid) {
    $cases[] = ['village', $task('field-' . $gid, 'resourceBuildingsLevelOfOneOfTheBuildings', 'g' . $gid)];
    $cases[] = ['village', $task('all-fields-' . $gid, 'resourceBuildingsAllLevels', 'g' . $gid)];
}
foreach ([10, 11, 15, 16, 17, 18, 19, 23, 25, 8, 31, 32, 33, 42, 43, 47, 50] as $gid) {
    $cases[] = ['village', $task('building-' . $gid, 'building', 'g' . $gid)];
}

$tribes = [1 => 'roman', 2 => 'teuton', 3 => 'gaul', 6 => 'egyptian', 7 => 'hun', 8 => 'spartan', 9 => 'viking'];
$checked = 0;
foreach ($tribes as $race => $tribe) {
    foreach ($cases as [$scope, $current]) {
        $group = [
            'key' => $current['key'], 'label' => $current['label'], 'type' => $current['type'], 'image' => $current['image'],
            'milestones' => [$current], 'current' => $current, 'completed' => false,
        ];
        $vars = ['group' => $group, 'tribe' => $tribe, 'scope' => $scope, 'heroBonus' => ['level' => 1, 'bonusPercent' => 0, 'nextBonusPercent' => 5]];
        $html = [
            'group' => PHPBatchView::render('tasks/row', $vars),
            'detail' => PHPBatchView::render('tasks/detail', $vars),
        ];
        preg_match_all('/<div class="taskImage (group|level|description)"><img class="([^"]*)"/', $html['group'] . $html['detail'], $images, PREG_SET_ORDER);
        if (count($images) < 3) {
            failTaskBoardArtworkContract("{$current['key']} ({$tribe}): the card, a level row and the description must each draw a picture.");
        }
        foreach ($images as [, $context, $class]) {
            if (!preg_match('/^summer (?: ?\S+)+$/', $class) || str_contains($class, 'summer' . $current['type'])) {
                failTaskBoardArtworkContract("{$current['key']} {$context}: image class \"{$class}\" must be \"summer\", a space and the type token.");
            }
            $classes = array_values(array_filter(explode(' ', $class)));
            foreach ($trees as $direction => $treeRules) {
                $picture = $artwork($treeRules, $context, $classes);
                if ($picture === null) {
                    failTaskBoardArtworkContract("{$current['key']} {$context} ({$tribe}, {$direction}): \"{$class}\" has no Gpack rule, so it shows the plain background.");
                }
                $picture = (string) realpath($picture);
                if ($picture === '' || !is_file($picture)) {
                    failTaskBoardArtworkContract("{$current['key']} {$context} ({$tribe}, {$direction}): its picture file is missing.");
                }
                if (str_ends_with($picture, '/background.jpg')) {
                    failTaskBoardArtworkContract("{$current['key']} {$context} ({$direction}): the picture is the shared default background, not the task's own artwork.");
                }
                ++$checked;
            }
        }
    }
}

// The token per scope is what the reference emits for the same task.
foreach (['village' => 'populationProgressInVillage', 'general' => 'populationProgress'] as $scope => $token) {
    $current = $task($scope . '-population', $token, 'population');
    $row = PHPBatchView::render('tasks/row', ['group' => ['key' => 'p', 'label' => 'p', 'type' => $token, 'image' => 'population',
        'milestones' => [$current], 'current' => $current, 'completed' => false], 'tribe' => 'roman', 'scope' => $scope]);
    if (!str_contains($row, '<img class="summer ' . $token . '"')) {
        failTaskBoardArtworkContract("The {$scope} population task must carry the class {$token}.");
    }
}

echo "Task board artwork contract passed ({$checked} pictures resolved).\n";
