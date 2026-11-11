<?php

use Game\Tasks\TaskBoard;

/**
 * One task-board card (`div.task`). Rendered for the page and, through
 * progressive-tasks/reload, for in-place updates after Collect.
 *
 * @var array<string, mixed> $vars group, tribe, scope
 */
$e = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$group = $vars['group'];
$current = $group['current'];
$state = $group['completed'] ? 'collected' : ($current['eligible'] ? 'achieved' : 'open');
$referenceType = TaskBoard::referenceType($current);
$buildingId = TaskBoard::buildingId($current);
$collectLabel = T('Tasks', 'collect');
$collectedLabel = T('Tasks', 'collected');
// Bidi-isolated "n/d" exactly as the reference formatter writes it.
$isolate = static fn ($value): string => "\u{202D}" . (int)$value . "\u{202C}";
$fraction = static fn (int $nominator, int $denominator): string => "\u{202D}" . $isolate($nominator) . '/' . $isolate($denominator) . "\u{202C}";
$decoration = static function (string $side) use ($e): string {
    $id = substr(bin2hex(random_bytes(4)), 0, 7);
    return '<svg class="decoration ' . $side . '" viewBox="0 0 11.97 19" id="svg-' . $id . '"><style>#svg-' . $id
        . ' { --linearGradient: url(#svg-gradient-' . $id . '); }</style><defs><linearGradient class="frameGradient" id="svg-gradient-' . $id
        . '" spreadMethod="pad" gradientTransform="rotate(90)"><stop class="colorStop1" offset="5%"></stop><stop class="colorStop2" offset="13%"></stop>'
        . '<stop class="colorStop3" offset="32%"></stop><stop class="colorStop4" offset="48%"></stop><stop class="colorStop5" offset="72%"></stop>'
        . '<stop class="colorStop6" offset="93%"></stop><stop class="colorStop7" offset="100%"></stop></linearGradient></defs>'
        . '<path d="M3.94 19c-.53 0-.94-.45-.94-1.03V1.03C3 .45 3.42 0 3.94 0c.27 0 .51.11.68.33l7.11 8.47c.32.4.32.99 0 1.39l-7.11 8.47c-.18.21-.42.33-.68.33Z"></path>'
        . '<path d="M1.13 17.64C.5 17.64 0 17.08 0 16.39V2.6c0-.69.5-1.25 1.13-1.25.31 0 .61.14.83.4l5.81 6.89c.4.47.4 1.23 0 1.7l-5.81 6.9c-.21.26-.5.4-.82.4h-.01Z"></path></svg>';
};
$button = static function (string $classes) use ($e, $collectLabel, $collectedLabel): string {
    return '<button class="textButtonV2 buttonFramed collect ' . $classes . ' rectangle withText green" type="button" data-text-collected="'
        . $e($collectedLabel) . '"' . (str_contains($classes, 'disabled') ? ' disabled' : '') . '><div>'
        . $e(str_contains($classes, 'collected') ? $collectedLabel : $collectLabel) . '<i class="collectedCheckmark"></i></div></button>';
};
?>
<div class="task <?= $state; ?> group <?= $e($referenceType); ?> " data-task-key="<?= $e($group['key']); ?>"
    data-quest-type="<?= $e($referenceType); ?>" data-scope="<?= $vars['scope'] === 'general' ? 'general' : 'spawnVillage'; ?>"
    data-target-level="<?= (int)$current['milestone']; ?>"<?= $buildingId !== null ? ' data-building-id="' . $buildingId . '"' : ''; ?>>
    <div class="taskImage group"><img class="summer<?= $e(' ' . TaskBoard::imageClass($current, $vars['tribe'])); ?>" src="img/spacer.gif" alt=""></div>
    <div class="titleDecoration"></div>
    <div class="title"><?= $e($group['label']); ?></div>
    <div class="rewards">
        <div class="iconValueBoxWrapper"><div class="iconValueBox allResources allResources"><i class="allResources" aria-hidden="true"></i><span><?= (int)$current['reward']['resources']; ?></span></div></div>
        <div class="iconValueBoxWrapper"><div class="iconValueBox xp xp"><i class="xp" aria-hidden="true"></i><span><?= (int)$current['reward']['experience']; ?></span></div></div>
    </div>
    <div class="progress">
        <?php if ($group['completed']): ?>
            <?= $button('collected disabled preventAnimation'); ?>
        <?php elseif ($current['eligible']): ?>
            <?= $button('preventAnimation'); ?>
        <?php else:
            $target = max(1, (int)$current['target']);
            $progress = min($target, max(0, (int)$current['progress']));
            $percent = rtrim(rtrim(number_format(min(100, 100 * $progress / $target), 4, '.', ''), '0'), '.');
            $type = (string)$current['type'];
            $toLevel = in_array($type, ['resourceBuildingsLevelOfOneOfTheBuildings', 'resourceBuildingsAllLevels'], true);
            $label = '';
            if ($type === 'building') {
                $label .= '<span>' . $e(T('Tasks', 'level')) . ' </span>';
            } elseif (str_starts_with($type, 'populationProgress')) {
                $label .= '<span>' . $e(T('combat', 'Population')) . ' </span>';
            }
            $label .= '<span>' . $e($fraction($progress, $target)) . '</span>';
            if ($toLevel) {
                $label .= '<span> ' . $e(sprintf(T('Tasks', 'progressToLevel'), (int)$current['milestone'])) . '</span>';
            }
            ?>
            <div class="progressBar preventMobileSwipeNavigation ">
                <div class="bar"><?= $decoration('start'); ?><div class="filling primary green" style="width:<?= $e($percent); ?>%"></div><?= $decoration('end'); ?></div>
            </div>
            <div class="label"><?= $label; ?></div>
        <?php endif; ?>
    </div>
</div>
