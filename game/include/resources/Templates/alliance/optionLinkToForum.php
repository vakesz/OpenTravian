<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/forum');
$saveLabel = T("Global", "General.save");
?>
<h4 class="round"><?=T("Alliance", "Link to the forum");?></h4>
<p class="option">
    <?=T("Alliance", "If your alliance wants to use an external forum, you can enter the URL here");?>
</p>
<form id="SettingsFormular" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <table cellpadding="1" cellspacing="1" class="option forum_link transparent">
        <tbody>
        <tr>
            <th>
                <?=T("Alliance", "URL");?>                    </th>
            <td>
                <input class="link text" type="text" name="f_link" value="<?=htmlspecialchars($vars['f_link'], ENT_QUOTES, 'UTF-8');?>" maxlength="200">
            </td>
        </tr>
        </tbody>
    </table>
    <p class="option">
        <button type="submit" value="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>" id="btn_ok" class="textButtonV1 green disabled" version="textButtonV1" disabled="">
            <?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <script type="text/javascript">
            jQuery(function() {
                if (jQuery('#btn_ok').length > 0) {
                    jQuery('#btn_ok').click(function (event) {
                        jQuery(window).trigger('buttonClicked', [this, {
                            "type": "submit",
                            "value": "<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>",
                            "id": "btn_ok",
                            "class": "textButtonV1 green ",
                            "title": "<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>",
                            "version": "textButtonV1"
                        }]);
                    });
                }
            });
        </script>
    </p>
</form>
<p class="note option"></p>
<p class="error option"></p>
<script type="text/javascript">
    jQuery(function() {
        Travian.Form.UnloadHelper.watchHtmlForm(jQuery('#SettingsFormular'));
    });
</script>
