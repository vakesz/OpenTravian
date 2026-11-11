<?php

declare(strict_types=1);

/**
 * Contract: registration is the `#registration` overlay on the public landing
 * page. /register only redirects there (locale and invite code kept in the
 * query), the terms checkbox is gone, and the social row exists only for
 * operator-configured providers.
 */

function failRegister(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failRegister("Could not read $path.");
    }

    return $contents;
};

$controller = $read('game/include/Controller/RegisterCtrl.php');
$flow = $read('game/include/Game/PublicRegistration.php');
$homepageCtrl = $read('game/include/Controller/HomepageCtrl.php');
$homepage = $read('game/include/resources/Templates/system/homepage.php');
$template = $read('game/include/resources/Templates/system/registrationDialog.php');
$main = $read('game/include/mainInclude.php');
$head = $read('game/include/resources/Templates/layout/head.php');
$referral = $read('game/include/Controller/ReferAFriendCtrl.php');
$preRegistration = $read('game/include/admin/include/Controllers/PreRegistrationByEmailCtrl.php');
$fixes = $read('assets/gpack/travian/mainPage/lang/overrides.css');
$adminCss = $read('assets/gpack/travian/mainPage/lang/admin.css');
$worldSql = $read('database/world.sql');
$activate = $read('game/include/Controller/ActivateCtrl.php');
$registerModel = $read('game/include/Model/RegisterModel.php');
$enUs = $read('game/include/resources/Translation/en-US.php');

// --- Route: /register only forwards to the overlay ------------------------------------------
if (!str_contains($main, "'register' => 'RegisterCtrl'")
    || !str_contains($main, "if (\$requestPath === '/anmelden.php')")
    || !str_contains($main, "WebService::redirect('/register');")
    || str_contains($main, "'anmelden.php' => 'RegisterCtrl'")) {
    failRegister('Registration must dispatch only through /register and canonicalize the retired alias.');
}
if (!str_contains($controller, 'PublicHomepageLocale::registrationUrl(')
    || !str_contains($controller, 'PublicRegistration::referralCode()')
    || str_contains($controller, 'PHPBatchView')
    || str_contains($controller, 'INSERT INTO')) {
    failRegister('RegisterCtrl must only redirect to the homepage registration overlay.');
}
if (!str_contains($referral, "'/register?uc='") || str_contains($referral, '#register')) {
    failRegister('Refer-a-friend invite links must keep opening /register, which forwards to the overlay.');
}
if (!str_contains($preRegistration, 'gameWorldUrl") . \'register\'') || str_contains($preRegistration, 'anmelden.php')) {
    failRegister('Pre-registration e-mails must use the canonical local registration path.');
}

// --- Standalone register shell and Login/Register chooser are gone --------------------------
foreach ([
    'game/include/resources/Templates/system/register.php',
    'assets/gpack/travian/mainPage/lang/registration.css',
] as $retired) {
    if (file_exists($root . '/' . $retired)) {
        failRegister("Retired standalone registration file still exists: $retired");
    }
}
if (str_contains($head, 'registration.css') || str_contains($head, 'registerPage')) {
    failRegister('head.php must not load the retired registration stylesheet.');
}
if (str_contains($homepage, 'homepageAuthDialog') || str_contains($fixes, 'homepageAuthDialog')) {
    failRegister('The Login/Register chooser dialog must not exist; the reference has none.');
}
foreach (['lobbyPanels', 'lobbyPage', 'LobbyWorld', 'registerPage'] as $retiredLobbyPath) {
    if (str_contains($controller . $flow . $template, $retiredLobbyPath)) {
        failRegister('Registration must not retain obsolete lobby chrome: ' . $retiredLobbyPath);
    }
}

