<?php

/**
 * Second step of an administrator sign-in (code, enrolment or recovery codes).
 *
 * @var array{action: string, token: string, state: string, error: string, secret: string, codes: list<string>, qr: string} $vars
 */
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$state = $vars['state'];
$error = (string)$vars['error'];
?>
<div id="loginScene" class="scene contentV2">
    <div id="dialogOverlay" class="dialogOverlay dialogVisible enabled dark">
        <div class="dialogWrapper dialogV2" data-context="">
            <div class="dialog basic">
                <div class="dialogContainer">
                    <div class="dialogContents">
                        <form method="post" action="<?= $escape($vars['action']); ?>" accept-charset="UTF-8" autocomplete="off">
                            <div class="dialogDragBar"></div>
                            <div class="content" id="dialogContent">
                                <h1>Two-factor sign-in</h1>
                                <div class="formV2">
                                    <input type="hidden" name="twoFactorToken" value="<?= $escape($vars['token']); ?>">
                                    <?php if ($state === 'recovery'): ?>
                                        <p>Two-factor sign-in is on. Keep these recovery codes somewhere safe: each works once if you lose your authenticator, and they are not shown again.</p>
                                        <pre class="twoFactorRecoveryCodes"><?= $escape(implode("\n", $vars['codes'])); ?></pre>
                                        <input type="hidden" name="acknowledged" value="1">
                                        <div class="centeredText">
                                            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit"><div>I have saved them, continue</div></button>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($state === 'enrol'): ?>
                                            <p>This account must use an authenticator app. Scan the code, or type the secret into the app, then enter the 6-digit code it shows.</p>
                                            <div class="twoFactorQr"><?= $vars['qr']; ?></div>
                                            <p>Secret: <code><?= $escape(implode(' ', str_split($vars['secret'], 4))); ?></code></p>
                                        <?php else: ?>
                                            <p>Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
                                        <?php endif; ?>
                                        <label class="input fixed<?= $error !== '' ? ' invalid' : ''; ?>">
                                            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus spellcheck="false" maxlength="32">
                                            <div class="label">Code</div>
                                            <div class="validation<?= $error !== '' ? ' show' : ''; ?>" data-rule="notEmpty" role="alert"><?= $escape($error); ?></div>
                                        </label>
                                        <div class="centeredText">
                                            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit"><div>Verify</div></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
