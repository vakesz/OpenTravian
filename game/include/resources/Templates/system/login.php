<?php

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$gpackUrl = $escape(get_gpack_cdn_mainPage_url());
$publicLoginUrl = is_string($vars['publicLoginUrl'] ?? null) ? $vars['publicLoginUrl'] : '/';
$publicRecoveryUrl = is_string($vars['publicRecoveryUrl'] ?? null)
    ? $vars['publicRecoveryUrl']
    : '/?forgotPassword=true';
$publicRegisterUrl = is_string($vars['publicRegisterUrl'] ?? null) ? $vars['publicRegisterUrl'] : '/homepage#registration';
$loginAction = $vars['isAdmin'] ? '/admin.php' : $publicLoginUrl;
$showRecovery = (bool)($vars['forgotPassword'] ?? false);
$emailAvailable = (bool)($vars['emailAvailable'] ?? true);
$recoveryUnavailable = (string)T('Login', 'pwResetUnavailable');
$hasReferenceLoginCopy = in_array(get_locale(), ['en-US', 'ar-AE', 'el-GR'], true);
$loginCopy = static function (string $referenceKey, string $legacyKey) use ($hasReferenceLoginCopy): string {
    if ($hasReferenceLoginCopy) {
        return (string)T('Login', $referenceKey);
    }

    return (string)T('Login', $legacyKey);
};
$recoveryCopy = static function (string $sourceKey): string {
    return (string)T('Login', $sourceKey);
};
$worldTitle = (string)$vars['worldTitle'];
$title = $showRecovery
    ? $recoveryCopy('Password Recovery')
    : ($hasReferenceLoginCopy
        ? str_replace('[WORLD]', $worldTitle, $loginCopy('loginToWorld', 'Welcome'))
        : sprintf(T('Login', 'Welcome'), $worldTitle));
