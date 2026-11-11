<div class="vacationModeConfirmation">
    <p><?=T('Options', 'Are you sure you want to enter vacation mode?'); ?></p>
    <input type="hidden" name="days" value="<?=$vars['days']; ?>">
    <input type="hidden" name="<?= \Core\Session::getCheckerName(); ?>"
           value="<?= htmlspecialchars((string)($vars['checker'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="buttonWrapper">
        <?= getButton([
            'type' => 'submit',
            'value' => T('Options', 'enter vacation mode now'),
            'class' => 'textButtonV1 green ',
            'version' => 'textButtonV1',
        ], ['data' => ['type' => 'submit', 'version' => 'textButtonV1']], T('Options', 'enter vacation mode now')); ?>
    </div>
</div>