// --- Server side: validation, prepared statements, no terms ---------------------------------
foreach ([
    'throttle_public_form(\'register\'',
    'Password::validate(',
    'Password::hash(',
    'hash_equals($password, $confirm)',
    'isNameBlackListed(',
    'FILTER_VALIDATE_EMAIL',
    "'INSERT INTO activation (name, password, email, newsletter, token, refUid, time) VALUES (?, ?, ?, ?, ?, ?, ?)'",
    "\$this->redirect = '/activate.php'",
    'bin2hex(random_bytes(32))',
    'preregistration_keys',
    'registrationOpen',
    'RegistrationWorld::summary()',
    'validateEmail()',
    "\$_POST['stage'] === 'email'",
    'showAccountStep',
] as $needle) {
    if (!str_contains($flow, $needle)) {
        failRegister("PublicRegistration is missing: $needle");
    }
}
if (preg_match('/->query\\s*\\(\\s*["\'][^"\']*\\$/', $flow) === 1 || str_contains($flow, 'real_escape_string')) {
    failRegister('PublicRegistration must not build SQL from request values.');
}
foreach ([$flow, $template, $controller, $homepageCtrl] as $source) {
    if (stripos($source, 'acceptTermsAndConditions') !== false
        || stripos($source, 'termsAccepted') !== false
        || str_contains($source, 'getTermsUrl')
        || str_contains($source, 'getGameRulesUrl')
        || str_contains($source, 'legalAcceptance')) {
        failRegister('The terms checkbox, sentence and server-side consent check must be removed.');
    }
}

// --- Newsletter opt-in is persisted ---------------------------------------------------------
foreach (['activation', 'users'] as $table) {
    $start = strpos($worldSql, 'CREATE TABLE ' . ($table === 'users' ? 'IF NOT EXISTS ' : '') . "`$table`");
    $end = $start === false ? false : strpos($worldSql, ') ENGINE', $start);
    if ($start === false || $end === false
        || !str_contains(substr($worldSql, $start, $end - $start), '`newsletter` TINYINT(1) UNSIGNED NOT NULL DEFAULT')) {
        failRegister("world.sql `$table` must persist the newsletter opt-in.");
    }
}
if (!str_contains($activate, "!empty(\$this->activationRow['newsletter'])")
    || !str_contains($registerModel, 'newsletter, access')) {
    failRegister('The newsletter opt-in must carry from the activation row into the created user.');
}

// --- Overlay markup -------------------------------------------------------------------------
foreach ([
    'id="homepageRegistrationDialog"',
    'role="dialog"',
    'data-step="email"',
    'data-step="account"',
    'name="newsletter"',
    'id="registerEmailContinue"',
    'class="formV2"',
    'name="uc"',
    "event.submitter?.value === 'email'",
    'button.disabled = !valid;',
    'emailStep.hidden = true',
    'accountStep.hidden = false',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failRegister("The registration overlay is missing: $needle");
    }
}
if (!str_contains($homepage, '$registrationDialog') || !str_contains($homepage, "window.location.hash !== '#registration'")
    || !str_contains($homepage, 'data-homepage-toggle="homepageRegistrationDialog"')) {
    failRegister('PLAY NOW and the #registration hash must open the overlay on the landing page.');
}
if (!str_contains($homepageCtrl, "'socialProviders' =>") || !str_contains($homepageCtrl, 'SocialProviderList::build(')
    || !str_contains($homepageCtrl, 'socialErrorMessage(') || str_contains($homepageCtrl, "\$_GET['socialError']")) {
    failRegister('HomepageCtrl must pass the socialProviders list and the social error slot.');
}

// --- Every player-facing string is a canonical key ------------------------------------------
foreach ([
    'Sign in with Apple',
    'Registration is unavailable', 'Registration is closed for %s right now.', 'Create your account',
    'Account name', 'Repeat the password', 'Registration key', 'Create account', 'Log in',
    'Next you choose your tribe (%s) and your starting position.',
] as $key) {
    if (!str_contains($enUs, "\$Definition['Login'][" . var_export($key, true) . '] = ' . var_export($key, true) . ';')) {
        failRegister("en-US is missing the canonical Login key: $key");
    }
}
foreach (['Create Legends lobby account', 'Registration is unavailable', 'Already have an account?', 'Log in now', 'Register with email address', 'or continue with'] as $literal) {
    if (str_contains(preg_replace("/T\\('Login', '[^']*'\\)/", '', $template) ?? $template, '>' . $literal . '<')) {
        failRegister("The overlay must not hardcode: $literal");
    }
}

