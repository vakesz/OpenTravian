<?php

declare(strict_types=1);

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$form = is_array($vars['form'] ?? null) ? $vars['form'] : [];
$errors = is_array($vars['errors'] ?? null) ? $vars['errors'] : [];
$worldOptions = is_array($vars['worldOptions'] ?? null) ? $vars['worldOptions'] : [];
$categories = is_array($vars['categories'] ?? null) ? $vars['categories'] : [];
$deliveryConfigured = !empty($vars['deliveryConfigured']);
$formError = is_string($vars['formError'] ?? null) ? $vars['formError'] : '';
$supportUrl = is_string($vars['supportUrl'] ?? null) ? $vars['supportUrl'] : '/support/ticket';
?>
<div class="supportCard">
    <p class="supportCardDescription"><?= T('Support', 'description') ?></p>
    <p class="supportCardScope"><?= T('Support', 'Game errors, login errors and game rules related questions') ?></p>

    <?php if (!$deliveryConfigured): ?>
        <p class="supportError" role="alert"><?= T('Support', 'errors.Delivery is not configured') ?></p>
    <?php endif; ?>
    <?php if ($formError !== ''): ?>
        <p class="supportError" role="alert"><?= $escape($formError) ?></p>
    <?php endif; ?>

    <form class="supportForm" method="post" action="<?= $escape($supportUrl) ?>" name="support" id="support" novalidate>
        <?= \Core\Session::getCheckerInput() ?>
        <div class="supportField">
            <label for="support_gameworld"><?= T('Support', 'Game world') ?></label>
            <select id="support_gameworld" name="support[gameworld]" required>
                <option value="please_select"<?= ($form['gameworld'] ?? '') === 'please_select' ? ' selected' : '' ?>><?= T('Support', 'Choose') ?></option>
                <option value="i_do_not_know"<?= ($form['gameworld'] ?? '') === 'i_do_not_know' ? ' selected' : '' ?>><?= T('Support', 'I don´t know') ?></option>
                <?php foreach ($worldOptions as $value => $label): ?>
                    <option value="<?= $escape((string) $value) ?>"<?= ($form['gameworld'] ?? '') === $value ? ' selected' : '' ?>><?= $escape((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (($errors['gameworld'] ?? '') !== ''): ?><div class="supportError" role="alert"><?= $escape($errors['gameworld']) ?></div><?php endif; ?>
        </div>

        <div class="supportField">
            <label for="support_username"><?= T('Support', 'Username') ?></label>
            <input id="support_username" class="text" type="text" name="support[username]" maxlength="64" value="<?= $escape((string) ($form['username'] ?? '')) ?>" autocomplete="username" required>
            <?php if (($errors['username'] ?? '') !== ''): ?><div class="supportError" role="alert"><?= $escape($errors['username']) ?></div><?php endif; ?>
        </div>

        <div class="supportField">
            <label for="support_email"><?= T('Support', 'Email') ?></label>
            <input id="support_email" class="text" type="email" name="support[email]" maxlength="<?= \Game\SupportContact::MAX_LENGTH ?>" value="<?= $escape((string) ($form['email'] ?? '')) ?>" autocomplete="email" required>
            <?php if (($errors['email'] ?? '') !== ''): ?><div class="supportError" role="alert"><?= $escape($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="supportField">
            <label for="support_type"><?= T('Support', 'Category') ?></label>
            <select id="support_type" name="support[type]" required>
                <option value="please_select"<?= ($form['type'] ?? '') === 'please_select' ? ' selected' : '' ?>><?= T('Support', 'Choose') ?></option>
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?= $escape((string) $value) ?>"<?= ($form['type'] ?? '') === $value ? ' selected' : '' ?>><?= $escape((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (($errors['type'] ?? '') !== ''): ?><div class="supportError" role="alert"><?= $escape($errors['type']) ?></div><?php endif; ?>
        </div>

        <div class="supportField">
            <label for="support_message"><?= T('Support', 'Message') ?></label>
            <textarea id="support_message" name="support[message]" rows="7" maxlength="10000" required><?= $escape((string) ($form['message'] ?? '')) ?></textarea>
            <?php if (($errors['message'] ?? '') !== ''): ?><div class="supportError" role="alert"><?= $escape($errors['message']) ?></div><?php endif; ?>
        </div>

        <div class="supportSubmit">
            <button type="submit" name="support[submit]" value="request" class="textButtonV1 green"<?= $deliveryConfigured ? '' : ' disabled' ?>>
                <span class="button-container addHoverClick"><span class="button-background"><span class="buttonStart"><span class="buttonEnd"><span class="buttonMiddle"></span></span></span></span><span class="button-content"><?= T('Support', 'send request') ?></span></span>
            </button>
        </div>
    </form>
</div>
