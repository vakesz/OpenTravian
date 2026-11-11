<form method="post" action="/messages/notes">
    <div class="paper notices">
        <div class="layout">
            <div class="paperTop">
                <div class="paperContent">
                    <input type="hidden" name="save" value="1">
                    <?=\Core\Session::getCheckerInput();?>

                    <div id="bbEditor">
                        <div id="notepad_container" class="bbEditor">
                            <?= \resources\View\PHPBatchView::render('messages/bbToolbar', ['id' => 'notepad']); ?>
<div class="line bbLine"></div>
                            <textarea name="notepad" id="notepad" class="messageEditor" tabindex="3" cols="1" rows="1"><?=htmlspecialchars((string)$vars['note'], ENT_QUOTES, 'UTF-8');?></textarea>

                            <div id="notepad_preview" class="messageEditor hide"></div>
                        </div>
                        <script type="text/javascript">
                            jQuery(function() {
                                new Travian.Game.BBEditor("notepad", true);
                            });
                        </script>
                    </div>
                    <div class="btn" id="send">
                        <?php $saveButtonId = get_button_id(); $saveLabel = T("Global", "General.save"); ?>
                        <button type="submit" value="<?= $saveLabel; ?>" id="<?= $saveButtonId; ?>" class="textButtonV1 green " version="textButtonV1"><?= $saveLabel; ?></button>
                        <script type="text/javascript" id="<?= $saveButtonId; ?>_script">
                            jQuery(function() {
                                jQuery('button#<?= $saveButtonId; ?>').on('click', function () {
                                    jQuery(window).trigger('buttonClicked', [this, {"type":"submit","value":"<?= $saveLabel; ?>","name":"","id":"<?= $saveButtonId; ?>","class":"textButtonV1 green ","title":"","confirm":"","onclick":"","version":"textButtonV1"}]);
                                });
                            });
                        </script>
                    </div>
                    <div class="notepad info"><?=$vars['info'];?></div>
                </div>
            </div>
            <div class="paperBottom"></div>
        </div>
    </div>
</form>
<script type="text/javascript">
    jQuery(function() {

        Travian.Translation.add({
            'nachrichten.notice_too_long': '<?=T("Messages", "Your note is too long!");?>'
        });

        var notice = new Travian.Game.Notice('10000', jQuery('#notepad'));
    });

</script>
