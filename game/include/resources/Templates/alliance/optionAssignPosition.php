<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/rights');
$saveLabel = T("Global", "General.save");
?>
<h4 class="round"><?=T("Alliance", "Assign to position");?></h4>
<p><?=T("Alliance", "You can set up different permissions for each alliance member and assign positions");?></p>
<form id="SettingsFormular" method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <table cellpadding="1" cellspacing="1" id="memberAdministration">

        <thead>
        <tr>
            <th><?=T("Alliance", "Player");?></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightPosition" alt="<?=T("Alliance", "Assign to position");?>" title="<?=T("Alliance", "Assign to position");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightDisband" alt="<?=T("Alliance", "Kick player");?>" title="<?=T("Alliance", "Kick player");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightDescription" alt="<?=T("Alliance", "Change alliance description");?>" title="<?=T("Alliance", "Change alliance description");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightDiplomacy" alt="<?=T("Alliance", "Alliance diplomacy");?>" title="<?=T("Alliance", "Alliance diplomacy");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightMessage" alt="<?=T("Alliance", "IGMs to every alliance member");?>" title="<?=T("Alliance", "IGMs to every alliance member");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightInvite" alt="<?=T("Alliance", "Invite a player into the alliance");?>" title="<?=T("Alliance", "Invite a player into the alliance");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightForum" alt="<?=T("Alliance", "Manage forums");?>" title="<?=T("Alliance", "Manage forums");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightFlags" alt="<?=T("Alliance", "Manage flags and markers in map");?>" title="<?=T("Alliance", "Manage flags and markers in map");?>"/></th>
            <th><img src="/img/spacer.gif" class="allyRight allyRightSpecialization" alt="<?=T("Alliance", "Manage members specializations");?>" title="<?=T("Alliance", "Manage members specializations");?>"/></th>
            <th><?=T("Alliance", "Title");?></th>
        </tr>
        </thead>
        <tbody>
        <?=$vars['membersWithPermission'];?>
        </tbody>
    </table>
    <p>
        <button type="submit" value="<?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?>" id="btn_ok" class="textButtonV1 green " version="textButtonV1"><?= htmlspecialchars($saveLabel, ENT_QUOTES, 'UTF-8'); ?></button>
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
<?php if(isset($vars['note'])):?>
    <p class="note option"><?=$vars['note'];?></p>
<?php endif;?>
