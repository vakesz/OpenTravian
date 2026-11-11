<?php if (isset($vars['error']) and $vars['error']): ?>
    <p class="error"><?= $vars['error']; ?></p>
<?php endif; ?>
<div id="messageNavigation">
    <div id="backToInbox">
        <a href="/messages/inbox"><?= T("Messages", "back to the inbox"); ?></a>
    </div>
    <div class="clear"></div>
</div>
<form method="post" id="messageForm" action="/messages/write">
<div class="paper">
<div class="layout">
<div class="paperTop">
<div class="paperContent">
<div id="recipient">
<div class="header label">
<?= T("Messages", "Recipient"); ?>:
</div>
<div class="header text">
<input tabindex="1" id="receiver" class="text short" type="text" name="an" value="<?= htmlspecialchars((string)$vars['receiver'], ENT_QUOTES, 'UTF-8'); ?>">
<button type="button" id="adbook" class="icon " title="<?= T("Messages", "Addressbook"); ?>" onclick="Travian.Game.AddressBook.show()" tabindex="5" useicon=""><img src="/img/spacer.gif" class="adbook"></button>
<button type="button" id="ally" class="icon " title="<?= T("Messages", "Alliance"); ?>" onclick="Travian.Game.Messages.addRecipient('[ally]')" tabindex="6" useicon=""><img src="/img/spacer.gif" class="ally"></button>
<a href="/support/ticket" target="_blank" class="button icon" title="<?= T("Profile", "Support and Multihunter"); ?>"><i class="support_small"></i></a>
</div>
<div class="clear"></div>
</div>
<div id="subject">
<div class="header label">
<?= T("Messages", "Subject"); ?>:
</div>
<div class="header text">
<input tabindex="2" class="text" type="text" name="be" value="<?= htmlspecialchars((string)$vars['subject'], ENT_QUOTES, 'UTF-8'); ?>">
</div>
<div class="clear"></div>
</div>
<div id="languages">
<div class="header label">
<?= T("Messages", "Languages", "Languages"); ?>:
</div>
<div class="header text">
<div id="playerLanguages"></div>
</div>
<div class="clear"></div>
</div>
<div id="bbEditor" class="">
<div id="message_container" class="bbEditor">
<?= \resources\View\PHPBatchView::render('messages/bbToolbar', ['id' => 'message']); ?>
<div class="line bbLine"></div>
<textarea id="message" name="message" class="messageEditor" tabindex="3" cols="1" rows="1"><?= htmlspecialchars((string)$vars['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
<div id="message_preview" class="messageEditor"></div>
</div>
<script type="text/javascript">
jQuery(function()
{
new Travian.Game.BBEditor("message", false);
});
</script>
</div>
<div id="send">
<script type="text/javascript">
MessageSendButtonDoubleClickPreventer = new Travian.DoubleClickPreventer();
MessageSendButtonDoubleClickPreventer.timeout = 2000;
</script>
<?php $sendButtonId = get_button_id(); ?>
<button type="submit" value="<?= T("Messages", "send"); ?>" id="<?= $sendButtonId; ?>" class="textButtonV1 green " onclick="return MessageSendButtonDoubleClickPreventer.check();" tabindex="4" version="textButtonV1"><?= T("Messages", "send"); ?></button>
<script type="text/javascript" id="<?= $sendButtonId; ?>_script">
jQuery(function() {
jQuery('button#<?= $sendButtonId; ?>').on('click', function () {
jQuery(window).trigger('buttonClicked', [this, {"type":"submit","value":"<?= T("Messages", "send"); ?>","name":"","id":"<?= $sendButtonId; ?>","class":"textButtonV1 green ","title":"","confirm":"","onclick":"return MessageSendButtonDoubleClickPreventer.check();","tabindex":"4","version":"textButtonV1"}]);
});
});
</script>
<input type="hidden" name="c" value="<?= $vars['checker']; ?>">
</div>
<div class="clear"></div>
</div>
</div>
<div class="paperBottom"></div>
</div>
</div>
</form>
<script type="text/javascript">
	Travian.Translation.add({
		'nachrichten.adressbuch': '<?=T("Messages", "Addressbook");?>',
		'allgemein.save': '<?=T("Global", "General.save");?>'
	});
	jQuery(function() {
		
		var form = jQuery('#messageForm');
        form.on('submit', function(e) {
            if (Travian.Game.Messages.recipients_checked) {
                return true;
            }
            e.stopPropagation();
            var recepients = jQuery('#receiver').val().split(';').map(function(entry) {
                return entry.trim()
            });
            Travian.Game.Messages.checkRecipients(recepients, form);
            return false;
        });
        new Travian.Game.AutoCompleter.PlayerName(jQuery('input#receiver'), {'context': 'messageRecipient'});

        var receiver = jQuery('#receiver');
        var ajaxReq = null;

        receiver.on('change keyup', function() {
            var recipients = Travian.Game.Messages.getRecipients(true);

            if (T4_feature_flags.multi_language) {
                if (ajaxReq) clearTimeout(ajaxReq);
                ajaxReq = setTimeout(() => {
                    ajaxReq = null;
                    Travian.api('profile-languages/recipients', {
                        data: {
                            recipients: recipients
                        },
                        success: function (response) {
                            jQuery('#languages #playerLanguages').html(response.languages);
                            Travian.Tip.refresh();
                        },
                        error: function () {
                            jQuery('#languages #playerLanguages').empty();
                        }
                    })
                }, 333);
            }
        });
		
		<?php if (isset($vars['InadmissibleMessage']) and $vars['InadmissibleMessage']):?>
        var dialog = new Travian.Dialog.Dialog(
            {
                buttonTextOk: '<?=T("Global", "General.ok");?>',
                preventFormSubmit: true
            }
        );
        dialog.setContent('<?=T("Messages", "Inadmissible message");?>');
        dialog.show();
        <?php endif;?>
	});
</script>
