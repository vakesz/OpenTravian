<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/name');
$saveLabel = T("Global", "General.save");
?>
<form id="SettingsFormular" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <table cellpadding="1" cellspacing="1" class="option name transparent">
        <tbody>
        <tr>
            <th>
                <?=T("Alliance", "Tag");?>:
            </th>
            <td>
                <input class="tag text " name="newTag" value="<?=htmlspecialchars($vars['tag'], ENT_QUOTES, 'UTF-8');?>" maxlength="5">
                <?php if (isset($vars['tagError'])):?>
                    <span class="error2"><?=$vars['tagError'];?></span>
                <?php endif;?>
            </td>
        </tr>
        <tr>
            <th>
                <?=T("Alliance", "Name");?>:
            </th>
            <td>
                <input class="name text " name="newName" value="<?=htmlspecialchars($vars['name'], ENT_QUOTES, 'UTF-8');?>" maxlength="15">
                <?php if (isset($vars['nameError'])):?>
                    <span class="error2"><?=$vars['nameError'];?></span>
                <?php endif;?>
            </td>
        </tr>
        </tbody>
    </table>
    <p>
        <button type="submit" value="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>" id="btn_ok" class="textButtonV1 green disabled" version="textButtonV1" disabled=""><?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?></button>
        <script type="text/javascript">
            jQuery(function() {
                if (jQuery('#btn_ok').length > 0) {
                    jQuery('#btn_ok').click(function (event) {
                        jQuery(window).trigger('buttonClicked', [this, {
                            "type": "submit",
                            "value": "<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>",
                            "id": "btn_ok",
                            "class": "textButtonV1 green ",
                            "version": "textButtonV1"
                        }]);
                    });
                }
            });
        </script>
    </p>
</form>
<p class="note option"><?= htmlspecialchars((string) ($vars['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<script type="text/javascript">
    jQuery(function() {
        Travian.Form.UnloadHelper.watchHtmlForm(jQuery('#SettingsFormular'));
    });
</script>
