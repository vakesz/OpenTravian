<?php

use Model\PlayerAccusationModel;

/** @var array{uid:int, remaining:int} $vars */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$max = PlayerAccusationModel::MAX_MESSAGE_LENGTH;
?>
<div class="accusePlayerDialog formV2" id="accusePlayerDialog">
    <strong><?= $e(T('Profile', 'Report player')); ?></strong>
    <label class="select">
        <select size="1" name="reason" required="" title="">
            <option value="" disabled="" selected=""></option>
            <?php foreach (PlayerAccusationModel::REASONS as $reason): ?>
                <option value="<?= $e($reason); ?>"><?= $e(T('Profile', 'accusationReason.' . $reason)); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="label"><?= $e(T('Profile', 'reportPlayer_chooseReason')); ?></div>
        <div class="validation" data-rule="notEmpty"><?= $e(T('Profile', 'reportPlayer_chooseReason')); ?></div>
    </label>
    <p class="alert inappropriateContentNotice" style="display: none"><?= $e(T('Profile', 'inappropriateContent_description')); ?></p>
    <label class="textarea">
        <textarea name="message" maxlength="<?= $max; ?>"></textarea>
        <div class="label"><?= $e(T('Profile', 'additionalComments')); ?></div>
    </label>
    <div class="characterLimit"><span><?= $e(T('Profile', 'maxLength')); ?>: </span><span>&#8237;<span class="accuseCount">&#8237;0&#8236;</span>/&#8237;<?= $max; ?>&#8236;&#8236;</span></div>
    <p class="notice"><?= $e(sprintf(T('Profile', 'reportPlayer_reportsPerDayWarning'), (string) $vars['remaining'])); ?></p>
    <input type="hidden" name="uid" value="<?= (int) $vars['uid']; ?>">
    <div class="buttonsWrapper">
        <button class="textButtonV2 buttonFramed rectangle withText grey" type="button" id="accusePlayerCancel"><div><?= $e(T('Profile', 'Cancel')); ?></div></button>
        <button class="textButtonV2 buttonFramed rectangle withText green" type="button" id="accusePlayerSubmit"><div><?= $e(T('Profile', 'reportButton')); ?></div></button>
    </div>
</div>
<script type="text/javascript">
    jQuery(function () {
        var dialog = jQuery('#accusePlayerDialog');
        var reason = dialog.find('select[name=reason]');
        var message = dialog.find('textarea[name=message]');
        reason.on('change', function () {
            dialog.find('.inappropriateContentNotice').toggle(reason.val() === 'inappropriateContent');
        });
        message.on('input', function () {
            dialog.find('.accuseCount').text('‭' + message.val().length + '‬');
        });
        jQuery('#accusePlayerCancel').on('click', function () {
            Travian.WindowManager.closeAllWindows();
        });
        jQuery('#accusePlayerSubmit').on('click', function () {
            if (!reason.val()) {
                reason.closest('label').addClass('invalid');
                return;
            }
            var data = {action: 'save', uid: <?= (int) $vars['uid']; ?>, reason: reason.val(), message: message.val()};
            Travian.WindowManager.closeAllWindows();
            new Travian.Dialog.Ajax('player/accuse', {
                data: data,
                context: 'accusePlayer',
                version: 2,
                        cssClass: 'plain contentV2 playerProfile',
                buttonOk: true,
                buttonTextOk: <?= json_encode(T('Global', 'General.ok')); ?>,
                buttonCloseOnClickOk: true,
                darkOverlay: true
            });
        });
    });
</script>
