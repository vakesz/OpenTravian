<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/info');
$saveLabel = T("Global", "General.save");
?>
<h4 class="round"><?=T("Alliance", "Edit internal info page");?></h4>
<form id="SettingsFormular" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <textarea id="internInfo1" class="internInfo" name="info1"><?=htmlspecialchars($vars['info1'], ENT_QUOTES, 'UTF-8');?></textarea>
    <textarea id="internInfo2" class="internInfo" name="info2"><?=htmlspecialchars($vars['info2'], ENT_QUOTES, 'UTF-8');?></textarea>

    <div class="clear"></div>

    <p class="btn"><input type="image" value="" name="save" id="btn_save" class="dynamic_img" src="/img/spacer.gif" alt="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>"></p>
    <div class="openedClosedSwitch switchClosed" id="switchBBCodes"><?=T("Alliance", "BB codes");?></div>
    <div class="clear"></div>
    <table cellpadding="1" cellspacing="1" id="bBCodes" class="hide">
        <tbody>
        <?php foreach ([
            ['bbCodeNews', '[news]' . T("Alliance", "bbCodeNumber") . '[/news]'],
            ['losses', '[losses]' . T("Alliance", "bbCodeAlliance") . '[/losses]'],
            ['strength', '[stats]strength[/stats]'],
            ['fighting points', '[stats]fighting points[/stats]'],
            ['bbCodeForum', '[forum]' . T("Alliance", "bbCodeNumber") . '[/forum]'],
            ['Alliance diplomacy', '[diplomatie]'],
            ['bbCodeAlly', '[ally]'],
            ['bbCodeNap', '[nap]'],
            ['bbCodeWar', '[war]'],
        ] as [$descriptionKey, $tag]): ?>
        <tr>
            <td class="desc"><?=T("Alliance", $descriptionKey);?></td>
            <td class="tag"><?=$tag;?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="submitContainer">
        <button type="submit" value="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>" class="textButtonV1 green disabled" version="textButtonV1" disabled=""><?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
</form>
<script type="text/javascript">
    jQuery(function() {
        Travian.Form.UnloadHelper.watchHtmlForm(jQuery('#SettingsFormular'));

        jQuery('#switchBBCodes').click(function (e) {
            Travian.toggleSwitch(jQuery('#bBCodes'), jQuery('#switchBBCodes'));
        });
    });
</script>
