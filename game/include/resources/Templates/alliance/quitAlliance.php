<?php
/** @var array<string, mixed> $vars */
$aid = (int) ($vars['aid'] ?? 0);
$action = (string) ($vars['formAction'] ?? ('/alliance/options/quit'));
if ($action === '/alliance/options/quit' && $aid > 0 && empty($vars['ownAllianceRoutes'])) {
    $action = '/alliance/' . $aid . '/options?o=11';
}
$confirmLabel = T("Alliance", "Confirm");
?>
<h4 class="round"><?=T("Alliance", "Quit alliance");?></h4>
<?php if($vars['confirmed']):?>
    <p class="error option">
        <?=T("Alliance", "you just left your alliance");?>
    </p>
<?php else:?>
    <p class="option"><?=T("Alliance", "In order to quit the alliance you have to enter your password gain for safety reasons");?></p>
    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
        <table cellpadding="1" cellspacing="1" class="option quit transparent">
            <tbody>
            <tr>
                <th>
                    <?=T("Alliance", "Password");?>:
                </th>
                <td>
                    <input class="pass text" type="password" name="pw" maxlength="20">
                </td>
            </tr>
            </tbody>
        </table>

        <p class="option">
            <button type="submit" value="<?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8'); ?>" id="btn_ok" class="textButtonV1 green " version="textButtonV1"
                    title="<?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <script type="text/javascript">
                jQuery(function() {
                    if (jQuery('#btn_ok').length > 0) {
                        jQuery('#btn_ok').click(function (event) {
                            jQuery(window).trigger('buttonClicked', [this, {
                                "type": "submit",
                                "value": "<?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8'); ?>",
                                "id": "btn_ok",
                                "class": "textButtonV1 green ",
                                "title": "<?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8'); ?>",
                                "version": "textButtonV1"
                            }]);
                        });
                    }
                });
            </script>
        </p>
    </form>
    <p class="error option">
        <?=$vars['error'];?>
    </p>
<?php endif;?>
