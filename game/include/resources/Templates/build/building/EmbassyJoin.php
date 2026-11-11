<?php
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div id="join">
    <?php if (!empty($vars['error'])): ?>
        <p class="error"><?= $escape($vars['error']); ?></p>
    <?php endif; ?>
    <?php if (empty($vars['invitations'])): ?>
        <?= T("Buildings", "There are no invitations available"); ?>
    <?php else: ?>
        <table class="transparent">
            <tbody>
            <?php foreach ($vars['invitations'] as $invitation): ?>
                <tr>
                    <td>
                        <?= sprintf(
                            T("Embassy", "Alliance %s (%s), invited by %s"),
                            '<a href="' . allianceUrl((int) $invitation['aid']) . '">' . $escape($invitation['name']) . '</a>',
                            '<a href="' . allianceUrl((int) $invitation['aid']) . '">' . $escape($invitation['tag']) . '</a>',
                            '<a href="' . profileUrl((int) $invitation['fromUid']) . '">' . $escape($invitation['from']) . '</a>'
                        ); ?>
                    </td>
                    <td class="actions">
                        <button type="button" class="textButtonV1 green" version="textButtonV1"
                                onclick="showJoinAllianceDialog(<?= (int)$invitation['id']; ?>, <?= $escape(json_encode($invitation['name'], JSON_HEX_APOS | JSON_HEX_QUOT)); ?>); return false;"><?= T("Embassy", "Join", "Join"); ?></button>
                        <button type="button" class="textButtonV1 grey" version="textButtonV1"
                                onclick="submitAllianceInvitation('refuse', <?= (int)$invitation['id']; ?>); return false;"><?= T("Buildings", "refuse"); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<form id="allianceInvitationForm" method="post" action="<?= $escape($vars['formAction']); ?>" style="display:none">
    <?= \Core\Session::getCheckerInput(); ?>
    <input type="hidden" name="action" value="alliance">
    <input type="hidden" name="do" value="">
    <input type="hidden" name="d" value="">
</form>
<script type="text/javascript">
    function submitAllianceInvitation(action, invitationId) {
        var form = document.getElementById('allianceInvitationForm');
        form.elements['do'].value = action;
        form.elements['d'].value = invitationId;
        form.submit();
    }
    Travian.Translation.add({'allianz.joinDialogTitle': <?= json_encode(T("Embassy", "Join alliance dialog title", "Join alliance: [NAME]"), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>});
    function showJoinAllianceDialog(invitationId, name) {
        new Travian.Dialog.Confirmation({
            title: Travian.Translation.translate('{allianz.joinDialogTitle}').replace('[NAME]', name),
            message: <?= json_encode(T("Embassy", "Joining cooldown note", "After joining an alliance, there is a 24-hour cooldown before you can leave."), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
            confirmText: <?= json_encode(T("Embassy", "Join", "Join")); ?>,
            cancelText: <?= json_encode(T("Global", "General.cancel", "Cancel")); ?>,
            confirmClass: 'green',
            cancelClass: 'grey',
            buttonCancel: true,
            context: 'joinAlliance',
            onCancel: function () {
                Travian.WindowManager.closeByContext('joinAlliance');
            },
            onConfirm: function () {
                submitAllianceInvitation('join', invitationId);
            }
        }).show();
    }
</script>
