<?php

declare(strict_types=1);

/**
 * Public `#registration` overlay content: an e-mail step followed by the local
 * account-details step, rendered inside the public landing page.
 *
 * @var array<string, mixed> $vars
 */
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$world = $vars['world'];
$input = $vars['input'];
$errors = $vars['errors'];
$formError = (string)$vars['formError'];
$showAccountStep = (bool)$vars['showAccountStep'];
$open = (bool)$vars['open'];
$action = (string)$vars['registerAction'];
$loginUrl = (string)$vars['loginUrl'];
$closeLabel = (string)$vars['closeLabel'];
$copy = $vars['copy'];
$socialProviders = is_array($vars['socialProviders'] ?? null) ? $vars['socialProviders'] : [];
$socialError = (string)($vars['socialError'] ?? '');
$referral = (string)($vars['referral'] ?? '');
$social = (bool)($vars['social'] ?? false);
$emailError = (string)($errors['email'] ?? '');
$emailRuleEmpty = $copy['Please enter your email address'];
$emailRuleInvalid = $copy['Please enter a valid email address. E.g. mymail@provider.com'];
// The message the email field is described by while it is in error (kept in step by the script below).
$emailMessageId = match (true) {
    $emailError === '' => '',
    $emailError === $emailRuleEmpty => 'registrationEmailRuleEmpty',
    $emailError === $emailRuleInvalid => 'registrationEmailRuleInvalid',
    default => 'registrationEmailRuleServer',
};
$invalidIcon = '<svg viewBox="0 0 22 19" class="invalid" aria-hidden="true"><path d="M11 4l7.5 13h-15L11 4m0-4L0 19h22L11 0z"></path><path d="M12 14h-2v2h2zM12 8h-2v5h2z"></path></svg>';
$validIcon = '<svg class="valid" viewBox="0 0 20 20" aria-hidden="true"><path d="M14.59 5.58L8 12.17 4.41 8.59 3 10l5 5 8-8zM10 0a10 10 0 1010 10A10 10 0 0010 0zm0 18a8 8 0 118-8 8 8 0 01-8 8z"></path></svg>';
$checkboxIcon = '<svg class="checkbox" viewBox="0 0 18 18" aria-hidden="true"><path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path><rect class="indeterminate" width="10" height="2" x="4" y="8"></rect><path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path></svg>';
$socialAsset = static fn (string $file): string => '<img src="' . $escape(get_gpack_cdn_mainPage_url() . gpack_image_path('homepage/' . $file, 'LTR')) . '" width="38" height="38" alt="">';
// Accessible names are each provider's official button text (visible UI is
// icon-only): Google Identity "Sign in with Google" branding guidelines,
// Meta's standard "Continue with Facebook" login button, and Apple's own
// "Sign in with Apple" (appleid.auth.js). See README, Social sign-in.
$socialLabels = [
    'google' => T('Login', 'Sign in with Google'),
    'facebook' => T('Login', 'Continue with Facebook'),
    'apple' => T('Login', 'Sign in with Apple'),
];
$socialIcons = [
    'google' => $socialAsset('social-google.svg'),
    'apple' => $socialAsset('social-apple.svg'),
    'facebook' => '<svg viewBox="0 0 2048 2048" aria-hidden="true"><path d="M 1014.5,-0.5 C 1032.5,-0.5 1050.5,-0.5 1068.5,-0.5C 1316.3,8.56358 1534.3,92.7302 1722.5,252C 1905.16,412.288 2020,612.455 2067,852.5C 2075.48,899.978 2080.98,947.644 2083.5,995.5C 2083.5,1026.5 2083.5,1057.5 2083.5,1088.5C 2066.21,1376.48 1952.54,1618.31 1742.5,1814C 1578.82,1960.81 1387.15,2048.31 1167.5,2076.5C 1167.5,1837.5 1167.5,1598.5 1167.5,1359.5C 1261.5,1359.5 1355.5,1359.5 1449.5,1359.5C 1468.83,1253.83 1488.17,1148.17 1507.5,1042.5C 1394.17,1042.5 1280.83,1042.5 1167.5,1042.5C 1167.33,996.832 1167.5,951.165 1168,905.5C 1168.7,871.955 1174.37,839.289 1185,807.5C 1202.17,762.333 1233.33,731.167 1278.5,714C 1307.03,704.245 1336.36,698.578 1366.5,697C 1419.21,695.101 1471.88,695.934 1524.5,699.5C 1524.5,603.5 1524.5,507.5 1524.5,411.5C 1425.52,390.426 1325.52,382.926 1224.5,389C 1159.56,393.053 1096.89,407.053 1036.5,431C 932.366,475.804 862.199,551.97 826,659.5C 803.192,730.685 791.525,803.685 791,878.5C 790.5,933.166 790.333,987.832 790.5,1042.5C 718.833,1042.5 647.167,1042.5 575.5,1042.5C 575.5,1148.17 575.5,1253.83 575.5,1359.5C 647.167,1359.5 718.833,1359.5 790.5,1359.5C 790.833,1590.83 790.5,1822.17 789.5,2053.5C 543.691,1989.11 345.858,1855.11 196,1651.5C 71.9976,1477.33 6.49762,1283 -0.5,1068.5C -0.5,1050.5 -0.5,1032.5 -0.5,1014.5C 11.3901,728.626 119.057,486.126 322.5,287C 516.795,105.745 747.461,9.912 1014.5,-0.5 Z"></path></svg>',
];
$field = static function (string $id, string $label, string $type, string $value, string $autocomplete, array $errors, array $extra = []) use ($escape, $invalidIcon): string {
    $error = (string)($errors[$id] ?? '');
    $attributes = '';
    foreach ($extra as $name => $attributeValue) {
        $attributes .= ' ' . $name . '="' . $escape($attributeValue) . '"';
    }

    return '<label class="input fixed' . ($error !== '' ? ' invalid' : '') . '">'
        . '<input id="register_' . $id . '" type="' . $type . '" name="' . $id . '" value="' . $escape($value) . '" autocomplete="' . $autocomplete . '" required' . $attributes . '>'
        . '<div class="label">' . $escape($label) . '</div>'
        . $invalidIcon
        . '<div class="validation" role="alert">' . $escape($error) . '</div></label>';
};
?>
<div class="homepageDialog homepageRegistrationDialog" id="homepageRegistrationDialog" role="dialog" aria-modal="true" aria-labelledby="homepageRegistrationHeading"<?= $open ? '' : ' hidden' ?>>
    <div class="homepageDialogPanel" id="registrationFlowWrapper"<?= $social ? ' data-social-cancel="/oauth/cancel" data-social-cancel-field="' . $escape(\Core\Session::getCheckerName()) . '" data-social-cancel-token="' . $escape(\Core\Session::getInstance()->getChecker()) . '"' : '' ?>>
        <button class="homepageDialogClose" type="button" data-homepage-close aria-label="<?= $escape($closeLabel); ?>">×</button>
        <?php if (!$world['registrationOpen']): ?>
            <div class="registrationStep active">
                <h2 id="homepageRegistrationHeading"><?= $escape(T('Login', 'Registration is unavailable')); ?></h2>
                <p class="registerNote"><?= $escape(sprintf(T('Login', 'Registration is closed for %s right now.'), (string)$world['title'])); ?></p>
            </div>
        <?php else: ?>
            <form method="post" action="<?= $escape($action); ?>" accept-charset="UTF-8" class="registerForm" novalidate>
                <input type="hidden" name="uc" value="<?= $escape($referral); ?>">
                <div class="registrationStep<?= $showAccountStep ? '' : ' active' ?>" data-step="email"<?= $showAccountStep ? ' hidden' : '' ?>>
                    <h2 id="homepageRegistrationHeading"><?= $escape($copy['Create Legends Lobby Account']); ?></h2>
                    <?php if ($formError !== ''): ?><div class="toast" role="alert"><?= $escape($formError); ?></div><?php endif; ?>
                    <?php if ($socialError !== ''): ?><div class="toast" role="alert" data-social-error><?= $escape($socialError); ?></div><?php endif; ?>
                    <label class="text<?= $emailError !== '' ? ' invalid' : '' ?>" id="registrationEmailLabel">
                        <input id="registration_email" placeholder="<?= $escape($copy['Enter your email address']); ?>" type="text" name="email" value="<?= $escape($input['email']); ?>" autocomplete="email"<?= $emailMessageId !== '' ? ' aria-invalid="true" aria-describedby="' . $emailMessageId . '"' : '' ?><?= $showAccountStep ? ' disabled' : '' ?> required>
                        <div class="label"><?= $escape($copy['Email Address']); ?>:</div>
                        <?= $validIcon ?><?= $invalidIcon ?>
                        <div class="validation<?= $emailError === $emailRuleEmpty ? ' show' : '' ?>" id="registrationEmailRuleEmpty" data-rule="notEmpty"><?= $escape($emailRuleEmpty); ?></div>
                        <div class="validation<?= $emailError === $emailRuleInvalid ? ' show' : '' ?>" id="registrationEmailRuleInvalid" data-rule="isEmail"><?= $escape($emailRuleInvalid); ?></div>
                        <?php if ($emailError !== '' && $emailError !== $emailRuleEmpty && $emailError !== $emailRuleInvalid): ?>
                            <div class="validation show" id="registrationEmailRuleServer" data-rule="server" role="alert"><?= $escape($emailError); ?></div>
                        <?php endif; ?>
                    </label>
                    <label class="checkbox">
                        <input id="registration_newsletter" type="checkbox" value="1" name="newsletter"<?= $input['newsletter'] ? ' checked' : '' ?><?= $showAccountStep ? ' disabled' : '' ?>><?= $checkboxIcon ?>
                        <div class="label"><?= $escape($copy['I want to receive the newsletter']); ?></div>
                    </label>
                    <div class="actions">
                        <button id="registerEmailContinue" class="green buttonFramed withText withLoadingIndicator" type="submit" name="stage" value="email" disabled><div><?= $escape($copy['Register with email address']); ?></div></button>
                        <?php if ($socialProviders !== []): ?>
                            <div id="socialBlock">
                                <div id="socialSignIn"><span class="label"><?= $escape($copy['or continue with']); ?></span><div class="socialButtons">
                                    <?php foreach ($socialProviders as $provider): ?>
                                        <?php $socialLabel = $socialLabels[$provider['id']] ?? ''; ?>
                                        <button class="<?= $escape($provider['id']); ?>SignInButton" type="submit" form="socialStart_<?= $escape($provider['id']); ?>" formnovalidate<?= $socialLabel !== '' ? ' aria-label="' . $escape($socialLabel) . '"' : '' ?>><?= $socialIcons[$provider['id']] ?? '' ?></button>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                        <?php endif; ?>
                        <div><?= $escape($copy['Already have an account?']); ?>&nbsp;<a href="<?= $escape($loginUrl); ?>"><?= $escape($copy['Login now']); ?></a></div>
                    </div>
                </div>
                <div class="registrationStep accountStep<?= $showAccountStep ? ' active' : '' ?>" data-step="account"<?= $showAccountStep ? '' : ' hidden' ?> aria-live="polite">
                    <h2><?= $escape(T('Login', 'Create your account')); ?></h2>
                    <?php if ($formError !== ''): ?><div class="toast" role="alert"><?= $escape($formError); ?></div><?php endif; ?>
                    <div class="formV2">
                        <?php if ($social): ?>
                            <label class="input fixed lockedEmail"><input id="register_social_email" type="text" value="<?= $escape($input['email']); ?>" readonly aria-readonly="true" autocomplete="email"><div class="label"><?= $escape($copy['Email Address']); ?></div></label>
                        <?php endif; ?>
                        <?= $field('name', T('Login', 'Account name'), 'text', $input['name'], 'username', $errors, ['maxlength' => 15, 'minlength' => 3, 'pattern' => '[A-Za-z0-9_.\\-]{3,15}', 'spellcheck' => 'false', 'disabled' => $showAccountStep ? '' : 'disabled']); ?>
                        <?php if (!$social): ?>
                            <input id="register_email" type="hidden" name="email" value="<?= $escape($input['email']); ?>"<?= $showAccountStep ? '' : ' disabled' ?>>
                            <input id="register_newsletter" type="hidden" name="newsletter" value="<?= $input['newsletter'] ? '1' : '0' ?>"<?= $showAccountStep ? '' : ' disabled' ?>>
                            <?= $field('password', T('Login', 'pass'), 'password', '', 'new-password', $errors, ['maxlength' => $vars['passwordMax'], 'minlength' => 8, 'disabled' => $showAccountStep ? '' : 'disabled']); ?>
                            <?= $field('passwordConfirm', T('Login', 'Repeat the password'), 'password', '', 'new-password', $errors, ['maxlength' => $vars['passwordMax'], 'disabled' => $showAccountStep ? '' : 'disabled']); ?>
                        <?php endif; ?>
                        <?php if ($world['keyOnly']): ?>
                            <?= $field('preKey', T('Login', 'Registration key'), 'text', $input['preKey'], 'off', $errors, ['maxlength' => 32, 'spellcheck' => 'false', 'disabled' => $showAccountStep ? '' : 'disabled']); ?>
                        <?php endif; ?>
                        <p class="registerNote"><?= $escape(sprintf(T('Login', 'Next you choose your tribe (%s) and your starting position.'), implode(', ', $world['tribes']))); ?></p>
                        <div class="centeredText"><button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="stage" value="account"<?= $showAccountStep ? '' : ' disabled' ?>><div><?= $escape(T('Login', 'Create account')); ?></div></button><div><?= $escape($copy['Already have an account?']); ?>&nbsp;<a href="<?= $escape($loginUrl); ?>"><?= $escape(T('Login', 'Log in')); ?></a></div></div>
                    </div>
                </div>
            </form>
            <?php foreach ($socialProviders as $provider): ?>
                <form id="socialStart_<?= $escape($provider['id']); ?>" method="post" action="<?= $escape($provider['url']); ?>" hidden><?= \Core\Session::getCheckerInput(); ?></form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($world['registrationOpen']): ?>
