<?php
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div id="info">
    <h4 class="roundV2 details"><?= T("Embassy", "General Information", "General Information"); ?></h4>
    <table class="transparent">
        <tbody>
        <tr>
            <th><?= T("Buildings", "Tag"); ?></th>
            <td><?= $escape($vars['tag']); ?></td>
            <td class="empty"></td>
        </tr>
        <tr>
            <th><?= T("Buildings", "Name"); ?></th>
            <td><?= $escape($vars['name']); ?></td>
            <td class="actions">
                <?= getButton(
                    [
                        'type' => 'button',
                        'value' => T("Buildings", "to the alliance"),
                        'class' => 'textButtonV1 green ',
                        'version' => 'textButtonV1',
                        'onclick' => "this.disabled = true; window.location.href = '/alliance'; return false;",
                    ],
                    ['data' => ['type' => 'button', 'version' => 'textButtonV1']],
                    T("Buildings", "to the alliance")
                ); ?>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<div id="leave">
    <h4 class="roundV2 details"><?= T("Embassy", "Leave the alliance"); ?></h4>
    <div class="content">
        <p>
            <span class="alert bold"><?= T("Embassy", "If you leave the alliance your contribution statistics will reset"); ?>.</span>
            <?= T("Embassy", "Alliance leave contribution note"); ?>
        </p>
        <form method="post" action="<?= $escape($vars['formAction']); ?>">
            <input type="hidden" name="action" value="alliance">
            <?= \Core\Session::getCheckerInput(); ?>
            <div class="actionFields">
                <span><strong><?= T("Embassy", "Please type", "Please type:"); ?> leave</strong></span>
                <span><input class="text" type="text" name="confirmation_leave" placeholder="leave" autocomplete="off"></span>
                <button type="submit" value="leave" name="do" id="do" class="textButtonV1 grey negativeAction" version="textButtonV1"> <?= T("Embassy", "Leave alliance", "Leave alliance"); ?></button>
            </div>
            <p class="error"><?= $escape($vars['leaveError']); ?></p>
        </form>
    </div>
</div>
