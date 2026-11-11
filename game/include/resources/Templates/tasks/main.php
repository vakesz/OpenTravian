<?php

$e = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$scope = $vars['scope'];
$kid = (int)$vars['kid'];
$scopeQuery = 't=' . rawurlencode($scope) . ($scope === 'village' ? '&kid=' . $kid : '');
$t = static fn (string $key): string => T('Tasks', $key);
$tribe = \Game\Tasks\TaskBoard::tribeClass((int)\Core\Session::getInstance()->getRace());
?>
<div id="tasks" class="contentV2"><div>
    <h1 class="titleInHeader"><?= $e(T('Quest', 'taskList')); ?></h1>
    <div>
    <?= \resources\View\PHPBatchView::render('layout/tabStrip', [
        'tabs' => [
            [
                'label' => sprintf($t('forVillage'), $vars['villageName'] !== '' ? $vars['villageName'] : $t('yourVillage')),
                'href' => '/tasks?t=village',
                'active' => $scope === 'village',
            ],
            ['label' => $t('general'), 'href' => '/tasks?t=general', 'active' => $scope === 'general'],
        ],
        'favor' => true,
        'wrapperClass' => 'tabFavorWrapper',
        'ids' => false,
        'reactState' => true,
        'bareAnchors' => true,
    ]); ?>
    <div class="navigationSpacer"></div>
    </div>

    <?php if (!$vars['groups']): ?>
        <p class="noTasks"><?= $e($t('empty')); ?></p>
    <?php else: ?>
        <div class="taskOverview">
            <?php foreach ($vars['groups'] as $group): ?>
                <?= \resources\View\PHPBatchView::render('tasks/row', ['group' => $group, 'tribe' => $tribe, 'scope' => $scope]); ?>
            <?php endforeach; ?>
        <?php if ($vars['pageCount'] > 1): ?>
            <div class="pagination" aria-label="<?= $e($t('pages')); ?>">
                <?php $previousPage = max(1, (int)$vars['page'] - 1); ?>
                <button type="button" class="back<?= $vars['page'] <= 1 ? ' disabled' : ''; ?>"<?= $vars['page'] <= 1 ? ' disabled' : ''; ?> aria-label="<?= $e($t('previous')); ?>" title="<?= $e($t('previous')); ?>" data-task-page-url="/tasks?<?= $e($scopeQuery . ($previousPage > 1 ? '&page=' . $previousPage : '')); ?>"></button>
                <?php for ($page = 1; $page <= $vars['pageCount']; ++$page): ?>
                    <button type="button" class="page<?= $page === $vars['page'] ? ' selected' : ''; ?>"<?= $page === $vars['page'] ? ' disabled' : ''; ?> aria-label="<?= $e(sprintf($t('page'), $page)); ?>" title="<?= $e(sprintf($t('page'), $page)); ?>" data-task-page-url="/tasks?<?= $e($scopeQuery . ($page > 1 ? '&page=' . $page : '')); ?>"></button>
                    <div class="pageIndex"><?= $page; ?></div>
                <?php endfor; ?>
                <?php $nextPage = min((int)$vars['pageCount'], (int)$vars['page'] + 1); ?>
                <button type="button" class="forward<?= $vars['page'] >= $vars['pageCount'] ? ' disabled' : ''; ?>"<?= $vars['page'] >= $vars['pageCount'] ? ' disabled' : ''; ?> aria-label="<?= $e($t('next')); ?>" title="<?= $e($t('next')); ?>" data-task-page-url="/tasks?<?= $e($scopeQuery . '&page=' . $nextPage); ?>"></button>
            </div>
        <?php else: ?>
            <div class="divider"></div>
        <?php endif; ?>

            <?= \resources\View\PHPBatchView::render('tasks/rewardBonus', ['heroBonus' => $vars['heroBonus']]); ?>
        </div>
        <div class="taskDetailTemplates" hidden aria-hidden="true">
            <?php foreach ($vars['groups'] as $group): ?>
                <template data-task-detail-template="<?= $e($group['key']); ?>"><?= \resources\View\PHPBatchView::render('tasks/detail', ['group' => $group, 'tribe' => $tribe, 'scope' => $scope, 'heroBonus' => $vars['heroBonus']]); ?></template>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></div>
<script type="text/javascript" src="/tasks-board.js?v=<?= filemtime(PUBLIC_INTERNAL_PATH . 'tasks-board.js'); ?>"></script>