<script>
(() => {
    const form = document.querySelector('.registerForm');
    const emailStep = form?.querySelector('[data-step="email"]');
    const accountStep = form?.querySelector('[data-step="account"]');
    const email = document.querySelector('#registration_email');
    const newsletter = document.querySelector('#registration_newsletter');
    const button = document.querySelector('#registerEmailContinue');
    const emailLabel = document.querySelector('#registrationEmailLabel');
    // Closing the overlay (X, Esc or backdrop) abandons a pending social
    // sign-up; reload so reopening shows the email step.
    const panel = document.querySelector('#registrationFlowWrapper');
    const dialog = document.querySelector('#homepageRegistrationDialog');
    if (panel?.dataset.socialCancel && dialog) {
        let cancelled = false;
        new MutationObserver(() => {
            if (!dialog.hidden || cancelled) return;
            cancelled = true;
            const body = new FormData();
            body.append(panel.dataset.socialCancelField, panel.dataset.socialCancelToken);
            fetch(panel.dataset.socialCancel, { method: 'POST', body, credentials: 'same-origin', keepalive: true })
                .finally(() => window.location.reload());
        }).observe(dialog, { attributes: true, attributeFilter: ['hidden'] });
    }
    if (!form || !emailStep || !accountStep || !email || !button) return;
    const validEmail = value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    const update = () => {
        const valid = validEmail(email.value.trim());
        emailLabel.classList.toggle('valid', valid);
        const invalid = email.value !== '' && !valid;
        emailLabel.classList.toggle('invalid', invalid);
        setEmailError(invalid ? 'registrationEmailRuleInvalid' : '');
        button.disabled = !valid;
    };
    // Programmatic twin of the visible error: the field is invalid and described by the shown message.
    const setEmailError = messageId => {
        if (messageId !== '') {
            email.setAttribute('aria-invalid', 'true');
            email.setAttribute('aria-describedby', messageId);
        } else {
            email.removeAttribute('aria-invalid');
            email.removeAttribute('aria-describedby');
        }
    };
    const continueToAccount = () => {
        if (!validEmail(email.value.trim())) return false;
        document.querySelector('#register_email').value = email.value.trim();
        document.querySelector('#register_newsletter').value = newsletter?.checked ? '1' : '0';
        emailStep.querySelectorAll('input, button').forEach(control => { control.disabled = true; });
        accountStep.querySelectorAll('input, button').forEach(control => { control.disabled = false; });
        emailStep.hidden = true;
        accountStep.hidden = false;
        accountStep.classList.add('active');
        document.querySelector('#register_name')?.focus();
        return true;
    };
    email.addEventListener('input', update);
    form.addEventListener('submit', event => {
        if (event.submitter?.value === 'email') {
            event.preventDefault();
            if (!continueToAccount()) {
                emailLabel.classList.add('invalid');
                setEmailError(email.value === '' ? 'registrationEmailRuleEmpty' : 'registrationEmailRuleInvalid');
            }
        }
    });
    if (!accountStep.hidden) {
        accountStep.querySelectorAll('input, button').forEach(control => { control.disabled = false; });
    } else {
        update();
    }
})();
</script>
<?php endif; ?>
