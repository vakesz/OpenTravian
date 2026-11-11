<?php

use Game\Tasks\TaskBoard;

/**
 * In-place detail state for one progressive task group. The reference keeps
 * this beneath the same task header/tab strip rather than changing routes.
 *
 * @var array<string, mixed> $vars group, tribe, scope, heroBonus
 */
$e = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$group = $vars['group'];
$tribe = (string)$vars['tribe'];
$scope = $vars['scope'] === 'general' ? 'general' : 'village';
$details = TaskBoard::detailMilestones($group);
$pages = array_chunk($details, 3);
$pages = $pages === [] ? [[]] : $pages;
$imageTask = $group['current'] ?? ($details[0] ?? null);
$key = (string)$group['key'];

$isolate = static fn ($value): string => "\u{202D}" . (int)$value . "\u{202C}";
$fraction = static fn (int $nominator, int $denominator): string => "\u{202D}" . $isolate($nominator) . '/' . $isolate($denominator) . "\u{202C}";
$decoration = static function (string $side): string {
    $id = substr(bin2hex(random_bytes(4)), 0, 7);
    return '<svg class="decoration ' . $side . '" viewBox="0 0 11.97 19" id="svg-' . $id . '"><style>#svg-' . $id
        . ' { --linearGradient: url(#svg-gradient-' . $id . '); }</style><defs><linearGradient class="frameGradient" id="svg-gradient-' . $id
        . '" spreadMethod="pad" gradientTransform="rotate(90)"><stop class="colorStop1" offset="5%"></stop><stop class="colorStop2" offset="13%"></stop>'
        . '<stop class="colorStop3" offset="32%"></stop><stop class="colorStop4" offset="48%"></stop><stop class="colorStop5" offset="72%"></stop>'
        . '<stop class="colorStop6" offset="93%"></stop><stop class="colorStop7" offset="100%"></stop></linearGradient></defs>'
        . '<path d="M3.94 19c-.53 0-.94-.45-.94-1.03V1.03C3 .45 3.42 0 3.94 0c.27 0 .51.11.68.33l7.11 8.47c.32.4.32.99 0 1.39l-7.11 8.47c-.18.21-.42.33-.68.33Z"></path>'
        . '<path d="M1.13 17.64C.5 17.64 0 17.08 0 16.39V2.6c0-.69.5-1.25 1.13-1.25.31 0 .61.14.83.4l5.81 6.89c.4.47.4 1.23 0 1.7l-5.81 6.9c-.21.26-.5.4-.82.4h-.01Z"></path></svg>';
};
$description = TaskBoard::detailDescription($group);
?>
<div class="taskDetailview" data-task-detail-key="<?= $e($key); ?>">
    <div class="taskDescription">
        <div class="imageFrame">
            <?php if (is_array($imageTask)): ?>
                <div class="taskImage description"><img class="summer<?= $e(' ' . TaskBoard::imageClass($imageTask, $tribe, 'description')); ?>" src="img/spacer.gif" alt=""></div>
            <?php endif; ?>
        </div>
        <div class="description">
            <div class="title"><?= $e($group['label']); ?></div>
            <?php if ($description !== ''): ?>
                <div><?= $e($description); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach ($pages as $pageIndex => $page): ?>
        <?php foreach ($page as $milestone): ?>
            <?php
            $state = !empty($milestone['claimed']) ? 'collected' : (!empty($milestone['eligible']) ? 'achieved' : 'open');
            $referenceType = TaskBoard::referenceType($milestone);
            $buildingId = TaskBoard::buildingId($milestone);
            $target = max(1, (int)$milestone['target']);
            $progress = min($target, max(0, (int)$milestone['progress']));
            $percent = rtrim(rtrim(number_format(min(100, 100 * $progress / $target), 4, '.', ''), '0'), '.');
            $toLevel = in_array((string)$milestone['type'], ['resourceBuildingsLevelOfOneOfTheBuildings', 'resourceBuildingsAllLevels'], true);
            ?>
            <div class="task <?= $state; ?> level <?= $e($referenceType); ?> " data-task-detail-page="<?= $pageIndex; ?>"
                data-quest-type="<?= $e($referenceType); ?>" data-scope="<?= $scope === 'general' ? 'general' : 'spawnVillage'; ?>"
                data-target-level="<?= (int)$milestone['milestone']; ?>"<?= $buildingId !== null ? ' data-building-id="' . $buildingId . '"' : ''; ?><?= $pageIndex > 0 ? ' hidden' : ''; ?>>
                <div class="taskImage level"><img class="summer<?= $e(' ' . TaskBoard::imageClass($milestone, $tribe, 'level')); ?>" src="img/spacer.gif" alt=""></div>
                <div class="titleDecoration"></div>
                <div class="title"><?= $e(TaskBoard::detailTitle($group, $milestone)); ?></div>
                <div class="rewards">
                    <div class="iconValueBoxWrapper"><div class="iconValueBox allResources allResources"><i class="allResources" aria-hidden="true"></i><span><?= (int)$milestone['reward']['resources']; ?></span></div></div>
                    <div class="iconValueBoxWrapper"><div class="iconValueBox xp xp"><i class="xp" aria-hidden="true"></i><span><?= (int)$milestone['reward']['experience']; ?></span></div></div>
                </div>
                <div class="progress">
                    <?php if (!empty($milestone['claimed'])): ?>
                        <button class="textButtonV2 buttonFramed collect collected disabled preventAnimation rectangle withText green" type="button" data-text-collected="<?= $e(T('Tasks', 'collected')); ?>" disabled><div><?= $e(T('Tasks', 'collected')); ?><i class="collectedCheckmark"></i></div></button>
                    <?php elseif (!empty($milestone['eligible'])): ?>
                        <button class="textButtonV2 buttonFramed collect preventAnimation rectangle withText green" type="button" data-text-collected="<?= $e(T('Tasks', 'collected')); ?>"><div><?= $e(T('Tasks', 'collect')); ?><i class="collectedCheckmark"></i></div></button>
                    <?php else: ?>
                        <?php
                        $label = '';
                        if ((string)$milestone['type'] === 'building') {
                            $label .= '<span>' . $e(T('Tasks', 'level')) . ' </span>';
                        } elseif (str_starts_with((string)$milestone['type'], 'populationProgress')) {
                            $label .= '<span>' . $e(T('combat', 'Population')) . ' </span>';
                        }
                        $label .= '<span>' . $e($fraction($progress, $target)) . '</span>';
                        if ($toLevel) {
                            $label .= '<span> ' . $e(sprintf(T('Tasks', 'progressToLevel'), (int)$milestone['milestone'])) . '</span>';
                        }
                        ?>
                        <div class="progressBar preventMobileSwipeNavigation ">
                            <div class="bar"><?= $decoration('start'); ?><div class="filling primary green" style="width:<?= $e($percent); ?>%"></div><?= $decoration('end'); ?></div>
                        </div>
                        <div class="label"><?= $label; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php if (count($pages) > 1): ?>
        <nav class="pagination taskDetailPagination" aria-label="<?= $e(T('Tasks', 'pages')); ?>">
            <button type="button" class="back disabled" disabled aria-label="<?= $e(T('Tasks', 'previous')); ?>" title="<?= $e(T('Tasks', 'previous')); ?>" data-task-detail-page="0"></button>
            <?php foreach ($pages as $pageIndex => $_): ?>
                <button type="button" class="page<?= $pageIndex === 0 ? ' selected' : ''; ?>"<?= $pageIndex === 0 ? ' disabled' : ''; ?> aria-label="<?= $e(sprintf(T('Tasks', 'page'), $pageIndex + 1)); ?>" data-task-detail-page="<?= $pageIndex; ?>"></button>
                <div class="pageIndex"><?= $pageIndex + 1; ?></div>
            <?php endforeach; ?>
            <button type="button" class="forward" aria-label="<?= $e(T('Tasks', 'next')); ?>" title="<?= $e(T('Tasks', 'next')); ?>" data-task-detail-page="1"></button>
        </nav>
    <?php else: ?>
        <div class="divider"></div>
    <?php endif; ?>

    <?= \resources\View\PHPBatchView::render('tasks/rewardBonus', ['heroBonus' => $vars['heroBonus']]); ?>
    <div class="buttonWrapper"><button class="textButtonV2 buttonFramed backToOverview rectangle withText green" type="button"><div><?= $e(T('Tasks', 'backToOverview')); ?></div></button></div>
</div>
