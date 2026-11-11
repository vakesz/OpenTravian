<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/invite');
$inviteLabel = T("Alliance", "invite");
?>
<h4 class="round"><?=T("Alliance", "Invite a player into the alliance");?></h4>
<?php if ($vars['disallowInvite']): ?>
    <p class="warning"><?=T("Alliance", "Kicking/inviting is not allowed at this time");?></p>
<?php else: ?>
<form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <table cellpadding="1" cellspacing="1" class="option invite transparent">
        <tbody>
        <tr>
            <th>
                <?=T("Alliance", "Name");?>:
            </th>
            <td>
                <input class="name text" type="text" name="a_name" maxlength="20">
            </td>
        </tr>
        </tbody>
    </table>
    <p class="option">
        <button type="submit" value="<?= htmlspecialchars($inviteLabel, ENT_QUOTES, 'UTF-8'); ?>" class="textButtonV1 green " version="textButtonV1"><?= htmlspecialchars($inviteLabel, ENT_QUOTES, 'UTF-8'); ?></button>
        <script type="text/javascript">
            jQuery(function() {
                jQuery('button.textButtonV1').click(function (event) {
                    jQuery(window).trigger('buttonClicked', [this, {
                        "type": "submit",
                        "value": "<?= htmlspecialchars($inviteLabel, ENT_QUOTES, 'UTF-8'); ?>",
                        "class": "textButtonV1 green ",
                        "version": "textButtonV1"
                    }]);
                });
            });
        </script>
    </p>
    <p class="note option"><?= htmlspecialchars((string) ($vars['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="error option"><?= htmlspecialchars((string) ($vars['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</form>
<h4 class="round"><?=T("Alliance", "Invitations");?>:</h4>
<table cellpadding="1" cellspacing="1" class="option invitations transparent">
    <tbody>
    <?=$vars['invitations'];?>
    </tbody>
</table>
<?php endif;?>
