<?php

declare(strict_types=1);

function failGuestActivationRequestGuardContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$activation = file_get_contents($root . '/game/include/Controller/ActivateCtrl.php');
$wizard = file_get_contents($root . '/game/include/resources/Templates/activation/wizard.php');
$verification = file_get_contents($root . '/game/include/Controller/EmailVerificationCtrl.php');
$inProgress = file_get_contents($root . '/game/include/resources/Templates/verify/inProgress.php');
$login = file_get_contents($root . '/game/include/resources/Templates/system/login.php');

if (in_array(false, [$activation, $wizard, $verification, $inProgress, $login], true)) {
    failGuestActivationRequestGuardContract('Guest activation request-guard sources must be readable.');
}

foreach ([
    'use Core\\Session;',
    'if (!Session::validateChecker()) {',
    'return $this->completeRegistration($tribe, $sector);',
    "return ['redirect' => '/activate.php'];",
] as $contract) {
    if (!str_contains($activation, $contract)) {
        failGuestActivationRequestGuardContract('Activation writes must require one-time POST confirmation.');
    }
}

foreach ([
    'Session::getInstance()->login(',
    '$this->activationRow[\'name\']',
    '$this->activationRow[\'password\']',
] as $contract) {
    if (!str_contains($activation, $contract)) {
        failGuestActivationRequestGuardContract('Activation must establish the player session through the shared login path.');
    }
}
foreach (['IPTracker::addCurrentIP', "fixSessionPrefix('uid')", "fixSessionPrefix('pw')", 'session_regenerate_id(true)'] as $retiredActivationSessionPath) {
    if (str_contains($activation, $retiredActivationSessionPath)) {
        failGuestActivationRequestGuardContract('Activation must not retain its partial duplicate player-session setup.');
    }
}

if (!str_contains($wizard, '\\Core\\Session::getCheckerInput();')) {
    failGuestActivationRequestGuardContract('Activation forms must submit the current session checker.');
}

if (!str_contains($verification, "\$checker = \$_GET['c'] ?? null;")
    || !str_contains($verification, 'Session::matchesChecker($checker)')
    || !str_contains($verification, "isset(\$_GET['resendEmail']) && \\Game\\SmtpConfig::isConfigured() && Session::validateChecker()")
    || !str_contains($inProgress, "'/verify.php?resendEmail&amp;' . \\Core\\Session::getCheckerForUrl()")) {
    failGuestActivationRequestGuardContract('Verification-email resends must require and render the current session checker.');
}

foreach ([
    "\$publicLoginUrl = is_string(\$vars['publicLoginUrl'] ?? null) ? \$vars['publicLoginUrl'] : '/';",
    "? \$vars['publicRecoveryUrl']\n    : '/?forgotPassword=true';",
    "\$loginAction = \$vars['isAdmin'] ? '/admin.php' : \$publicLoginUrl;",
    'action="<?= $escape($publicRecoveryUrl); ?>"',
    'href="<?= $escape($publicRecoveryUrl); ?>"',
] as $contract) {
    if (!str_contains($login, $contract)) {
        failGuestActivationRequestGuardContract('Guest login query destinations must stay root-absolute.');
    }
}

// Runtime invariant: every public destination LoginCtrl emits is root-absolute
// (single leading slash) with a valid transient locale kept and an invalid one dropped.
require_once $root . '/game/include/Game/PublicHomepageLocale.php';
foreach ([[], ['homepageLocale' => 'en-EN'], ['homepageLocale' => 'bogus-locale'], ['homepageLocale' => ['x']]] as $query) {
    $_GET = $query;
    $valid = ($query['homepageLocale'] ?? null) === 'en-EN';
    foreach (['/', '/?forgotPassword=true', '/register'] as $path) {
        $url = \Game\PublicHomepageLocale::append($path);
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            failGuestActivationRequestGuardContract('Guest login query destinations must stay root-absolute.');
        }
        if ($valid !== str_contains($url, 'homepageLocale=en-EN')
            || (!$valid && str_contains($url, 'homepageLocale'))
            || substr_count($url, '?') !== substr_count($path, '?') + ($valid && !str_contains($path, '?') ? 1 : 0)) {
            failGuestActivationRequestGuardContract('Public locale must be kept when valid and dropped when invalid.');
        }
    }
}
$_GET = [];

echo "Guest activation request-guard contract passed.\n";