// --- Geometry and stylesheet hygiene --------------------------------------------------------
foreach ([
    '#homepageRegistrationDialog .homepageDialogPanel',
    'width: min(450px, calc(100vw - 30px))',
    'button.buttonFramed.green',
    'padding-block-start: 100px',
    'font-variant: small-caps',
    'width: 38px',
    'height: 38px',
] as $needle) {
    if (!str_contains($fixes, $needle)) {
        failRegister("overrides.css no longer preserves the measured registration overlay geometry: $needle");
    }
}
if (preg_match('/#homepageRegistrationDialog \.homepageDialogPanel \{[^}]*(?:min-)?height:\s*\d+px/', $fixes)) {
    failRegister('The registration panel height must stay content-driven, not fixed.');
}
if (!is_file($root . '/assets/gpack/travian/mainPage/fonts/homepage/noto-serif-bold.ttf')
    || !str_contains($fixes, 'url("../fonts/homepage/noto-serif-bold.ttf")')) {
    failRegister('The measured registration heading font must be served from the local Gpack.');
}
if (preg_match('#https?://#', preg_replace('#url\\("data:[^)]*\\)#', '', $adminCss) ?? $adminCss) === 1) {
    failRegister('admin.css must not reference external URLs.');
}

// --- Runtime: redirect target and overlay rendering -----------------------------------------
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$locale = 'en-EN';
foreach ([
    [[], '/homepage#registration'],
    [['homepageLocale' => $locale], "/homepage?homepageLocale=$locale#registration"],
    [['homepageLocale' => 'bogus'], '/homepage#registration'],
] as [$query, $expected]) {
    $_GET = $query;
    $url = \Game\PublicHomepageLocale::registrationUrl(\Game\PublicHomepageLocale::fromRequest());
    if ($url !== $expected || $url[0] !== '/' || str_starts_with($url, '//')) {
        failRegister("registrationUrl() returned $url, expected $expected.");
    }
}
$_GET = [];
$url = \Game\PublicHomepageLocale::registrationUrl($locale, '1_5');
if ($url !== "/homepage?uc=1_5&homepageLocale=$locale#registration") {
    failRegister("The invite code and locale must live in the query, the overlay marker in the hash: $url");
}
$_GET = ['uc' => '1_5<script>'];
if (\Game\PublicRegistration::referralCode() !== '') {
    failRegister('A malformed invite code must be dropped.');
}
$_GET = ['uc' => '1_5'];
if (\Game\PublicRegistration::referralCode() !== '1_5') {
    failRegister('A well-formed invite code must be preserved.');
}
$_GET = [];

$copy = require dirname(__DIR__, 2) . '/game/include/resources/Translation/HomepagePublicCatalog.php';
$copy = $copy['catalogues']['en-US'];
$render = static function (array $providers, array $overrides = []) use ($copy): string {
    return \resources\View\PHPBatchView::render('system/registrationDialog', $overrides + [
        'world' => ['registrationOpen' => true, 'keyOnly' => false, 'title' => 'W', 'tribes' => ['Romans']],
        'input' => ['name' => '', 'email' => '', 'preKey' => '', 'newsletter' => false],
        'errors' => [],
        'formError' => '',
        'showAccountStep' => false,
        'passwordMax' => 20,
        'open' => false,
        'redirect' => null,
        'registerAction' => '/homepage?homepageLocale=en-EN#registration',
        'loginUrl' => '/?homepageLocale=en-EN',
        'closeLabel' => 'Close window',
        'copy' => $copy,
        'socialProviders' => $providers,
        'socialError' => '',
        'referral' => '',
    ]);
};

