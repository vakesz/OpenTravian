<?php

declare(strict_types=1);

$tab = (int) $vars['t'];
$send = !empty($vars['send']);
$messageId = (int) $vars['id'];
$answerActive = !empty($vars['answerActive']);
$hasGoldClub = !empty($vars['goldClub']);
$formAction = match (true) {
    $tab === 3 => '/messages/archive',
    $send => '/messages/sent',
    default => '/messages/inbox',
};
$backHref = match (true) {
    $tab === 3 => '/messages/archive',
    $send => '/messages/sent',
    default => '/messages/inbox',
};
$backLabel = match (true) {
    $tab === 3 => T('Messages', 'back to the archive'),
    $send => T('Messages', 'back to send box'),
    default => T('Messages', 'back to the inbox'),
};
$answerButtonId = 'answerMessage';
?>
<div id="messageNavigation">
    <div id="backToInbox">
        <a href="<?= $backHref ?>"><?= $backLabel ?></a>
    </div>
    <div class="clear"></div>
</div>
<div class="paper">
    <div class="layout">
        <div class="paperTop">
            <div class="paperContent">
                <?php if ($vars['isAdmin']): ?>
                    <div id="sender">
                        <div class="header label"><?= T('Messages', 'sender') ?>:</div>
                        <?php if ($vars['isAllianceAutoType']): ?>
                            <div class="header text"><a href="<?= htmlspecialchars(getSupportUrl(), ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank"><?= $vars['name'] ?></a></div>
                        <?php else: ?>
                            <div class="header text"><a href="<?= profileUrl((int) $vars['info']['uid']) ?>"><?= $vars['info']['name'] ?></a></div>
                        <?php endif; ?>
                        <div class="clear"></div>
                        <div class="header label"><?= T('Messages', 'Recipient') ?>:</div>
                        <?php if ($vars['isAllianceAutoType']): ?>
                            <div class="header text"><a href="<?= htmlspecialchars(getSupportUrl(), ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank"><?= $vars['info']['to_name'] ?></a></div>
                        <?php else: ?>
                            <div class="header text"><a href="<?= profileUrl((int) $vars['info']['to_uid']) ?>"><?= $vars['info']['to_name'] ?></a></div>
                        <?php endif; ?>
                        <div class="clear"></div>
                    </div>
                <?php else: ?>
                    <div id="sender">
                        <div class="header label"><?= $send ? T('Messages', 'Recipient') : T('Messages', 'sender') ?>:</div>
                        <?php if ($vars['isAllianceAutoType']): ?>
                            <div class="header text"><a href="<?= htmlspecialchars(getSupportUrl(), ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank"><?= $vars['name'] ?></a></div>
                        <?php elseif ((int) $vars['uid'] === 0): ?>
                            <div class="header text"><a href="<?= htmlspecialchars(getSupportUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= $vars['name'] ?></a></div>
                        <?php else: ?>
                            <div class="header text"><a href="<?= profileUrl((int) $vars['uid']) ?>"><?= $vars['name'] ?></a></div>
                        <?php endif; ?>
                        <div class="clear"></div>
                    </div>
                <?php endif; ?>

                <div id="subject">
                    <div class="header label"><?= T('Messages', 'Subject') ?>:</div>
                    <div class="header text"><?= $vars['subject'] ?></div>
                    <div class="clear"></div>
                </div>

                <div id="time">
                    <div class="header label"><?= T('Messages', 'Sent at') ?>:</div>
                    <div class="header text"><?= $vars['time'] ?></div>
                </div>

                <div class="toolList">
                    <form method="post" action="<?= $formAction ?>">
                        <input type="hidden" name="id[]" value="<?= $messageId ?>">
                        <button type="submit" name="delete" id="delmsg" class="icon "
                                onclick="return (function() {
                                        (new Travian.Dialog.Dialog({
                                            preventFormSubmit: true,
                                            onOkay: function(dialog, contentElement) {
                                                var dummyInput = jQuery('<input>');
                                                dummyInput.attr('type', 'hidden');
                                                dummyInput.attr('name', 'delete');
                                                const form = jQuery('#delmsg').closest('form');
                                                form.append(dummyInput).trigger('submit');
                                            }}))
                                        .setContent('<?= T('Messages', 'ConfirmDelete') ?>')
                                        .show();
                                        return false;
                                    })()" useicon="" title="<?= T('Messages', 'Delete') ?>">
                            <img src="img/spacer.gif" class="delete" alt="delete">
                        </button>
                        <?php if ($hasGoldClub): ?>
                            <button type="submit" name="archive" id="archiveGoldclub" class="icon " useicon=""
                                    title="<?= T('Messages', 'Archive') ?>">
                                <img src="img/spacer.gif" class="archive" alt="archive">
                            </button>
                        <?php else: ?>
                            <button type="button" name="archive" id="archiveGoldclub" class="icon gold" useicon=""
                                    title="<?= T('Messages', 'Archive') . '||' . T('Messages', 'needClub') ?>"
                                    onclick="new Travian.Game.GoldclubDialog({featureKey: 'messageArchive', cssClass: 'premiumFeaturePackage premiumFeatureGoldclub paymentShopV5', premiumFeatureDialogVersion: 2, version: 2}); return false;">
                                <img src="img/spacer.gif" class="archive" alt="archive">
                            </button>
                        <?php endif; ?>
                        <div class="reportMessage">
                            <?php if (
                                !$send &&
                                (int) $vars['reported'] === 0 &&
                                !empty($vars['reportable']) &&
                                !empty($vars['canReportSpam'])
                            ): ?>
                                <a href="#" id="reportSpam" class="a arrow" onclick='
                                        Travian.Game.ReportSpamMessagesDialog.reportSpam(
                                        "<?= $messageId ?>",
                                        "<?= T('Messages', 'Report as spam') ?>",
                                        "<?= T('Messages', 'report') ?>",
                                        "<?= T('Messages', 'Attention: Misuse of the report function is punishable') ?>",
                                        {
                                        "not_chosen":"<?= T('Messages', 'Choose reason') ?>",
                                        "advertisement":"<?= T('Messages', 'Advertisement') ?>",
                                        "harassment":"<?= T('Messages', 'harassment') ?>",
                                        "gold":"<?= T('Messages', 'gold') ?>",
                                        "misc":"<?= T('Messages', 'Other') ?>"
                                        }
                                        );
                                        return false;'><?= T('Messages', 'Report as spam') ?></a>
                            <?php elseif ((int) $vars['reported'] > 0): ?>
                                <span class="notice"><?= $vars['reportedText'] ?></span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="separator"></div>
                <div id="message"><?= $vars['message'] ?></div>

                <div id="answer">
                    <?php if (!$send): ?>
                        <form method="post" action="<?= $formAction ?>">
                            <input type="hidden" name="id[]" value="<?= $messageId ?>">
                            <button type="submit" value="unread" name="mark" id="mark" class="textButtonV1 green " version="textButtonV1"><?= T('Messages', 'Mark as unread') ?></button>
                            <script type="text/javascript" id="mark_script">
                                jQuery(function () {
                                    jQuery('button#mark').on('click', function () {
                                        jQuery(window).trigger('buttonClicked', [this, {
                                            "type": "submit",
                                            "value": "unread",
                                            "name": "mark",
                                            "id": "mark",
                                            "class": "textButtonV1 green ",
                                            "title": "",
                                            "confirm": "",
                                            "onclick": "",
                                            "version": "textButtonV1"
                                        }]);
                                    });
                                });
                            </script>
                        </form>
                    <?php endif; ?>
                    <?php if ($answerActive): ?>
                        <button type="button" value="<?= T('Messages', 'answer') ?>" id="<?= $answerButtonId ?>" class="textButtonV1 green " version="textButtonV1"
                                onclick="this.disabled = true; window.location.href = '/messages/write?id=<?= $messageId ?>'; return false;"><?= T('Messages', 'answer') ?></button>
                    <?php else: ?>
                        <button type="button" value="<?= T('Messages', 'answer') ?>" id="<?= $answerButtonId ?>" class="textButtonV1 green  disabled" version="textButtonV1"
                                onclick="if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}"><?= T('Messages', 'answer') ?></button>
                    <?php endif; ?>
                    <script type="text/javascript" id="<?= $answerButtonId ?>_script">
                        jQuery(function () {
                            jQuery('button#<?= $answerButtonId ?>').on('click', function () {
                                jQuery(window).trigger('buttonClicked', [this, {
                                    "type": "button",
                                    "value": "<?= T('Messages', 'answer') ?>",
                                    "name": "",
                                    "id": "<?= $answerButtonId ?>",
                                    "class": "textButtonV1 green <?= $answerActive ? '' : ' disabled' ?>",
                                    "title": "",
                                    "confirm": "",
                                    "onclick": "<?= $answerActive ? "this.disabled = true; window.location.href = '/messages/write?id=" . $messageId . "'; return false;" : "if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} else {}" ?>",
                                    "version": "textButtonV1"
                                }]);
                            });
                        });
                    </script>
                    <div class="clear"></div>
                </div>
            </div>
        </div>
        <div class="paperBottom"></div>
    </div>
</div>
<script type="text/javascript">
    jQuery(function () {
    });
</script>
