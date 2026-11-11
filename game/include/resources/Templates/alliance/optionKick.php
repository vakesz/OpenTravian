<?php
/** @var array<string, mixed> $vars */
$action = (string) ($vars['formAction'] ?? '/alliance/options/kick');
$kickLabel = T("Alliance", "Kick player");
?>
<h4 class="round"><?=T("Alliance", "Kick player"); ?></h4>
<?php if ($vars['disallowKick']): ?>
<p class="warning"><?=T("Alliance", "Kicking/inviting is not allowed at this time");?></p>
<?php elseif ($vars['hasMembers']): ?>
    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
        <table cellpadding="1" cellspacing="1" class="option kick transparent">
            <tbody>
            <tr>
                <th><?=T("Alliance", "Name"); ?>:</th>
                <td>
                    <select name="kickPlayer" class="name dropdown"><?=$vars['members']; ?></select>
                </td>
            </tr>
            </tbody>
        </table>
        <p class="info option"><?=T("Alliance", "Players who joined recently cannot be kicked from the alliance within 24 hours after joining.");?></p>
        <table cellpadding="1" cellspacing="1" class="option kick transparent">
            <tbody>
            <tr>
                <td><strong><?=T("Alliance", "Please type: kick");?></strong></td>
            </tr>
            <tr>
                <td>
                    <input class="text" type="text" name="confirmation_kick" placeholder="kick" maxlength="20" autocomplete="off">
                </td>
            </tr>
            <tr>
                <td>
                    <button type="submit" value="<?= htmlspecialchars($kickLabel, ENT_QUOTES, 'UTF-8'); ?>" class="textButtonV1 green " version="textButtonV1"><?= htmlspecialchars($kickLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    <script type="text/javascript">
                        jQuery(function() {
                            jQuery('button.textButtonV1').click(function (event) {
                                jQuery(window).trigger('buttonClicked', [this, {
                                    "type": "submit",
                                    "value": "<?= htmlspecialchars($kickLabel, ENT_QUOTES, 'UTF-8'); ?>",
                                    "class": "textButtonV1 green ",
                                    "version": "textButtonV1"
                                }]);
                            });
                        });
                    </script>
                </td>
            </tr>
            </tbody>
        </table>
    </form>
    <p class="error option"><?= htmlspecialchars((string) ($vars['error'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="note option"><?= htmlspecialchars((string) ($vars['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