$germanCopy = (require dirname(__DIR__, 2) . '/game/include/resources/Translation/HomepagePublicCatalog.php')['catalogues']['de-DE'];
$german = $render([], ['copy' => $germanCopy, 'open' => true]);
foreach (['Create Legends Lobby Account', 'Register with email address', 'Login now', 'I want to receive the newsletter', 'Already have an account?', 'Enter your email address', 'Email Address'] as $referenceKey) {
    if (!str_contains($german, htmlspecialchars($germanCopy[$referenceKey], ENT_QUOTES, 'UTF-8'))) {
        failRegister("The overlay must render the public-locale value of: $referenceKey");
    }
}
$socialStep = $render([], ['social' => true, 'showAccountStep' => true, 'open' => true, 'input' => ['name' => '', 'email' => 'a"<b>@example.com', 'preKey' => '', 'newsletter' => false]]);
if (str_contains($socialStep, 'name="password"') || str_contains($socialStep, 'name="passwordConfirm"') || !str_contains($socialStep, 'id="register_social_email"')
    || !str_contains($socialStep, 'readonly') || str_contains($socialStep, 'a"<b>@example.com') || !str_contains($socialStep, 'a&quot;&lt;b&gt;@example.com')) {
    failRegister('The social name step must lock the (escaped) provider email and render no password fields.');
}
$socialRow = $render([['id' => 'google', 'url' => '/oauth/google/start?x=1'], ['id' => 'apple', 'url' => '/oauth/apple/start?x=1']]);
if (!str_contains($socialRow, 'img_common/homepage/social-google.svg') || !str_contains($socialRow, 'img_common/homepage/social-apple.svg')) {
    failRegister('Google and Apple buttons must use the self-hosted gpack artwork.');
}
$empty = $render([]);
if (str_contains($empty, 'socialBlock') || str_contains($empty, 'or continue with') || str_contains($empty, 'SignInButton')) {
    failRegister('With no configured providers the overlay must render no social row and no "or continue with".');
}
if (str_contains($empty, 'type="checkbox" value="1" name="acceptTermsAndConditions"')) {
    failRegister('The terms checkbox must not render.');
}
if (!str_contains($empty, 'href="/?homepageLocale=en-EN">Log in now</a>')) {
    failRegister('"Log in now" must go to the game-world login with the locale preserved.');
}

$social = $render([
    ['id' => 'google', 'url' => '/social/google'],
    ['id' => 'facebook', 'url' => '/social/facebook'],
    ['id' => 'apple', 'url' => '/social/apple'],
]);
$positions = [];
foreach (['google', 'facebook', 'apple'] as $id) {
    $needle = '<button class="' . $id . 'SignInButton" type="submit" form="socialStart_' . $id . '"';
    $positions[$id] = strpos($social, $needle);
    if ($positions[$id] === false) {
        failRegister("The $id provider must render as a form-associated submit button.");
    }
    if (!str_contains($social, '<form id="socialStart_' . $id . '" method="post" action="/social/' . $id . '"')) {
        failRegister("The $id provider must have a POST form (no GET link, no token in the URL).");
    }
}
if (!($positions['google'] < $positions['facebook'] && $positions['facebook'] < $positions['apple'])
    || !str_contains($social, 'or continue with')
    || preg_match('/googleSignInButton"[^>]*aria-label="Sign in with Google"/', $social) !== 1
    || preg_match('/facebookSignInButton"[^>]*aria-label="Continue with Facebook"/', $social) !== 1
    || preg_match('/appleSignInButton"[^>]*aria-label="Sign in with Apple"/', $social) !== 1) {
    failRegister('Social providers render in Google, Facebook, Apple order, each named by its official button text.');
}

$error = $render([], ['socialError' => 'Some message']);
if (!str_contains($error, 'data-social-error')) {
    failRegister('The social error slot must render a supplied message.');
}
if (str_contains($empty, 'data-social-error')) {
    failRegister('The social error slot must render nothing without a message.');
}

echo "Registration contract passed.\n";
