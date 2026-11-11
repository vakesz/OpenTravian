<?php

use Model\ArtefactsModel;

/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */

$disAllowKick = getGame("disallowAllianceKickAfterWWRelease") && ArtefactsModel::wwPlansReleased();
$disAllowInvite = getGame("disallowAllianceInviteAfterWWRelease") && ArtefactsModel::wwPlansReleased();
$aid = (int) ($vars['aid'] ?? 0);
$own = !empty($vars['ownAllianceRoutes']);
$base = $own ? '/alliance/options' : ('/alliance/' . $aid . '/options');
$nameHref = $own ? $base . '/name' : $base . '?o=100';
$descHref = $own ? $base . '/description' : $base . '?o=3';
$infoHref = $own ? $base . '/info' : $base . '?o=7';
$rightsHref = $own ? $base . '/rights' : $base . '?o=1';
$forumHref = $own ? $base . '/forum' : $base . '?o=5';
$inviteHref = $own ? $base . '/invite' : $base . '?o=4';
$diplHref = $own ? $base . '/diplomacy' : $base . '?o=6';
$kickHref = $own ? $base . '/kick' : $base . '?o=2';
$quitHref = !empty($vars['quitHref']) ? (string)$vars['quitHref'] : ($base . '?o=11');
?>
<?php if ($vars['options']['total'] > 0): ?>
    <h4 class="round"><?=T("Alliance", "Settings"); ?></h4>
    <ul class="options">
        <?php if ($vars['options']['perm'][0]): ?>
            <li>
                <a class="a arrow" href="<?= htmlspecialchars($nameHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Change name"); ?></a>
            </li>
            <li>
                <a class="a arrow"
                   href="<?= htmlspecialchars($descHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Change alliance description"); ?></a>
            </li>
            <li>
                <a class="a arrow"
                   href="<?= htmlspecialchars($infoHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Edit internal info page"); ?></a>
            </li>
        <?php endif; ?>
        <?php if ($vars['options']['perm'][1]): ?>
            <li>
                <a class="a arrow" href="<?= htmlspecialchars($rightsHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Assign to position"); ?></a>
            </li>
        <?php endif; ?>
        <?php if ($vars['options']['perm'][2]): ?>
            <li>
                <a class="a arrow" href="<?= htmlspecialchars($forumHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Link to the forum"); ?></a>
            </li>
        <?php endif; ?>
    </ul>
<?php endif; ?>
<h4 class="round"><?=T("Alliance", "Actions"); ?></h4>
<ul class="options">
    <?php if ($vars['options']['perm'][3]): ?>
        <?php if ($vars['finish']): ?>
            <li>
                <a class="arrow disabled"
                   title="<?=T("Alliance", "InvitesAreClosed"); ?>"><?=T("Alliance", "Invite a player into the alliance"); ?></a>
            </li>
        <?php elseif ($disAllowInvite): ?>
            <li>
                <a class="arrow disabled"
                   title="<?=T("Alliance", "Kicking/inviting is not allowed at this time");?>"><?=T("Alliance", "Kick player"); ?></a>
            </li>
        <?php else: ?>
            <li>
                <a class="arrow"
                   href="<?= htmlspecialchars($inviteHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Invite a player into the alliance"); ?></a>
            </li>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($vars['options']['perm'][4]): ?>
        <li>
            <a class="arrow" href="<?= htmlspecialchars($diplHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Alliance diplomacy"); ?></a>
        </li>
    <?php endif; ?>
    <?php if ($vars['options']['perm'][5]): ?>
        <?php if ($vars['finish']): ?>
            <li>
                <a class="arrow disabled"
                   title="<?=T("Alliance", "Cant kick player"); ?>"><?=T("Alliance", "Kick player"); ?></a>
            </li>
        <?php elseif ($disAllowKick): ?>
            <li>
                <a class="arrow disabled"
                   title="<?=T("Alliance", "Kicking/inviting is not allowed at this time");?>"><?=T("Alliance", "Kick player"); ?></a>
            </li>
        <?php else: ?>
            <li>
                <a class="arrow" href="<?= htmlspecialchars($kickHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Kick player"); ?></a>
            </li>
        <?php endif; ?>
    <?php endif; ?>
    <li>
        <?php if ($vars['hasEmbassy']): ?>
        <a class="arrow" href="<?= htmlspecialchars($quitHref, ENT_QUOTES, 'UTF-8') ?>"><?=T("Alliance", "Quit alliance"); ?></a>
            <?php else: ?>
            <a class="arrow disabled" href="#" title="<?=T("Alliance", "In order to quit alliance you need an embassy");?>"><?=T("Alliance", "Quit alliance"); ?></a>
        <?php endif; ?>

    </li>
</ul>
<?php
$bannerBase = $own ? '/alliance/options/banner' : ('/alliance/' . $aid . '/options/banner');
?>
<h4 class="round"><?= T("Alliance", "Alliance banner"); ?></h4>
<ul class="options">
    <li>
        <a class="arrow" href="<?= htmlspecialchars($bannerBase . '/create', ENT_QUOTES, 'UTF-8') ?>" title="<?= T("Alliance", "Create my banner"); ?>"><?= T("Alliance", "Create my banner"); ?></a>
    </li>
    <li>
        <a class="arrow" href="<?= htmlspecialchars($bannerBase . '/my', ENT_QUOTES, 'UTF-8') ?>" title="<?= T("Alliance", "My banner"); ?>"><?= T("Alliance", "My banner"); ?></a>
    </li>
    <li>
        <a class="arrow" href="<?= htmlspecialchars($bannerBase . '/gallery', ENT_QUOTES, 'UTF-8') ?>" title="<?= T("Alliance", "Banner gallery"); ?>"><?= T("Alliance", "Banner gallery"); ?></a>
    </li>
</ul>
