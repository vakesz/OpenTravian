<?php

declare(strict_types=1);

function failLoginV2Contract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/LoginCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/system/login.php');
$sidebar = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxMenu.php');
$guestMobileNav = file_get_contents($root . '/game/include/resources/Templates/layout/guestMobileNav.php');
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
$styles = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
$general = file_get_contents($root . '/game/include/functions.general.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$adminDispatcher = file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
$worldSchema = file_get_contents($root . '/database/world.sql');
$setup = file_get_contents($root . '/game/deployment/public/setup.php');
$updater = file_get_contents($root . '/game/include/update.php');

if (in_array(false, [$controller, $template, $sidebar, $guestMobileNav, $head, $styles, $general, $translation, $adminDispatcher, $worldSchema, $setup, $updater], true)) {
    failLoginV2Contract('Login V2 sources must be readable.');
}

foreach ([
    'addInformation(', 'maintenanceAction(', 'loginInfoTitle', 'loginInfoHTML',
    'del_cookie',
] as $retiredLoginControllerContract) {
    if (str_contains($controller, $retiredLoginControllerContract)) {
        failLoginV2Contract('The public login controller must not retain a retired legacy branch: ' . $retiredLoginControllerContract);
    }
}
foreach ([$adminDispatcher, $worldSchema, $setup] as $loginInfoSource) {
    if (str_contains($loginInfoSource, 'loginInfoTitle') || str_contains($loginInfoSource, 'loginInfoHTML')) {
        failLoginV2Contract('The retired login-info data path must not remain in the public/admin schema or installer.');
    }
}
if (!str_contains($updater, "DROP COLUMN IF EXISTS `loginInfoTitle`, DROP COLUMN IF EXISTS `loginInfoHTML`")) {
    failLoginV2Contract('Existing worlds must discard the retired login-info columns in the schema migration.');
}
foreach ([
    '/game/include/admin/include/Controllers/LoginInfoCtrl.php',
    '/game/include/admin/include/templates/loginInfo.php',
] as $retiredLoginInfoPath) {
    if (is_file($root . $retiredLoginInfoPath)) {
        failLoginV2Contract('The retired login-info admin file remains: ' . $retiredLoginInfoPath);
    }
}

foreach ([
    'containerCssClass\'] = \'loginSceneContainer\'',
    'contentCssClass\'] = \'loginSceneContainer\'',
    'showGuestNavBar\'] = true',
] as $requiredControllerContract) {
    if (!str_contains($controller, $requiredControllerContract)) {
        failLoginV2Contract('Login controller must bypass the obsolete generic content-page wrapper.');
    }
}

foreach ([
    "'perspectiveBuildings login lobbyPage'",
    'lobbyPanels()',
    "PHPBatchView::render('system/lobbyPanels'",
] as $retiredLobbyContract) {
    if (str_contains($controller, $retiredLobbyContract)) {
        failLoginV2Contract('The logged-out homepage must not retain local lobby chrome: ' . $retiredLobbyContract);
    }
}

foreach ([
    "\$requestPath === '/login.php' && \$_GET === [] && !WebService::isPost()",
    "WebService::redirect('/');",
    "isset(\$_GET['handshake'])",
    'WebService::redirect("/dorf1.php")',
] as $canonicalLoginContract) {
    if (!str_contains($controller, $canonicalLoginContract)) {
        failLoginV2Contract('The legacy bare login endpoint must redirect to the lobby while retaining tokenized SSO hand-off.');
    }
}
if (!str_contains($template, '$publicRegisterUrl')
    || !str_contains($template, 'href="<?= $escape($publicRegisterUrl); ?>"')
    || str_contains($template, 'anmelden.php')) {
    failLoginV2Contract('The lobby account banner must link to the local registration page while retaining a public locale.');
}

foreach ([
    'id="loginScene" class="scene contentV2<?= $showRecovery ? \' loginRecoveryScene\' : \'\'; ?>"',
    'id="dialogOverlay" class="dialogOverlay dialogVisible enabled dark"',
    'class="dialogWrapper dialogV2"',
    'class="dialogDragBar"',
    'class="formV2"',
    '<form method="get" action="<?= $escape($loginAction); ?>" accept-charset="UTF-8">',
    'name="name"',
    'name="password"',
    'id="recaptcha"',
    'class="visibility" aria-hidden="true"',
    '$loginAction = $vars[\'isAdmin\'] ? \'/admin.php\' : $publicLoginUrl;',
    '$publicRecoveryUrl',
    'href="<?= $escape($publicRecoveryUrl); ?>"',
    'class="lobbyAccountInfoBanner"',
    'withLoadingIndicator green',
    'data-rule="notEmpty"',
    'xmlns:xlink="http://www.w3.org/1999/xlink"',
    'xlink:href="<?= $gpackUrl; ?>logo.png"',
    'style="fill: #fff; stroke-width: 0px;"',
    "get_gpack_cdn_mainPage_url()",
] as $requiredTemplateContract) {
    if (!str_contains($template, $requiredTemplateContract)) {
        failLoginV2Contract('Login V2 template is missing a required public-login contract: ' . $requiredTemplateContract);
    }
}

foreach ([
    '$hasReferenceLoginCopy',
    "['en-US', 'ar-AE', 'el-GR']",
    "loginToWorld",
    "input_emailAccount_label",
    "input_emailAccount_placeholder",
    "input_emailAccount_error_empty",
    "input_password_label",
    "input_password_placeholder",
    "input_password_error_empty",
    "forgotYourPassword",
    '$loginSubmit',
    "lobbyAccountDescription",
    "createLobbyAccount",
] as $referenceLoginCopyContract) {
    if (!str_contains($template, $referenceLoginCopyContract)) {
        failLoginV2Contract('Login V2 must use this login copy: ' . $referenceLoginCopyContract);
    }
}

$referenceLoginCopy = [
    'en-US' => [
        'login' => 'Login',
        'loginToWorld' => 'Log in to [WORLD]',
        'input_emailAccount_label' => 'Enter your email address or account name',
        'input_emailAccount_placeholder' => 'Email address / account name',
        'input_emailAccount_error_empty' => 'Please enter your email address or account name',
        'input_password_label' => 'Enter your password',
        'input_password_placeholder' => 'Password',
        'input_password_error_empty' => 'Please enter your password',
        'forgotYourPassword' => 'Forgot your password?',
        'recover' => 'Recover',
        'lobbyAccountDescription' => 'Access all your game worlds with a single login.',
        'createLobbyAccount' => 'Create lobby account',
    ],
    'ar-AE' => [
        'login' => 'تسجيل الدخول',
        'loginToWorld' => 'سجّل دخول إلى [WORLD]',
        'input_emailAccount_label' => 'أدخل عنوان بريدك الإلكتروني أو اسم حسابك',
        'input_emailAccount_placeholder' => 'عنوان البريد الإلكتروني / اسم الحساب',
        'input_emailAccount_error_empty' => 'يرجى إدخال عنوان بريدك الإلكتروني أو اسم حسابك',
        'input_password_label' => 'أدخل كلمة المرور خاصتك',
        'input_password_placeholder' => 'كلمة المرور',
        'input_password_error_empty' => 'يرجى إدخال كلمة المرور خاصتك',
        'forgotYourPassword' => 'نسيت كلمة المرور خاصتك؟',
        'recover' => 'إعادة',
        'lobbyAccountDescription' => 'قم بالوصول إلى جميع سيرفرات لعبتك بتسجيل دخول واحد.',
        'createLobbyAccount' => 'إنشاء حساب ردهة',
    ],
    'el-GR' => [
        'login' => 'Σύνδεση',
        'loginToWorld' => 'Σύνδεση στο [WORLD]',
        'input_emailAccount_label' => 'Εισαγάγετε τη διεύθυνση email ή το όνομα λογαριασμού σας',
        'input_emailAccount_placeholder' => 'Διεύθυνση email / όνομα λογαριασμού',
        'input_emailAccount_error_empty' => 'Εισαγάγετε τη διεύθυνση email ή το όνομα λογαριασμού σας',
        'input_password_label' => 'Εισαγάγετε κωδικό πρόσβασης',
        'input_password_placeholder' => 'Κωδικός',
        'input_password_error_empty' => 'Εισαγάγετε τον κωδικό πρόσβασής σας',
        'forgotYourPassword' => 'Ξεχάσατε τον κωδικό πρόσβασής σας;',
        'recover' => 'Ανάκτηση',
        'lobbyAccountDescription' => 'Αποκτήστε πρόσβαση σε όλους τους κόσμους παιχνιδιού με μία μόνο σύνδεση.',
        'createLobbyAccount' => 'Δημιουργία λογαριασμού λόμπι',
    ],
];
foreach ($referenceLoginCopy as $locale => $copy) {
    $localeTranslation = file_get_contents($root . '/game/include/resources/Translation/' . $locale . '.php');
    if ($localeTranslation === false) {
        failLoginV2Contract('Could not read locale bundle ' . $locale . '.');
    }
    foreach ($copy as $key => $value) {
        $expected = "\$Definition['Login']['{$key}'] = '{$value}';";
        if (!str_contains($localeTranslation, $expected)) {
            failLoginV2Contract("{$locale} must retain the current login copy for {$key}.");
        }
    }
}

// Exact values from the public homepage localisation/{locale}.json on
// 2026-11-06. does not serve fa-IR; the source-approved official
// en-US mapping keeps that player-facing recovery route complete.
$recoveryReferenceCopy = [
    'en-US' => [
        'Back to Login' => 'Back to login',
        'Password Recovery' => 'Password recovery',
        'Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.' => 'Enter the email address associated with your Legends lobby account. We will send you instructions on how to reset your password.',
        'Enter your email address' => 'Enter your email address',
        'Please enter your email address' => 'Please enter your email address',
        'Please enter a valid email address. E.g. mymail@provider.com' => 'Please enter a valid email address. E.g. mymail@provider.com',
        'Recover password' => 'Recover password',
        'If a Lobby Account exists for the provided email address, we have sent you instructions to reset your password to the email:' => 'If a lobby account exists for this email address, you should have received instructions to reset your password in your emails:',
        'Once you have recovered your password, you can log in again.' => 'Once you have recovered your password, you can log in again.',
        'Password recovery cooldown' => 'Password recovery cooldown',
    ],
    'ar-AE' => [
        'Back to Login' => 'العودة لتسجيل الدخول',
        'Password Recovery' => 'استعادة كلمة المرور',
        'Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.' => 'إدخال عنوان البريد الإلكتروني المرتبط بحساب المنصَّة الخاص بك. سنرسل لك تعليمات حول كيفية إعادة تعيين كلمة المرور الخاصة بك.',
        'Enter your email address' => 'أدخل عنوان بريدك الإلكتروني',
        'Please enter your email address' => 'يرجى إدخال عنوان بريدك الإلكتروني',
        'Please enter a valid email address. E.g. mymail@provider.com' => 'فضلاً أدخل عنوان بريد إلكتروني صالح. على سبيل المثال mymail@provider.com',
        'Recover password' => 'استعادة كلمة المرور',
        'If a Lobby Account exists for the provided email address, we have sent you instructions to reset your password to the email:' => 'في حالة وجود حساب منصَّة لعنوان البريد الإلكتروني هذا، يجب أن تكون قد تلقيت تعليمات لإعادة تعيين كلمة المرور الخاصة بك في رسائل البريد الإلكتروني الخاصة بك:',
        'Once you have recovered your password, you can log in again.' => 'بمجرد استعادة كلمة المرور الخاصة بك، يمكنك تسجيل الدخول مرة أخرى.',
        'Password recovery cooldown' => 'فترة تهدئة استعادة كلمة المرور',
    ],
    'el-GR' => [
        'Back to Login' => 'Επιστροφή στη σύνδεση',
        'Password Recovery' => 'Ανάκτηση κωδικού πρόσβασης',
        'Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.' => 'Εισαγάγετε τη διεύθυνση ηλεκτρονικού ταχυδρομείου που σχετίζεται με τον Legends λόμπι λογαριασμό. Θα σας στείλουμε οδηγίες σχετικά με τον τρόπο επαναφοράς του κωδικού πρόσβασής σας.',
        'Enter your email address' => 'Εισαγάγετε τη διεύθυνση ηλεκτρονικού ταχυδρομείου',
        'Please enter your email address' => 'Εισαγάγετε τη διεύθυνση ηλεκτρονικού ταχυδρομείου σας',
        'Please enter a valid email address. E.g. mymail@provider.com' => 'Παρακαλώ εισάγετε μία έγκυρη ηλεκτρονική διεύθυνση ταχυδρομείου. Π.χ. mymail@provider.com',
        'Recover password' => 'Ανάκτηση κωδικού πρόσβασης',
        'If a Lobby Account exists for the provided email address, we have sent you instructions to reset your password to the email:' => 'Αν υπάρχει λόμπι λογαριασμός για αυτήν τη διεύθυνση ηλεκτρονικού ταχυδρομείου, θα πρέπει να έχετε λάβει οδηγίες για να επαναφέρετε τον κωδικό πρόσβασής σας στα μηνύματα ηλεκτρονικού ταχυδρομείου σας:',
        'Once you have recovered your password, you can log in again.' => 'Μόλις ανακτήσετε τον κωδικό πρόσβασής σας, μπορείτε να συνδεθείτε ξανά.',
        'Password recovery cooldown' => 'Χρόνος αναμονής για την ανάκτηση κωδικού πρόσβασης',
    ],
    'fa-IR' => [
        'Back to Login' => 'Back to login',
        'Password Recovery' => 'Password recovery',
        'Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.' => 'Enter the email address associated with your Legends lobby account. We will send you instructions on how to reset your password.',
        'Enter your email address' => 'Enter your email address',
        'Please enter your email address' => 'Please enter your email address',
        'Please enter a valid email address. E.g. mymail@provider.com' => 'Please enter a valid email address. E.g. mymail@provider.com',
        'Recover password' => 'Recover password',
        'If a Lobby Account exists for the provided email address, we have sent you instructions to reset your password to the email:' => 'If a lobby account exists for this email address, you should have received instructions to reset your password in your emails:',
        'Once you have recovered your password, you can log in again.' => 'Once you have recovered your password, you can log in again.',
        'Password recovery cooldown' => 'Password recovery cooldown',
    ],
];
foreach ($recoveryReferenceCopy as $locale => $copy) {
    $localeTranslation = file_get_contents($root . '/game/include/resources/Translation/' . $locale . '.php');
    if ($localeTranslation === false) {
        failLoginV2Contract("Could not read recovery reference locale {$locale}.");
    }
    foreach ($copy as $key => $value) {
        $expected = "\$Definition['Login']['{$key}'] = '{$value}';";
        if (!str_contains($localeTranslation, $expected)) {
            failLoginV2Contract("{$locale} must retain official recovery copy for {$key}.");
        }
    }
}

foreach ([
    'data-sitekey=',
    'www.google.com/recaptcha',
    'www.recaptcha.net/recaptcha',
] as $externalRecaptchaContract) {
    if (str_contains($template, $externalRecaptchaContract)) {
        failLoginV2Contract('The public login must not ship a third-party CAPTCHA key or remote CAPTCHA script: ' . $externalRecaptchaContract);
    }
}

if (str_contains($template, '<noscript>') || str_contains($template, 'noJavaScript')) {
    failLoginV2Contract('The public login must not retain the non-reference no-JavaScript fallback toast.');
}

foreach (glob($root . '/game/include/resources/Translation/*.php') ?: [] as $translationPath) {
    $localeTranslation = file_get_contents($translationPath);
    if ($localeTranslation !== false && str_contains($localeTranslation, "['noJavascript']")) {
        failLoginV2Contract('The retired no-JavaScript login translation remains in ' . basename($translationPath) . '.');
    }
}

foreach ([
    'id="passwordRecovery" class="modal"',
    'action="<?= $escape($publicRecoveryUrl); ?>"',
    "T('Login', \$sourceKey)",
    "\$recoveryCopy('Back to Login')",
    "\$recoveryCopy('Password Recovery')",
    "\$recoveryCopy('Enter the email address associated with your Legends Lobby account. We will send you instructions on how to reset your password.')",
    "\$recoveryCopy('Recover password')",
    'data-rule="isEmail"',
    "form.addEventListener('submit'",
    "return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(value) ? '' : 'isEmail';",
    "validation.classList.add('show');",
] as $requiredRecoveryTemplate) {
    if (!str_contains($template, $requiredRecoveryTemplate)) {
        failLoginV2Contract('Password recovery must use the source-backed Lobby modal control: ' . $requiredRecoveryTemplate);
    }
}

foreach ([
    "vars['newPassErrorRule'] = '';",
    "T('Login', 'Password recovery cooldown')",
    "T('Login', 'Please enter your email address')",
    "T('Login', 'Please enter a valid email address. E.g. mymail@provider.com')",
    'filter_var($pwEmail, FILTER_VALIDATE_EMAIL) === false',
] as $requiredRecoveryValidation) {
    if (!str_contains($controller, $requiredRecoveryValidation)) {
        failLoginV2Contract('Password recovery must retain its source-key validation path: ' . $requiredRecoveryValidation);
    }
}

foreach ([
    '#loginScene.loginRecoveryScene #dialogOverlay',
    '#loginScene.loginRecoveryScene #passwordRecovery',
    'padding-top: 100px;',
    'width: min(450px, calc(100vw - 30px));',
    'inset: 28px 12px auto auto;',
    'inset: 63px auto auto 0;',
    'width: 100%;',
    'font-size: 13px;',
] as $requiredRecoveryStyle) {
    if (!str_contains($styles, $requiredRecoveryStyle)) {
        failLoginV2Contract('Password recovery must retain the captured Lobby overlay geometry: ' . $requiredRecoveryStyle);
    }
}

if (preg_match(
    '/#loginScene\\.loginRecoveryScene #passwordRecovery label\\.text \\.validation\\.show\\s*\\{[^}]*transform\\s*:/s',
    $styles
)) {
    failLoginV2Contract('Password recovery validation must remain directly below the email input, without an upward transform.');
}

foreach ([
    "class=\"validation<?= \$vars['userError'] !== '' ? ' show' : ''; ?>\" data-rule=\"notEmpty\"",
    "class=\"validation<?= \$vars['pwError'] !== '' ? ' show' : ''; ?>\" data-rule=\"notEmpty\"",
] as $requiredValidationContract) {
    if (!str_contains($template, $requiredValidationContract)) {
        failLoginV2Contract('Login V2 must reveal reference-shaped validation rows after server validation fails.');
    }
}

foreach ([
    "const loginForm = document.querySelector('#loginScene form');",
    "loginForm?.querySelector('input[name=\"name\"]')",
    "loginForm?.querySelector('input[name=\"password\"]')",
    "loginForm.addEventListener('submit'",
    "entry.field.querySelector('.validation[data-rule=\"notEmpty\"]')?.classList.add('show');",
    "entry.input.addEventListener('input'",
    "const visibility = loginForm.querySelector('svg.visibility');",
    "visibility.addEventListener('click'",
    "passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';",
] as $requiredClientValidation) {
    if (!str_contains($template, $requiredClientValidation)) {
        failLoginV2Contract('Blank login submission must remain in-place with the reference validation rows.');
    }
}

if (!str_contains($head, '$documentLanguage = (string) ($vars[\'documentLanguage\'] ?? get_locale());')
    || !str_contains($head, "lang=\"<?= htmlspecialchars(\$documentLanguage, ENT_QUOTES, 'UTF-8') ?>\"")) {
    failLoginV2Contract('The shared page shell must expose the active document language.');
}

foreach ([
    "\$Definition['Login']['userErrors']['empty'] = 'Please enter your email address or account name';",
    "\$Definition['Login']['pwErrors']['empty'] = 'Please enter your password';",
] as $requiredValidationCopy) {
    if (!str_contains($translation, $requiredValidationCopy)) {
        failLoginV2Contract('The public login must retain the captured blank-field validation copy.');
    }
}

foreach (['recoveryEmailEmpty', 'recoveryEmailInvalid', 'forgotPasswordSent'] as $retiredRecoveryTranslation) {
    if (str_contains($translation, $retiredRecoveryTranslation)) {
        failLoginV2Contract('The unsupported local password-recovery translation must be removed: ' . $retiredRecoveryTranslation);
    }
}

if (str_contains($template, '<text ') || str_contains($template, 'required\n                                            >')) {
    failLoginV2Contract('The public login must use the captured Lobby Account vector wordmark and reference custom validation.');
}

foreach ([
    "\$escape = static fn(\$value): string => htmlspecialchars((string)\$value, ENT_QUOTES, 'UTF-8');",
    'value="<?= $escape($vars[\'pw_email\']); ?>"',
] as $requiredHardeningContract) {
    if (!str_contains($template, $requiredHardeningContract)) {
        failLoginV2Contract('Login V2 template must escape and annotate its credential fields: ' . $requiredHardeningContract);
    }
}

foreach ([
    'name="login"',
    'name="w"',
    'name="s1"',
    'id="loginName"',
    'id="loginPassword"',
    'maxlength="20"',
    'autocomplete="username"',
    'autocomplete="current-password"',
    'document.login',
    'method="post" action="<?= $escape($loginAction); ?>"',
] as $retiredLoginFormContract) {
    if (str_contains($template, $retiredLoginFormContract)) {
        failLoginV2Contract('The public login must not retain the legacy credential-form control: ' . $retiredLoginFormContract);
    }
}

foreach ([
    "\$credentials = WebService::isPost() ? \$_POST : \$_GET;",
    "array_key_exists('name', \$_GET) || array_key_exists('password', \$_GET)",
    'if (WebService::isPost() || $isLoginGet)',
] as $requiredLoginTransportContract) {
    if (!str_contains($controller, $requiredLoginTransportContract)) {
        failLoginV2Contract('The public login must process the captured GET credential submission: ' . $requiredLoginTransportContract);
    }
}

if (str_contains($controller, 'FILTER_SANITIZE_STRING') || !str_contains($controller, 'is_string($forgotPassword)')) {
    failLoginV2Contract('Login controller must validate request input without the removed string sanitizer.');
}

if (str_contains($template, 'loginPasswordToggle') || str_contains($styles, 'loginPasswordToggle')) {
    failLoginV2Contract('The password-eye interaction must retain the reference non-focusable SVG shape.');
}

foreach (['outerLoginBox', 'loginCard','showPasswordForgotten', 'textButtonV1'] as $retiredTemplateContract) {
    if (str_contains($template, $retiredTemplateContract)) {
        failLoginV2Contract('Login V2 template must not retain retired login chrome: ' . $retiredTemplateContract);
    }
}

foreach (['.loginCard', '.loginForm', '.showPasswordForgotten'] as $retiredStyleContract) {
    if (str_contains($styles, $retiredStyleContract)) {
        failLoginV2Contract('Login V2 styles must not retain retired login selectors: ' . $retiredStyleContract);
    }
}

if (!str_contains($styles, 'body.login #sidebarBoxMenu a')) {
    failLoginV2Contract('The public login sidebar must retain the reference navigation treatment.');
}

$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
if ($layout === false
    || !str_contains($layout, '<a id="logo" href="<?= htmlspecialchars(')
    || str_contains($layout, 'class="light"')
    || str_contains($layout, 'fill="#231f20"')) {
    failLoginV2Contract('The shared Gpack must control the public guest wordmark colour without a local logo override.');
}

foreach ([
    'body.login #loginScene:not(.loginRecoveryScene) #dialogOverlay',
    'overflow: hidden;',
    'body.login #loginScene:not(.loginRecoveryScene) .dialogWrapper',
    'position: absolute;',
] as $normalLoginOverlayContract) {
    if (!str_contains($styles, $normalLoginOverlayContract)) {
        failLoginV2Contract('The normal public login must retain the reference overlay scroll and dialog anchoring: ' . $normalLoginOverlayContract);
    }
}

if (str_contains($styles, "body.login #center,\nbody.login #contentOuterContainer.loginSceneContainer")) {
    failLoginV2Contract('The public login must retain the reference 800px centre shell rather than resetting it with the dialog wrapper.');
}

if (!str_contains($general, "return '/homepage';")) {
    failLoginV2Contract('The public Homepage destination must stay on the self-hosted /homepage route.');
}

if (!str_contains($sidebar, 'class="sidebarBox expanded"')
    || !str_contains($sidebar, '<div class="header noHeader"></div>')
    || !str_contains($sidebar, '<div class="content">')
    || str_contains($sidebar, 'sidebarBoxBaseBox')
    || str_contains($sidebar, 'sidebarBoxInnerBox')) {
    failLoginV2Contract('The guest navigation must use the reference sidebar-box structure.');
}

if (!str_contains($sidebar, '$sidebarLoginUrl')
    || !str_contains($sidebar, "<?php if (getDiscordUrl() !== '') { ?>")
    || !str_contains($sidebar, "htmlspecialchars(getDiscordUrl(), ENT_QUOTES, 'UTF-8'); ?>\" title=\"<?= htmlspecialchars(T('Global', 'Footer.Discord'), ENT_QUOTES, 'UTF-8'); ?>\" target=\"_blank\" rel=\"noopener\"><?= T('Global', 'Footer.Discord'); ?>")
    || !str_contains($sidebar, '$sidebarRegisterUrl')
    || str_contains($sidebar, 'anmelden.php')
    || str_contains($sidebar, 'discord.gg')
    || str_contains($sidebar, 'href="/login.php"')) {
    failLoginV2Contract('The guest login and Discord controls must use their current reference destinations.');
}

if (!str_contains($sidebar, '$sidebarSupportUrl')
    || !str_contains($sidebar, 'getSupportTicketUrl()')) {
    failLoginV2Contract('The guest Support control must preserve the reference new-tab behavior.');
}

if (!str_contains($styles, 'top: 90px;')) {
    failLoginV2Contract('The narrow login dialog must retain the reference mobile top offset.');
}

if (!str_contains($styles, 'body.login #loginScene:not(.loginRecoveryScene) .dialogWrapper')) {
    failLoginV2Contract('The narrow login dialog must override the desktop inset at the same selector specificity.');
}

if (!str_contains($styles, 'body.login.mobileOptimized #sidebarBoxMenu')
    || !str_contains($styles, 'display: none;')) {
    failLoginV2Contract('The narrow guest layout must hide the desktop sidebar while the mobile drawer is active.');
}

foreach ([
    'class="mainpageItem"',
    'class="loginItem"',
    'class="registerItem"',
    '$guestRegisterUrl',
    'class="forum"',
    'class="support"',
    "title=\"<?= htmlspecialchars(T('Global', 'Footer.HomePage'), ENT_QUOTES, 'UTF-8') ?>\"",
    "title=\"<?= htmlspecialchars(T('Global', 'Login'), ENT_QUOTES, 'UTF-8') ?>\"",
    "title=\"<?= htmlspecialchars(T('Global', 'Register'), ENT_QUOTES, 'UTF-8') ?>\"",
    "title=\"<?= htmlspecialchars(T('Global', 'Footer.Discord'), ENT_QUOTES, 'UTF-8') ?>\"",
    "title=\"<?= htmlspecialchars(T('Global', 'Footer.Support'), ENT_QUOTES, 'UTF-8') ?>\"",
] as $guestMobileContract) {
    if (!str_contains($guestMobileNav, $guestMobileContract)) {
        failLoginV2Contract('Guest mobile navigation is missing a reference control: ' . $guestMobileContract);
    }
}

echo "Login V2 contract passed.\n";
