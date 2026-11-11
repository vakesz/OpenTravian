<?php
$previousTitle = htmlspecialchars(T('Reports', 'Previous report'), ENT_QUOTES, 'UTF-8');
$nextTitle = htmlspecialchars(T('Reports', 'Next report'), ENT_QUOTES, 'UTF-8');
$subjectTitle = htmlspecialchars(trim(html_entity_decode(strip_tags((string) $vars['subject']), ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
$chevronBack = '<svg viewBox="0 0 11.8 20" class="chevronBack"><path d="M10 20 0 10 10 0l1.8 1.8L3.5 10l8.2 8.2L10 20z"></path></svg>';
$chevronForward = '<svg viewBox="0 0 11.8 20" class="chevronForward"><path d="M1.8 20 0 18.2 8.2 10 0 1.8 1.8 0l10 10-10 10z"></path></svg>';
?>
<div class="navigationSpacer"></div>
<div id="reportWrapper">
    <script type="text/javascript">Travian.Game.Reports.rightsText = <?= json_encode($vars['rightsText'], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>;</script>
    <div class="header<?=(!$vars['showImage'] ? ' noImages' : null);?>">
        <?php if ($vars['showForward']): ?>
            <div class="headline withQuickNavigation">
                <?php if ($vars['old']['disabled']): ?>
                <a href="#" class="reportQuickNavigation disabled" title="<?= $previousTitle; ?>" onclick="return false;">
                    <?php else: ?>
                    <a href="<?= $vars['old']['link']; ?>" class="reportQuickNavigation" title="<?= $previousTitle; ?>">
                        <?php endif; ?>
                        <?= $chevronBack; ?>
                    </a>
                    <div class="subject" title="<?= $subjectTitle; ?>"><?= $vars['subject']; ?></div>
                    <?php if ($vars['next']['disabled']): ?>
                    <a href="#" class="reportQuickNavigation disabled" title="<?= $nextTitle; ?>" onclick="return false;">
                        <?php else: ?>
                        <a href="<?= $vars['next']['link']; ?>" class="reportQuickNavigation" title="<?= $nextTitle; ?>">
                            <?php endif; ?>
                            <?= $chevronForward; ?>
                        </a>
            </div>
        <?php else:?>
            <div class="headline withQuickNavigation">
                <a href="#" class="reportQuickNavigation disabled" title="<?= $previousTitle; ?>" onclick="return false;">
                    <?= $chevronBack; ?>
                </a>
                <div class="subject" title="<?= $subjectTitle; ?>"><?= $vars['subject']; ?></div>
                <a href="#" class="reportQuickNavigation disabled" title="<?= $nextTitle; ?>" onclick="return false;">
                    <?= $chevronForward; ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="time">
            <div class="text"><?= $vars['sendTime']; ?></div>
        </div>
        <div class="toolList">

            <?php if ($vars['showDeleteButton']): ?>
                <button type="button" id="deleteReportButton" class="icon "
                        title="<?= T("Reports", "Delete report"); ?>" onclick="return (function() {
                        (new Travian.Dialog.Dialog({

                        preventFormSubmit: true,
                        onOkay: function(dialog, contentElement) {Travian.Game.Reports.indexUrl = '/report/overview'; Travian.Game.Reports.processDeleteIds([<?= (int)$vars['reportId']; ?>])}}))
                        .setContent('<?= T("Reports", "Really delete this report?"); ?>')
                        .show();
                        return false;
                        })()"><i class="reportButton delete"></i>
                </button>
            <?php endif; ?>

            <?php if ($vars['isMine']): ?>
                <button type="button" id="markReadButtonReport" class="icon " title="<?= T("Reports", "Mark as unread"); ?>" onclick="Travian.Game.Reports.indexUrl = '/report/overview'; Travian.Game.Reports.toggleReadStatus(<?= (int)$vars['reportId']; ?>, 1); return false;" useicon="1"><i class="reportButton markRead"></i></button>
            <?php endif; ?>

            <?php if ($vars['showPermissionsButton']): ?>
                <button type="button" class="icon" title="<?= T("Reports", "Access permissions"); ?>" onclick="return Travian.Game.Reports.editRights(this, <?= (int)$vars['reportId']; ?>);" useicon="1"><i class="reportButton access"></i></button>
            <?php endif; ?>

            <?php if ($vars['showArchiveButton']): ?>
                <?php if ($vars['hasGoldClub']): ?>
                    <?php if ($vars['archived']): ?>
                    <button type="button" id="archiveGoldclub" class="icon"
                            onclick="Travian.Game.Reports.processRecoverIds([<?= (int)$vars['reportId']; ?>]);return false;"
                            title="<?= T("Reports", "Recover"); ?>"><i class="reportButton archive"></i></button>
                <?php else: ?>
                    <button type="button" id="archiveGoldclub" class="icon"
                            onclick="Travian.Game.Reports.processArchiveIds([<?= (int)$vars['reportId']; ?>]);return false;"
                            title="<?= T("Reports", "Archive"); ?>"><i class="reportButton archive"></i></button>
                <?php endif; ?>
                <?php else: ?>
                    <button type="button" id="archiveGoldclub" class="icon gold"
                            title="<?= T("Reports", "Archive||For this feature you need the Gold club activated"); ?>"
                            onclick="new Travian.Game.GoldclubDialog({featureKey: 'messageArchive', cssClass: 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5', premiumFeatureDialogVersion: 2, version: 2}); return false;"
                            useicon="1">
                        <i class="reportButton archive"></i></button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="clear"></div>
    </div>
    <div class="body">
        <?php if ($vars['showImage'] && isset($vars['bodyImage'])):?>
        <?=$vars['bodyImage'];?>
        <?php endif;?>
        <?php if (isset($vars['includeFile'])): ?>
            <?php require(TEMPLATES_PATH . $vars['includeFile'] . ".php"); ?>
        <?php endif; ?>
        <?= $vars['content']; ?>
    </div>
</div>
