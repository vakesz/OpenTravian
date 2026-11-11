<?php $ltr = static fn($value): string => "\u{202D}" . $value . "\u{202C}"; ?>
<img src="img/spacer.gif" class="reportImage <?= ($vars['lose'] ? 'adventureLose' : 'adventureVictory'); ?>"<?= $vars['title'] !== null ? ' title="' . $vars['title'] . '" alt="' . $vars['title'] . '"' : ''; ?>>
<div class="adventureReport">
    <div class="adventureHeader">
        <div class="headline">
            <div class="from">
                <i class="tribeIcon bigTribe<?= $vars['race']; ?>"> </i>
                <?= \Core\Helper\CombatIcon::svg('svg-attack.svg', 'roleIcon'); ?>
                <h2><?= T("Auction", "Adventure"); ?></h2>
            </div>
            <div class="to">
                <svg viewBox="0 0 20 20" preserveAspectRatio="none">
                    <path d="M0 0L20 10L0 20z"></path>
                </svg>
            </div>
        </div>
    </div>
    <table class="additionalInformation">
        <?php if (!$vars['lose']): ?>
            <tbody class="infos">
            <tr>
                <td class="empty" colspan="12"></td>
            </tr>
            <tr>
                <th><?= T("Reports", "Information"); ?></th>
                <td class="dropItems" colspan="11">
                    <div><img src="img/spacer.gif" class="iExperience" alt="<?= T("Reports", "exp"); ?>" title="<?= T("Reports", "exp"); ?>"> <?= $ltr($vars['exp']); ?> </div>
                    <div><img src="img/spacer.gif" class="injury" alt="<?= T("Reports", "injury"); ?>" title="<?= T("Reports", "injury"); ?>"> <?= $ltr("\u{2212}" . $ltr($vars['injury']) . '%'); ?></div>
                </td>
            </tr>
            </tbody>
        <?php endif; ?>
        <tbody class="goods">
        <tr>
            <td class="empty" colspan="12"></td>
        </tr>
        <?php if ($vars['failed']): ?>
            <th><?= T("Reports", "Bounty"); ?></th>
            <td colspan="11">
                <?= T("Reports", "noValuableThingFound"); ?>
            </td>
        <?php elseif (!empty($vars['Bounty'])): ?>
            <tr>
                <th><?= T("Reports", "Bounty"); ?></th>
                <td colspan="11" <?= $vars['style']; ?>><?= $vars['Bounty']; ?></td>
            </tr>
        <?php else: ?>
            <tr>
                <th><?= T("Reports", "Information"); ?></th>
                <td colspan="11"><?= T("Reports", "adventureFailed"); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>