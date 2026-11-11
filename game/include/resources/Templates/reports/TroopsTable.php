<?php $roleClass = $vars['isDefender'] ? 'defender' : 'attacker'; ?>
<div class="role <?=$roleClass;?>">
    <div class="header">
        <div class="avatar">
            <i class="tribeIcon bigTribe<?=$vars['race'];?>"> </i>
            <?=\Core\Helper\CombatIcon::svg($vars['isDefender'] ? 'svg-defend.svg' : 'svg-attack.svg', '', $vars['role']);?>
        </div>
        <h2><?=$vars['role'];?></h2>
        <div class="outcome">
            <div class="arrowWrapper">
                <svg class="outcomeArrow" viewBox="0 0 20 20" preserveAspectRatio="none">
                    <path d="M0 0L20 10L0 20z"></path>
                </svg>
            </div>
            <?=$vars['outcomeIcons'];?>
        </div>
    </div>
    <div class="troopHeadline">
        <div>
            <?=$vars['troopHeadline'];?>
        </div>
        <div class="toolList">
            <?php if($vars['showSimulateButton']):?>
                <a class="iconButton" title="<?=T("Reports", "Combat simulator");?>" href="/build.php?id=39&amp;tt=3&amp;bid=<?=$vars['reportId'];?>"><i class="simulate"></i></a>
            <?php endif;?>
            <?php if($vars['showRepeatButton']):?>
                <a class="iconButton" title="<?=T("Reports", "Repeat attack");?>" href="/build.php?id=39&amp;tt=2&amp;bid=<?=$vars['reportId'];?>"><i class="repeatAttack"></i></a>
            <?php endif;?>
            <?php if ($vars['showAddToFarmList']): ?>
                <?= $vars['addToFarmListHTML']; ?>
            <?php endif; ?>
        </div>
    </div>
    <table>
        <?=$vars['tbody'];?>
    </table>
    <?=$vars['other'];?>
</div>
