<?php
$selectedTab = $vars['selectedTab'];
echo $vars['subTabs'];
?>
<?php if ($selectedTab == 0): ?>
    <table cellpadding="1" cellspacing="1" id="troops">
        <thead>
        <tr>
            <th class="villageName"><i class="tribe<?=(int)$vars['race']; ?>_medium"></i></th>
            <?=$vars['units']; ?>
        </tr>
        </thead>
        <tbody<?= $vars['tooLargeTroops'] ? ' class="noPadding"' : ''; ?>>
        <?=$vars['content']; ?>
        <tr>
            <td colspan="12" class="empty"></td>
        </tr>
        <tr class="sum <?= ($vars['summaryLarge'] ? 'small' : ''); ?>">
            <td class="vil"><?=T("villageOverview", "Sum"); ?></td>
            <?=$vars['summary']; ?>
        </tr>
        </tbody>
    </table>
    <br>
<?php else: ?>
    <?=$vars['content']; ?>
<?php endif; ?>