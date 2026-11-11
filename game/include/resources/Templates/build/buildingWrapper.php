<?php

$action = $vars['action'];
$plusAdvertising = !empty($action['plus']) && !empty($action['master']);
$hasSection2 = !$vars['soon'] && ($plusAdvertising || !empty($action['extraModuleButton']));
$durationContent = '<span class="value">' . htmlspecialchars((string)$vars['timeInString'], ENT_QUOTES, 'UTF-8') . '</span>';
$mainAction = (string)($action['main'] ?? '');

?>
<div id="contract_building<?= (int)$vars['itemId']; ?>" class="buildingWrapper">
    <div class="build_desc">
        <h2><?= $vars['count'] > 1 ? $vars['count'] . '. ' : ''; ?><?= T("Buildings", $vars['itemId'] . '.title'); ?></h2>
        <?= T("Buildings", $vars['itemId'] . '.desc'); ?>
    </div>
    <img class="build_logo building big g<?= (int)$vars['itemId']; ?> <?= htmlspecialchars((string)$vars['raceName'], ENT_QUOTES, 'UTF-8'); ?>"
         src="/img/spacer.gif"
         alt="<?= htmlspecialchars((string)T("Buildings", $vars['itemId'] . '.title'), ENT_QUOTES, 'UTF-8'); ?>"
         title="<?= htmlspecialchars((string)T("Buildings", $vars['itemId'] . '.title'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="upgradeBuilding">
        <div id="contract" class="contractWrapper">
            <?= \Game\Hero\CostChips::render($vars['cost'], $vars['freeCrop']); ?>
            <?php if (!$vars['soon'] && !empty($action['noResources'])): ?>
                <div class="upgradeBlocked">
                    <div class="errorMessage"><?= $mainAction; ?></div>
                    <?= $vars['npcButton']; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="buildingBenefits">
            <div class="wrapper">
                <?php foreach ($vars['benefits'] as $benefit): ?>
                    <div class="unit"<?= buildingBenefitTitleAttribute($benefit['icon']); ?>>
                        <i class="<?= htmlspecialchars($benefit['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span class="value"><?= htmlspecialchars($benefit['value'], ENT_QUOTES, 'UTF-8'); ?><?= $benefit['suffix']; ?></span>
                        <span class="delta"></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($vars['soon']): ?>
            <div class="upgradeButtonsContainer"><?= $vars['soonConditions']; ?></div>
        <?php else: ?>
            <div class="upgradeButtonsContainer<?= $hasSection2 ? ' section2Enabled' : ''; ?>">
                <div class="section1">
                    <?php if (empty($action['noResources']) && $mainAction !== ''): ?>
                        <?= $mainAction; ?>
                    <?php endif; ?>
                    <?= $action['master']; ?>
                    <div class="inlineIcon duration" title="<?= htmlspecialchars(T('Manual', 'duration'), ENT_QUOTES, 'UTF-8'); ?>"><div class="iconWrapper"><i class="clock_medium"></i></div><?= $durationContent; ?></div>
                    <?php if (!empty($action['waitLoop'])): ?>
                        <span class="none">(<?= T("Buildings", "waitLoop"); ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="section2">
                    <?php if ($plusAdvertising): ?>
                        <div class="plusAdvertising">
                            <?= str_replace(['<br />', '<br>', '<br/>'], '', $action['plus']); ?>
                        </div>
                    <?php endif; ?>
                    <?= str_replace(['<br />', '<br>', '<br/>'], '', $action['extraModuleButton'] ?? ''); ?>
                </div>
                <div class="clear"></div>
            </div>
        <?php endif; ?>
    </div>
</div>