$emailLabel = $loginCopy('input_emailAccount_label', 'accountNameOrEmailAddress');
$emailPlaceholder = $loginCopy('input_emailAccount_placeholder', 'accountNameOrEmailAddress');
$emailEmpty = $loginCopy('input_emailAccount_error_empty', 'userErrors.empty');
$passwordLabel = $loginCopy('input_password_label', 'pass');
$passwordPlaceholder = $loginCopy('input_password_placeholder', 'pass');
$passwordEmpty = $loginCopy('input_password_error_empty', 'pwErrors.empty');
$forgotPassword = $loginCopy('forgotYourPassword', 'PasswordForgotten?');
$recover = $loginCopy('recover', 'Request password');
$loginSubmit = $loginCopy('login', 'Login');
$recoveryBack = $recoveryCopy('Back to Login');
$recoveryDescription = $recoveryCopy('Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.');
$recoveryEmailLabel = $recoveryCopy('Enter your email address');
$recoveryEmailEmpty = $recoveryCopy('Please enter your email address');
$recoveryEmailInvalid = $recoveryCopy('Please enter a valid email address. E.g. mymail@provider.com');
$recoverySubmit = $recoveryCopy('Recover password');
$recoverySuccess = $recoveryCopy('If a Lobby Account exists for the provided email address, we have sent you instructions to reset your password to the email:');
$recoverySuccessReturn = $recoveryCopy('Once you have recovered your password, you can log in again.');
$showLobbyAccountBanner = $hasReferenceLoginCopy;
$lobbyDescription = $showLobbyAccountBanner ? $loginCopy('lobbyAccountDescription', 'Login') : '';
$createLobbyAccount = $showLobbyAccountBanner ? $loginCopy('createLobbyAccount', 'Login') : '';
?>
<div id="loginScene" class="scene contentV2<?= $showRecovery ? ' loginRecoveryScene' : ''; ?>">
    <div id="dialogOverlay" class="dialogOverlay dialogVisible enabled dark">
        <div class="dialogWrapper dialogV2" data-context="">
            <div class="dialog basic">
                <div class="dialogContainer">
                    <div class="dialogContents">
                        <?php if ($showRecovery): ?>
                            <form id="passwordRecovery" class="modal" action="<?= $escape($publicRecoveryUrl); ?>" method="post" accept-charset="UTF-8">
                                <button class="green buttonSecondary back withText" type="button" onclick="window.location.href='<?= $escape($publicLoginUrl); ?>'">
                                    <div><svg viewBox="0 0 12 7.4" aria-hidden="true"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"></path></svg><span><?= $escape($recoveryBack); ?></span></div>
                                </button>
                                <h1><?= $escape($title); ?></h1>
                                <?php if (!$emailAvailable): ?>
                                    <p class="loginRecoveryDescription" role="alert"><?= $escape($recoveryUnavailable); ?></p>
                                <?php elseif ($vars['success']): ?>
                                    <p class="loginRecoverySuccess" role="status"><?= $escape($recoverySuccess); ?> <?= $escape($vars['pw_email']); ?></p>
                                    <p class="loginRecoveryDescription"><?= $escape($recoverySuccessReturn); ?></p>
                                <?php else: ?>
                                    <p class="loginRecoveryDescription"><?= $escape($recoveryDescription); ?></p>
                                    <label class="text<?= $vars['newPassErr'] !== '' ? ' invalid' : ''; ?>">
                                        <input
                                            id="passwordRecoveryEmail"
                                            placeholder="<?= $escape($recoveryEmailLabel); ?>"
                                            type="text"
                                            name="pw_email"
                                            value="<?= $escape($vars['pw_email']); ?>"
                                            autocomplete="email"
                                        >
                                        <div class="label"><?= $escape($recoveryEmailLabel); ?></div>
                                        <svg viewBox="0 0 20 20" class="valid" aria-hidden="true"><path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"></path></svg>
                                        <svg viewBox="0 0 22 19" class="invalid" aria-hidden="true"><path d="M11 4l7.5 13h-15L11 4m0-4L0 19h22L11 0z"></path><path d="M12 14h-2v2h2zM12 8h-2v5h2z"></path></svg>
                                        <div class="validation<?= $vars['newPassErrorRule'] === 'notEmpty' ? ' show' : ''; ?>" data-rule="notEmpty" role="alert"><?= $escape($recoveryEmailEmpty); ?></div>
                                        <div class="validation<?= $vars['newPassErrorRule'] === 'isEmail' ? ' show' : ''; ?>" data-rule="isEmail" role="alert"><?= $escape($recoveryEmailInvalid); ?></div>
                                    </label>
                                    <input type="hidden" name="forgotPassword" value="1">
                                    <div class="actions">
                                        <button class="green buttonFramed withText withLoadingIndicator" type="submit"><div><?= $escape($recoverySubmit); ?></div></button>
                                    </div>
                                    <script>
                                        (function () {
                                            const form = document.getElementById('passwordRecovery');
                                            const email = document.getElementById('passwordRecoveryEmail');
                                            const field = email ? email.closest('label.text') : null;

                                            if (!form || !email || !field) {
                                                return;
                                            }

                                            const clearValidation = function () {
                                                field.classList.remove('invalid');
                                                email.removeAttribute('aria-invalid');
                                                field.querySelectorAll('.validation').forEach(function (validation) {
                                                    validation.classList.remove('show');
                                                });
                                            };
                                            const showValidation = function (rule) {
                                                clearValidation();
                                                field.classList.add('invalid');
                                                email.setAttribute('aria-invalid', 'true');
                                                const validation = field.querySelector('.validation[data-rule="' + rule + '"]');
                                                if (validation) {
                                                    validation.classList.add('show');
                                                }
                                            };
                                            const validationRule = function () {
                                                const value = email.value.trim();
                                                if (value === '') {
                                                    return 'notEmpty';
                                                }

                                                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'isEmail';
                                            };

                                            form.addEventListener('submit', function (event) {
                                                const rule = validationRule();
                                                if (rule === '') {
                                                    return;
                                                }

                                                event.preventDefault();
                                                showValidation(rule);
                                            });
                                            email.addEventListener('input', clearValidation);
                                        })();
                                    </script>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <form method="get" action="<?= $escape($loginAction); ?>" accept-charset="UTF-8">
                                <div class="dialogDragBar"></div>
                                <div class="content" id="dialogContent">
                                    <h1><?= $escape($title); ?></h1>
                                    <div class="formV2">
                                        <label class="input fixed<?= $vars['userError'] !== '' ? ' invalid' : ''; ?>">
                                            <input placeholder="<?= $escape($emailPlaceholder); ?>" type="text" name="name">
                                            <div class="label"><?= $escape($emailLabel); ?></div>
                                            <svg viewBox="0 0 20 20" class="valid" aria-hidden="true"><path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"></path></svg>
                                            <svg viewBox="0 0 22 19" class="invalid" aria-hidden="true"><path d="M11 4l7.5 13h-15L11 4m0-4L0 19h22L11 0z"></path><path d="M12 14h-2v2h2zM12 8h-2v5h2z"></path></svg>
                                            <div class="validation<?= $vars['userError'] !== '' ? ' show' : ''; ?>" data-rule="notEmpty"><?= $vars['userError'] !== '' ? $vars['userError'] : $escape($emailEmpty); ?></div>
                                        </label>

                                        <label class="input fixed<?= $vars['pwError'] !== '' ? ' invalid' : ''; ?>">
                                            <input placeholder="<?= $escape($passwordPlaceholder); ?>" type="password" name="password">
                                            <div class="label"><?= $escape($passwordLabel); ?></div>
                                            <svg viewBox="0 0 20 20" class="valid" aria-hidden="true"><path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"></path></svg>
                                            <svg viewBox="0 0 22 19" class="invalid" aria-hidden="true"><path d="M11 4l7.5 13h-15L11 4m0-4L0 19h22L11 0z"></path><path d="M12 14h-2v2h2zM12 8h-2v5h2z"></path></svg>
                                            <svg viewBox="0 0 16.5 11.3" class="visibility" aria-hidden="true"><path d="M8.2 9c.9 0 1.7-.3 2.4-1s1-1.5 1-2.4c0-.9-.3-1.7-1-2.4s-1.5-1-2.4-1c-.9 0-1.7.3-2.4 1s-1 1.5-1 2.4c0 .9.3 1.7 1 2.4s1.5 1 2.4 1zm0-1.3c-.6 0-1-.2-1.4-.6-.4-.4-.6-.9-.6-1.5s.2-1 .6-1.4c.4-.4.9-.6 1.4-.6.6 0 1 .2 1.4.6.4.4.6.9.6 1.4s-.2 1-.6 1.4c-.3.5-.8.7-1.4.7zm0 3.6c-1.8 0-3.5-.5-5-1.5C1.8 8.7.7 7.3 0 5.6c.7-1.7 1.8-3.1 3.3-4.1C4.8.5 6.5 0 8.3 0c1.8 0 3.5.5 5 1.5s2.6 2.4 3.3 4.1c-.7 1.7-1.8 3.1-3.3 4.1-1.5 1-3.3 1.6-5.1 1.6zm0-1.5c1.4 0 2.7-.4 3.9-1.1 1.2-.7 2.1-1.8 2.7-3-.6-1.2-1.5-2.3-2.7-3-1.1-.8-2.5-1.2-3.9-1.2s-2.7.4-3.9 1.1c-1.2.7-2.1 1.8-2.7 3 .6 1.2 1.5 2.3 2.7 3 1.2.8 2.6 1.2 3.9 1.2z"></path></svg>
                                            <div class="validation<?= $vars['pwError'] !== '' ? ' show' : ''; ?>" data-rule="notEmpty"><?= $vars['pwError'] !== '' ? $vars['pwError'] : $escape($passwordEmpty); ?></div>
                                        </label>

                                        <div id="recaptcha"></div>
                                        <div class="centeredText">
                                            <button class="textButtonV2 buttonFramed rectangle withText withLoadingIndicator green" type="submit"><div><?= $escape($loginSubmit); ?></div></button>
                                            <?php if ($emailAvailable): ?>
                                            <div><?= $escape($forgotPassword); ?>&nbsp;<a href="<?= $escape($publicRecoveryUrl); ?>"><?= $escape($recover); ?></a></div>
                                            <?php endif; ?>
                                        </div>
                                        <svg viewBox="0 0 200 10" class="divider" aria-hidden="true"><path d="m200 5-78.7-2.5c.2.75.54 1.57.67 2.35h-2.49c-.08-1.31-1.16-2.35-2.48-2.35s-2.41 1.04-2.48 2.35h-9.67L100 0l-4.85 4.85h-9.67C85.4 3.54 84.32 2.5 83 2.5s-2.41 1.04-2.48 2.35h-2.49c.13-.78.47-1.6.67-2.35L0 5l78.7 2.5c-.22-.74-.55-1.58-.67-2.35h2.49C80.6 6.46 81.68 7.5 83 7.5s2.41-1.04 2.48-2.35h9.67L100 10l4.85-4.85h9.67c.08 1.31 1.16 2.35 2.48 2.35s2.41-1.04 2.48-2.35h2.49c-.12.77-.46 1.61-.67 2.35L200 5Z"></path></svg>
                                        <?php if ($showLobbyAccountBanner): ?>
                                        <div class="lobbyAccountInfoBanner">
                                            <div class="logoLobbyAccount">
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 173.48 60" aria-label="Travian Lobby Account">
                                                    <image width="156" height="250" transform="matrix(.24 0 0 .24 .22 .14)" xlink:href="<?= $gpackUrl; ?>logo.png"></image>
                                                    <path d="M45.15,51.4v-9.31h1.19v8.21h4.41v1.1h-5.6ZM53.57,46.87c-.23-6.56,8.72-6.53,8.57-.11.15,6.33-8.6,6.37-8.57.11ZM54.8,46.89c-.11,4.88,6.35,4.78,6.12-.13.21-4.96-6.42-5.1-6.12.13ZM65.4,51.4v-9.31c1.09.07,4.09-.2,5.01.28,1.67.73,1.79,3.3.07,4.07,2.41.59,2.11,4.35-.22,4.8-.88.28-3.88.11-4.86.16ZM66.59,46.01c1.39-.11,4,.54,3.98-1.38-.03-1.27-.92-1.46-2.19-1.43,0,0-1.79,0-1.79,0v2.81ZM66.59,50.3h2.23c1.15.08,2.13-.39,2.12-1.6,0-2.17-2.9-1.49-4.35-1.6v3.2ZM75.53,51.4v-9.31c1.09.07,4.09-.2,5.01.28,1.67.73,1.79,3.3.07,4.07,2.41.59,2.11,4.35-.22,4.8-.88.28-3.88.11-4.86.16ZM76.72,46.01c1.39-.11,4,.54,3.98-1.38-.03-1.27-.92-1.46-2.19-1.43,0,0-1.79,0-1.79,0v2.81ZM76.72,50.3h2.23c1.15.08,2.13-.39,2.12-1.6,0-2.17-2.9-1.49-4.35-1.6v3.2ZM88.23,51.4v-3.94l-3.45-5.36h1.44c.66,1.06,2.13,3.31,2.68,4.38.53-1,2.07-3.35,2.71-4.38h1.38l-3.58,5.36v3.94h-1.19ZM99.19,51.4l3.44-9.31h1.28l3.67,9.31h-1.35l-1.04-2.82h-3.75l-.98,2.82h-1.26ZM101.77,47.58h3.04c-.39-1.1-1.31-3.52-1.57-4.51-.18,1.17-1.07,3.36-1.47,4.51ZM116.7,48.14l1.19.31c-.49,2.89-3.84,3.91-6.07,2.5-1.32-.88-1.85-2.67-1.86-4.27,0-1.74.6-3.34,2.06-4.18,2.11-1.24,5.15-.41,5.71,2.15l-1.17.29c-.4-1.96-2.69-2.46-4.12-1.43-.97.7-1.24,2.02-1.26,3.17.02,1.35.27,2.69,1.37,3.4,1.68,1.06,3.86.13,4.14-1.93ZM127.51,48.14l1.19.31c-.49,2.89-3.84,3.91-6.07,2.5-1.32-.88-1.85-2.67-1.86-4.27,0-1.74.6-3.34,2.06-4.18,2.11-1.24,5.15-.41,5.71,2.15l-1.17.29c-.4-1.96-2.69-2.46-4.12-1.43-.97.7-1.24,2.02-1.26,3.17.02,1.35.27,2.69,1.37,3.4,1.68,1.06,3.86.13,4.14-1.93ZM131.58,46.87c-.23-6.56,8.72-6.53,8.57-.11.15,6.33-8.6,6.37-8.57.11ZM132.8,46.89c-.11,4.88,6.35,4.78,6.12-.13.21-4.96-6.42-5.1-6.12.13ZM149.33,42.1h1.19v5.38c.13,2.4-.98,4.16-3.5,4.09-2.55.09-3.67-1.62-3.54-4.09,0,0,0-5.38,0-5.38h1.19v5.37c-.08,1.76.47,3,2.26,2.98,2.02-.05,2.38-.89,2.42-2.98,0,0,0-5.37,0-5.37ZM154.26,51.4v-9.31h1.22l4.71,7.31v-7.31h1.14v9.31h-1.22l-4.71-7.31v7.31h-1.14ZM167.37,51.4v-8.21h-2.95v-1.1h7.1v1.1h-2.96v8.21h-1.19ZM53.87,35.01c-.39-.12-.63-.27-.72-.45-.18-.32-.29-.74-.32-1.27-.04-.76-.06-1.2-.08-1.3l-.05-3.92.11-6.57c.04-1.08.08-2.17.13-3.25.05-1.51.16-2.43.32-2.77.12-.02.31-.04.56-.06.25-.02.39-.03.43-.03,2.64,0,4.12.28,4.42.84.05.1.14.37.27.81.11.36.19.54.24.54s.11-.02.16-.06v-.36c0-.44.08-.92.23-1.45.15-.52.31-1.04.47-1.57l-.37-.09c-.89.24-1.91.36-3.06.36h-4.99l-6.73-.24c-.25,0-.51.41-.78,1.24-.12.48-.25.96-.38,1.45-.18.5-.27.77-.27.81v.21h.24c.11-.36.32-.7.63-1.02.31-.32.6-.52.86-.59.26-.07.94-.21,2.05-.41,1.27-.2,2.1-.3,2.49-.3.29,0,.43,1.5.43,4.49l-.08,8.56c-.14,2.17-.25,3.69-.34,4.57-.08.87-.22,1.47-.42,1.79-.09.1-.23.18-.43.24-.2.06-.31.15-.35.27l.54.06c.2-.08.37-.12.51-.12,1.13-.1,1.75-.15,1.88-.15l2.79.15c.2-.04.34-.1.43-.18-.02,0-.29-.08-.83-.24ZM77.92,35.13c-.09.12-.71.21-1.88.27-.09,0-.25,0-.47.03-.22.02-.36.03-.42.03-.95,0-1.72-.33-2.31-.99-.29-.32-.89-1.3-1.82-2.92-.38-.74-.76-1.49-1.15-2.23-.5-.88-1.01-1.6-1.53-2.14-.39-.4-1.18-.6-2.36-.6-.18,0-.27.12-.27.36v5.42c0,1.97.47,2.95,1.42,2.95.16,0,.29.04.38.12-.57.1-.88.15-.91.15-.05,0-.42-.01-1.09-.03-.67-.02-1.02-.03-1.06-.03-.34,0-1.11.09-2.3.27.14-.1.36-.33.64-.69.16-.3.24-1.41.24-3.31l.19-4.16v-1.99s.02-.29.05-.75c.04-.46.05-.71.05-.75v-2.02l-.13-3.95c0-.92-.19-1.91-.56-2.95-.02-.1-.11-.18-.27-.24l-.32-.12c.25-.1.45-.15.59-.15l1.5-.06c.54,0,1.24-.02,2.12-.06l2.79-.15c1.41,0,2.64.32,3.67.96,1.39.86,2.09,2.18,2.09,3.95,0,1.21-.29,2.33-.86,3.38-.38.68-.92,1.39-1.64,2.11-.63.64-.96,1.01-.99,1.11,0,.04.22.44.67,1.21.62,1.08,1.08,1.89,1.37,2.41.48.9.96,1.81,1.45,2.71.59,1.04,1.14,1.79,1.66,2.23.18.16.39.28.64.36.45.14.71.23.8.27ZM71.03,25.49c.59-.54.88-1.76.88-3.65,0-1.41-.46-2.85-1.39-4.34-1.05-1.69-2.27-2.53-3.65-2.53-.64,0-.95,0-.91,0l-.13,11.3h1.8c1.75,0,2.89-.26,3.4-.78ZM95.98,32.81c-.45-1.17-.89-2.32-1.34-3.47-.34-.98-.93-2.37-1.77-4.16h-5.41c-.27,0-.62.52-1.05,1.57-.21.62-.43,1.24-.64,1.84-.71,1.89-1.07,3.25-1.07,4.1s.29,1.4.86,1.66c.7.26,1.39.51,2.09.75l-3.56.45c-.27,0-.73-.04-1.39-.11-.66-.07-1.13-.11-1.42-.11.95-.8,1.77-2,2.48-3.6.71-1.6,1.84-4.44,3.39-8.51,1.72-4.52,2.57-7.03,2.57-7.53,0-.32-.11-.59-.32-.8-.21-.21-.46-.32-.75-.32-.04,0-.12.01-.24.03h-.27c-.09,0-.17-.04-.24-.12,0-.1.1-.17.31-.21.21-.04.4-.07.58-.09l3.06-.09c.41,0,.84.51,1.29,1.54.23.64.47,1.3.72,1.96.86,2.57,1.72,5.14,2.6,7.72,1.34,3.78,2.61,6.66,3.81,8.65.21.38.53.68.94.9.36.2.54.32.56.36l-3.03.18c-1.05,0-1.97-.86-2.73-2.59ZM92.74,24.34s-.61-1.85-1.82-5.42c-.14-.46-.4-1.03-.78-1.72l-2.76,7.38h5.36v-.24ZM113.45,14.07v.27c.34-.06.62.18.83.72.16.42.24.84.24,1.27,0,1.35-.78,4.18-2.33,8.5-1,2.79-1.83,4.94-2.49,6.45-1.55-3.74-2.81-6.86-3.78-9.37-.84-2.27-1.46-3.98-1.85-5.12-.52-1.41-.79-2.12-.8-2.14-.25-.12-.79-.18-1.63-.18.04,0-.31.02-1.05.05s-1.28.04-1.63.04c-.29,0-.47.09-.56.27.2.06.39.12.58.17.19.05.34.16.47.32,1.11,1.53,2.29,3.75,3.54,6.66.66,1.53,1.59,3.9,2.79,7.11,1.09,2.95,1.71,4.58,1.85,4.88.57,1.21.92,1.84,1.04,1.9,2.77-7.31,5.78-14.58,9.03-21.79h-4.23ZM121.17,17.95c.04.32.05.57.05.75.02-.02.03,2.23.03,6.75l-.11,4.91-.48,5.09h4.18c.07-.12.11-.2.11-.24-.48-.2-.72-.31-.72-.33-.16-.22-.26-.53-.29-.93-.04-.32-.07-.64-.11-.96l.05-9.64c.07-3.6.25-6.74.54-9.43h-.21c.07,0-.13.05-.6.14s-1.04.14-1.7.14c.04,0-.18,0-.64,0-.41.02-.71.03-.88.03.48.5.73,1.09.75,1.76.02.67.04,1.33.05,1.97ZM132.59,34.35c.7.26,1.39.51,2.09.75l-3.56.45c-.27,0-.73-.04-1.39-.11-.66-.07-1.14-.11-1.42-.11.95-.8,1.77-2,2.48-3.6.71-1.6,1.84-4.44,3.39-8.51,1.72-4.52,2.57-7.03,2.57-7.53,0-.32-.11-.59-.32-.8-.21-.21-.46-.32-.75-.32-.04,0-.12.01-.24.03h-.27c-.09,0-.17-.04-.24-.12,0-.1.1-.17.31-.21.21-.04.4-.07.58-.09l3.06-.09c.41,0,.84.51,1.29,1.54.23.64.47,1.3.72,1.96.86,2.57,1.72,5.14,2.6,7.72,1.34,3.78,2.61,6.66,3.81,8.65.21.38.53.68.94.9.36.2.54.32.56.36l-3.03.18c-1.05,0-1.97-.86-2.73-2.59-.45-1.17-.89-2.32-1.34-3.47-.34-.98-.93-2.37-1.77-4.16h-5.41c-.27,0-.62.52-1.05,1.57-.21.62-.43,1.24-.64,1.84-.71,1.89-1.07,3.25-1.07,4.1s.29,1.4.86,1.66ZM134.41,24.58h5.36v-.24s-.61-1.85-1.82-5.42c-.14-.46-.4-1.03-.78-1.72l-2.76,7.38ZM169.49,14.34c-.38,0-1.04.18-1.98.54-.84.32-1.32.53-1.45.63.21,0,.77-.03,1.66-.09.21,0,.33.06.35.18.12,1.41.2,2.46.21,3.16.11,2.15.21,4.36.32,6.63l.13,4.64c0,.16-.03.28-.08.36-3.45-3.82-5.7-6.34-6.75-7.56-2.75-3.27-4.83-6.13-6.24-8.56-2.14.16-3.77.31-4.88.45,1.27.62,2.11,1.43,2.52,2.41.27.64.4,1.64.4,2.98l-.19,3.68v1.69c0,3.76-.19,6.39-.56,7.9-.11.4-.34.74-.7,1.02-.54.42-.82.65-.86.69.46.12,1.14.18,2.04.18.66,0,1.48-.11,2.47-.33,1.34-.32,2.02-.48,2.04-.48-.64-.12-1.12-.18-1.42-.18-.46,0-.92-.14-1.37-.42-.14-.1-.25-.25-.32-.45-.04-.1-.08-.33-.13-.69-.18-1.87-.27-3.2-.27-4.01-.11-3.88-.1-7.11.03-9.7,1.13,1.27,3.06,3.49,5.82,6.66,1.79,2.07,4.28,4.87,7.48,8.41.86.94,1.31,1.42,1.37,1.42.07,0,.12-.02.16-.06.11-1.85.21-3.7.32-5.55.2-3.6.29-5.65.29-6.15v-1.42c0-.26.19-2.56.56-6.9.21-.14.65-.29,1.31-.45.57-.16.94-.31,1.1-.45l-3.38-.18Z" style="fill: #fff; stroke-width: 0px;"></path>
                                                </svg>
                                            </div>
                                            <div class="description"><?= $escape($lobbyDescription); ?></div>
                                            <a class="textButtonV2 buttonSecondary rectangle withText green" href="<?= $escape($publicRegisterUrl); ?>"><div><?= $escape($createLobbyAccount); ?></div></a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <script>
                                        (function () {
                                            const loginForm = document.querySelector('#loginScene form');
                                            const fields = [
                                                loginForm?.querySelector('input[name="name"]'),
                                                loginForm?.querySelector('input[name="password"]')
                                            ].map(function (input) {
                                                return { input: input, field: input ? input.closest('label.input') : null };
                                            }).filter(function (entry) {
                                                return entry.input && entry.field;
                                            });

                                            if (!loginForm || fields.length !== 2) {
                                                return;
                                            }

                                            const clearValidation = function (entry) {
                                                entry.field.classList.remove('invalid');
                                                entry.input.removeAttribute('aria-invalid');
                                                entry.field.querySelector('.validation[data-rule="notEmpty"]')?.classList.remove('show');
                                            };
                                            const showValidation = function (entry) {
                                                entry.field.classList.add('invalid');
                                                entry.input.setAttribute('aria-invalid', 'true');
                                                entry.field.querySelector('.validation[data-rule="notEmpty"]')?.classList.add('show');
                                            };

                                            loginForm.addEventListener('submit', function (event) {
                                                let valid = true;
                                                fields.forEach(function (entry) {
                                                    if (entry.input.value.trim() === '') {
                                                        valid = false;
                                                        showValidation(entry);
                                                    } else {
                                                        clearValidation(entry);
                                                    }
                                                });
                                                if (!valid) {
                                                    event.preventDefault();
                                                }
                                            });
                                            fields.forEach(function (entry) {
                                                entry.input.addEventListener('input', function () {
                                                    clearValidation(entry);
                                                });
                                            });

                                            const visibility = loginForm.querySelector('svg.visibility');
                                            if (visibility) {
                                                visibility.addEventListener('click', function (event) {
                                                    event.preventDefault();
                                                    const passwordInput = loginForm.querySelector('input[name="password"]');
                                                    if (passwordInput) {
                                                        passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
                                                    }
                                                });
                                            }
                                        })();
                                    </script>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
