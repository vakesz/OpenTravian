<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/description');
$saveLabel = T("Global", "General.save");
?>
<h4 class="round"><?=T("Alliance", "Change alliance description");?></h4>
<form id="SettingsFormular" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <textarea class="editDescription editDescription1" tabindex="1" name="be1"><?=htmlspecialchars($vars['desc1'], ENT_QUOTES, 'UTF-8');?></textarea>
    <textarea class="editDescription editDescription2" tabindex="2" name="be2"><?=htmlspecialchars($vars['desc2'], ENT_QUOTES, 'UTF-8');?></textarea>

    <div class="clear"></div>
    <?php if ($vars['hasMedal']):?>
        <script type="text/javascript">
            jQuery(function() {
                if (jQuery('#switchBBCodes')) {
                    jQuery('#switchBBCodes').click(function (e) {
                        Travian.toggleSwitch(jQuery('#bBCodes'), jQuery('#switchBBCodes'));
                    });
                }
            });
        </script>
        <div class="switchWrap">
            <div class="openedClosedSwitch switchClosed" id="switchBBCodes"><?=T("Alliance", "Medals");?></div>
            <div class="clear"></div>
        </div>
        <table cellpadding="1" cellspacing="1" id="bBCodes" class="hide">
            <tr>
                <td><?=T("Alliance", "Category");?></td>
                <td><?=T("Alliance", "Rank");?></td>
                <td><?=T("Alliance", "week");?></td>
                <td><?=T("Alliance", "bbcode");?></td>
            </tr>
            <?=$vars['medals'];?>
        </table>
        <br/>
    <?php endif;?>
    <p class="btn">
        <button type="submit" value="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>" name="s1" id="btn_save" class="textButtonV1 green disabled" version="textButtonV1" tabindex="3" disabled=""><?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?></button>
        <script type="text/javascript">
            jQuery(function() {
                if (jQuery('#btn_save').length > 0) {
                    jQuery('#btn_save').click(function (event) {
                        jQuery(window).trigger('buttonClicked', [this, {
                            "type": "submit",
                            "value": "<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>",
                            "name": "s1",
                            "id": "btn_save",
                            "class": "textButtonV1 green ",
                            "version": "textButtonV1",
                            "tabindex": 3
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
