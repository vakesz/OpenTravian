<?php

declare(strict_types=1);

/**
 * Expansion tab of the Residence, Palace and Command Center: one block per expansion slot. The first slot that is
 * not taken yet explains settling and conquering, the later ones wait for it.
 *
 * @var array<string, mixed> $vars
 */
$t = static fn (string $key): string => (string) T('ResidencePalace', $key);
$race = (int) $vars['race'];
$settler = (int) $vars['settlerUnit'];
$chief = (int) $vars['chiefUnit'];
$settlerTitle = htmlspecialchars((string) T('Troops', $settler . '.title'), ENT_QUOTES, 'UTF-8');
$chiefTitle = htmlspecialchars((string) T('Troops', $chief . '.title'), ENT_QUOTES, 'UTF-8');
?>
<div id="expansionTab">
    <?php foreach ($vars['slots'] as $slot): ?>
        <h4>
            <?= sprintf($t('expansionSlot'), $slot['number']); ?>
            <div class="prerequisites">
                <?= T('Buildings', 'preRequests'); ?>
                <a href="/build.php?gid=<?= (int) $vars['gid']; ?>"><?= T('Buildings', $vars['gid'] . '.title'); ?></a>
                <span class="unlockLevel"><?= T('Buildings', 'level'); ?> <?= (int) $slot['level']; ?></span>
            </div>
        </h4>
        <?php if ($slot['state'] === 'taken'): ?>
            <div class="expandedVillage">
                <?= $slot['village']; ?>
            </div>
        <?php elseif ($slot['state'] === 'free'): ?>
            <div class="freeExpansionSlot">
                <div class="trainInfo">
                    <?= sprintf($t('trainToExpand.' . $race), '<a href="/build.php?gid=' . (int) $vars['gid'] . '&amp;s=1">' . $t('Train') . '</a>'); ?>
                </div>
                <img class="unitSection settler highlight u<?= $settler; ?>Section" src="/img/spacer.gif" alt="<?= $settlerTitle; ?>" title="<?= $settlerTitle; ?>"/>
                <img class="unitSection senator highlight u<?= $chief; ?>Section" src="/img/spacer.gif" alt="<?= $chiefTitle; ?>" title="<?= $chiefTitle; ?>"/>
                <div class="noHover"><?= $t('hoverForInfo'); ?></div>
                <div class="description settler">
                    <h5 class="headline"><?= $t('settleDescription.headline'); ?></h5>
                    <span class="text"><?= $t('settleDescription.text'); ?></span>
                    <ul>
                        <li><?= sprintf($t('settleDescription.list1'), 3); ?></li>
                        <li><?= sprintf($t('settleDescription.list2'), $vars['settleCost']); ?></li>
                        <li><?= $t('settleDescription.list3'); ?></li>
                    </ul>
                </div>
                <div class="description senator">
                    <h5 class="headline"><?= $t('conquerDescription.headline'); ?></h5>
                    <span class="text"><?= $t('conquerDescription.text.' . $race); ?></span>
                    <ul>
                        <li><?= $t('conquerDescription.list1.' . $race); ?></li>
                        <li><?= $t('conquerDescription.list2'); ?></li>
                        <li><?= $t('conquerDescription.list3'); ?></li>
                    </ul>
                </div>
            </div>
            <script type="text/javascript">
                $(function () {
                    var unitSection = jQuery('.freeExpansionSlot .unitSection');
                    unitSection.on('mouseover', function () {
                        var element = jQuery(this);
                        var className = element.attr('class').split(/\s+/)[1];
                        unitSection.removeClass('highlight');
                        element.addClass('highlight');
                        jQuery('.freeExpansionSlot .noHover').hide();
                        jQuery('.freeExpansionSlot .description').hide();
                        jQuery('.freeExpansionSlot .description.' + className).show();
                    });
                });
            </script>
        <?php else: ?>
            <div class="nextExpansionSlot"><?= $t('usePriorSlot'); ?></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
