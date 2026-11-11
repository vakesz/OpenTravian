<?php

declare(strict_types=1);

use Core\Config;
use Core\Session;

if (isset($vars['error'])) {
    echo '<p class="error">' . $vars['error'] . '</p>';
}
$m = new \Model\OptionModel();
$config = Config::getInstance();
$session = Session::getInstance();
$total_pop = (int)$session->get("total_pop");
$usedFreeRenames = $session->getTotalNameChanges() >= $config->gold->changeName->freeTimes
    || $total_pop >= $config->gold->changeName->freeTillPopulation;
$canGoldRename = $usedFreeRenames
    && $total_pop <= $config->gold->changeName->impossibleAfterPopulation;
$canFreeRename = !$usedFreeRenames;
$canRespawn = !empty($vars['canRespawn']);
?>
    <script type="text/javascript">
        function getAccountRenameValues() {
            var accountNewName = jQuery('#account_rename_new_name').val();
            if (typeof accountNewName === 'undefined' || accountNewName === '') {
                return false;
            }
            return {accountNewName: accountNewName, accountPassword: ''};
        }

        function avatarDelete() {
            var confirmations = ['delete'].map(function (confirmation) {
                return confirmation.trim().toLowerCase();
            });
            var confirmation = jQuery('#confirmation_delete').val().trim().toLowerCase();
            if (confirmations.indexOf(confirmation) === -1) {
                return Travian.defaultErrorFunction({
                    message: <?= json_encode(T('Options', 'confirmation.typeExactText'), JSON_UNESCAPED_UNICODE); ?>
                });
            }
            var form = jQuery('#settings');
            if (!form.find('input[name="del"]').length) {
                form.append(jQuery('<input>', {type: 'hidden', name: 'del', value: '1'}));
            }
            form.trigger('submit');
        }

        function avatarRespawn() {
            var confirmations = ['respawn'].map(function (confirmation) {
                return confirmation.trim().toLowerCase();
            });
            var confirmation = jQuery('#confirmation_respawn').val().trim().toLowerCase();
            if (confirmations.indexOf(confirmation) === -1) {
                return Travian.defaultErrorFunction({
                    message: <?= json_encode(T('Options', 'confirmation.typeExactText'), JSON_UNESCAPED_UNICODE); ?>
                });
            }
            Travian.redirectTo('/respawn');
        }
    </script>

    <h4 class="round"><?= T("Options", "Change account name"); ?></h4>
    <table id="change_account_name" cellpadding="1" cellspacing="1" class="transparent">
        <tbody>
        <tr>
            <td colspan="2">
                <?= sprintf(
                    T("Options", "changeNameDesc"),
                    $config->gold->changeName->freeTimes,
                    $config->gold->changeName->freeTillPopulation,
                    $config->gold->accountChangeNameGold,
                    $config->gold->changeName->impossibleAfterPopulation,
                    $config->gold->changeName->impossibleAfterPopulation
                ); ?>
            </td>
        </tr>
        <tr>
            <th><?= T("Options", "Number of changes"); ?></th>
            <td>
                <?php
                if ($usedFreeRenames) {
                    echo (int)$session->getTotalNameChanges();
                } else {
                    echo sprintf(
                        T("Options", "x of y changes"),
                        $session->getTotalNameChanges(),
                        $config->gold->changeName->freeTimes
                    );
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><label for="account_rename_new_name"><?= T("Options", "Enter new account name"); ?></label></th>
            <td>
                <input id="account_rename_new_name" tabindex="1" class="text" type="text"
                       name="account_rename_new_name" maxlength="15"
                       value="<?= isset($_POST['account_rename_new_name']) ? htmlspecialchars((string)$_POST['account_rename_new_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"/>
            </td>
        </tr>
        <tr>
            <th></th>
            <td class="buttonColumn">
                <?php
                if ($canGoldRename) {
                    echo getButton([
                        'type' => 'button',
                        'value' => T("Options", "Change name"),
                        'class' => 'textButtonV1 gold ',
                        'coins' => $config->gold->accountChangeNameGold,
                        'onclick' => 'Travian.Game.PremiumFeature.AvatarNameChange(getAccountRenameValues()); return false;',
                    ], ['data' => ['type' => 'button']], T("Options", "Change name"));
                } elseif ($canFreeRename) {
                    echo getButton([
                        'type' => 'submit',
                        'value' => T("Options", "Change name"),
                        'class' => 'textButtonV1 green ',
                        'name' => 'free_rename',
                    ], ['data' => ['type' => 'submit']], T("Options", "Change name"));
                } else {
                    echo getButton([
                        'type' => 'button',
                        'value' => T("Options", "Change name"),
                        'class' => 'textButtonV1 gold disabled',
                        'coins' => $config->gold->accountChangeNameGold,
                    ], ['data' => ['type' => 'button']], T("Options", "Change name"));
                }
                ?>
            </td>
        </tr>
        </tbody>
    </table>
<?php if (is_array($vars['social'] ?? null)): ?>
    <h4 class="round"><?= T('Login', 'Social accounts'); ?></h4>
    <?php if ($vars['social']['notice'] !== null): ?>
        <p class="<?= $vars['social']['notice']['error'] ? 'error' : 'success'; ?>"><?= htmlspecialchars($vars['social']['notice']['text'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <table id="socialAccounts" cellpadding="1" cellspacing="1" class="account transparent">
        <tbody>
        <?php foreach ($vars['social']['rows'] as $row): ?>
            <tr>
                <th><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                <td>
                    <?php if ($row['email'] === null): ?>
                        <form action="/oauth/<?= $row['provider']->value; ?>/link" method="post">
                            <input type="hidden" name="a" value="<?= $session->getChecker(); ?>"/>
                            <span class="state"><?= T('Login', 'Not linked'); ?></span>
                            <?= getButton(['type' => 'submit', 'class' => 'textButtonV1 green '], ['data' => ['type' => 'submit']], T('Login', 'Link')); ?>
                        </form>
                    <?php else: ?>
                        <form action="/oauth/<?= $row['provider']->value; ?>/unlink" method="post">
                            <input type="hidden" name="a" value="<?= $session->getChecker(); ?>"/>
                            <span class="state"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <input class="text" type="password" name="password" autocomplete="current-password" placeholder="<?= htmlspecialchars((string)T('Login', 'input_password_label'), ENT_QUOTES, 'UTF-8'); ?>" required="required"/>
                            <?= getButton(['type' => 'submit', 'class' => 'textButtonV1 green '], ['data' => ['type' => 'submit']], T('Login', 'Unlink')); ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<h4 class="round"><?= T('Options', 'Export data'); ?></h4>
<form id="exportData" action="/options" method="post">
    <input type="hidden" name="s" value="2"/>
    <input type="hidden" name="a" value="<?= $session->getChecker(); ?>"/>
    <input type="hidden" name="exportData" value="1"/>
    <table cellpadding="1" cellspacing="1" id="export_data" class="account transparent">
        <tbody>
        <tr>
            <td colspan="3"><?= T('Options', 'Download a JSON file with your account data, villages, hero, reports, messages and linked social accounts.'); ?></td>
        </tr>
        <tr>
            <td>
                <label for="export_password"><strong><?= T('Login', 'input_password_label'); ?></strong></label>
            </td>
            <td>
                <input class="text" type="password" id="export_password" name="password" autocomplete="current-password" required="required"/>
            </td>
            <td>
                <?= getButton(['type' => 'submit', 'class' => 'textButtonV1 green '], ['data' => ['type' => 'submit']], T('Options', 'Download')); ?>
            </td>
        </tr>
        </tbody>
    </table>
</form>
<form id="settings" action="/options" method="post">
    <input type="hidden" name="e" value="2"/>
    <input type="hidden" name="s" value="2"/>
    <input type="hidden" name="a" value="<?= $session->getChecker(); ?>"/>

    <h4 class="round spacer"><?= T("Options", "Newsletter"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="newsletter" class="account transparent">
        <tbody>
        <tr>
            <td colspan="2"><?= T("Options", "Here you can subscribe or unsubscribe to our newsletter"); ?></td>
        </tr>
        <tr>
            <th><?= T("Options", "Newsletter"); ?></th>
            <td>
                <label><input class="radio" type="radio" name="newsletter" value="1" <?= !empty($vars['newsletter']) ? ' checked="checked"' : ''; ?> /> <?= T("Options", "Yes"); ?></label>
                &nbsp;&nbsp;&nbsp;
                <label><input class="radio" type="radio" name="newsletter" value="0" <?= empty($vars['newsletter']) ? ' checked="checked"' : ''; ?> /> <?= T("Options", "No"); ?></label>
            </td>
        </tr>
        <?php if (empty($vars['deliveryConfigured'])): ?>
        <tr>
            <td colspan="2"><?= T('Mail', 'Email delivery is not configured for this world. Messages stay queued until it is.'); ?></td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4 class="round spacer"><?= T('Mail', 'Email notifications'); ?></h4>
    <input type="hidden" name="emailNotifications" value="1"/>
    <table cellpadding="1" cellspacing="1" id="emailNotifications" class="account transparent">
        <tbody>
        <?php foreach ($vars['notifications'] as $kind => $notification): ?>
        <tr>
            <td>
                <label><input class="check" type="checkbox" name="emailNotification[<?= htmlspecialchars((string)$kind, ENT_QUOTES, 'UTF-8'); ?>]" value="1" <?= $notification['enabled'] ? ' checked="checked"' : ''; ?> /> <?= htmlspecialchars((string)$notification['label'], ENT_QUOTES, 'UTF-8'); ?></label>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($vars['deliveryConfigured'])): ?>
        <tr>
            <td><?= T('Mail', 'Email delivery is not configured for this world. Messages stay queued until it is.'); ?></td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4 class="round spacer"><?= T("Options", "Support colorBlind title"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="colorblind" class="account transparent">
        <tbody>
        <tr>
            <td colspan="2"><?= T("Options", "Support colorBlind desc"); ?></td>
        </tr>
        <tr>
            <th><?= T("Options", "Use colorBlind?"); ?></th>
            <td>
                <label><input class="radio" type="radio" name="colorblind" value="1" <?= (int)$vars['colorBlind'] === 1 ? ' checked="checked"' : ''; ?> /> <?= T("Options", "Yes"); ?></label>
                &nbsp;&nbsp;&nbsp;
                <label><input class="radio" type="radio" name="colorblind" value="0" <?= (int)$vars['colorBlind'] === 0 ? ' checked="checked"' : ''; ?> /> <?= T("Options", "No"); ?></label>
            </td>
        </tr>
        </tbody>
    </table>

    <h4 class="round spacer"><?= T("Options", "Respawn avatar"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="respawnAvatar" class="account transparent">
        <tbody>
        <tr>
            <td colspan="3"><?= T("Options", "respawnAvatarDesc"); ?></td>
        </tr>
        <tr>
            <td>
                <label for="confirmation_respawn"<?= $canRespawn ? '' : ' class="disabled"'; ?>>
                    <strong><?= T("Options", "Please type: respawn"); ?></strong>
                </label>
            </td>
            <td>
                <input class="text<?= $canRespawn ? '' : ' disabled'; ?>" type="text" id="confirmation_respawn"
                       name="confirmation_respawn" maxlength="20" placeholder="respawn"
                       <?= $canRespawn ? '' : ' disabled="disabled"'; ?> />
            </td>
            <td>
                <?php
                if ($canRespawn) {
                    echo getButton([
                        'type' => 'button',
                        'value' => T("Options", "Respawn avatar"),
                        'class' => 'textButtonV1 green ',
                        'onclick' => 'avatarRespawn(); return false;',
                    ], ['data' => ['type' => 'button']], T("Options", "Respawn avatar"));
                } else {
                    echo getButton([
                        'type' => 'button',
                        'value' => T("Options", "Respawn avatar"),
                        'class' => 'textButtonV1 green disabled',
                        'disabled' => 'disabled',
                    ], ['data' => ['type' => 'button']], T("Options", "Respawn avatar"));
                }
                ?>
            </td>
        </tr>
        <?php if (!$canRespawn): ?>
        <tr>
            <td colspan="3"><?= T("Options", "respawnOnlyBeginnerProtection"); ?></td>
        </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h4 class="round spacer"><?= T("Options", "Delete account"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="del_acc" class="account transparent">
        <tbody>
            <tr>
                <td colspan="3"><?= T("Options", "deleteDesc"); ?></td>
            </tr>
            <?php if (!$m->isDeletion($session->getPlayerId())): ?>
                <tr>
                    <td>
                        <label for="confirmation_delete">
                            <strong><?= T("Options", "Please type: delete"); ?></strong>
                        </label>
                    </td>
                    <td>
                        <input class="text" type="text" id="confirmation_delete" name="confirmation_delete"
                               maxlength="20" placeholder="delete" />
                    </td>
                    <td>
                        <?= getButton([
                            'type' => 'button',
                            'value' => T("Options", "Delete"),
                            'class' => 'textButtonV1 green ',
                            'onclick' => 'avatarDelete(); return false;',
                        ], ['data' => ['type' => 'button']], T("Options", "Delete")); ?>
                    </td>
                </tr>
            <?php else:
                $timestamp = $m->getDeletionTimestamp($session->getPlayerId());
                ?>
                <tr>
                    <td colspan="3" class="count">
                        <?php
                        echo !isServerFinished()
                            ? '<button type="button" class="icon " onclick="window.location.href = \'/options/account?id=' . $session->getPlayerId() . '&amp;a=1&amp;e=2\'; return false;"><img src="img/spacer.gif" class="del" alt="del"></button> '
                            : '';
                        echo sprintf(T("inGame", "The account will be deleted in"), appendTimer($timestamp - time()));
                        ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="submitButtonContainer">
        <?= getButton([
            'type' => 'submit',
            'value' => T("Global", "General.save"),
            'class' => 'textButtonV1 green ',
        ], ['data' => ['type' => 'submit']], T("Global", "General.save")); ?>
    </div>
</form>
<script type="text/javascript">
    jQuery(function () {
        Travian.Form.UnloadHelper.watchHtmlForm(jQuery('#settings'));
        var deleteInput = document.getElementById('confirmation_delete');
        if (deleteInput) {
            deleteInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    avatarDelete();
                }
            });
        }
        var respawnInput = document.getElementById('confirmation_respawn');
        if (respawnInput && !respawnInput.disabled) {
            respawnInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    avatarRespawn();
                }
            });
        }
    });
</script>
