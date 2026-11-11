<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/EmailVerificationUrlCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/verify/verify-url.php');

if ($controller === false || $template === false) {
    fwrite(STDERR, "Could not read the email-verification sources.\n");
    exit(1);
}

foreach ([
    "bodyCssClass'] = 'perspectiveBuildings login emailVerification'",
    "contentCssClass'] = 'login'",
    "hash_equals((string)\$row['activationCode'], \$verifyCode)",
] as $contract) {
    if (!str_contains($controller, $contract)) {
        fwrite(STDERR, "Email verification must preserve its scalar code and native public-shell contracts.\n");
        exit(1);
    }
}

foreach ([
    'class="verificationCard verificationCard--<?=$state;?>"',
    'class="verificationHeading"',
    'class="verificationMessage"',
    "T('EVerify', 'TO_LOGIN_PLEASE_CLICK_HERE')",
    'style=',
] as $contract) {
    $present = str_contains($template, $contract);
    if (($contract === 'style=' && $present) || ($contract !== 'style=' && !$present)) {
        fwrite(STDERR, "Email verification must use the shared public card without inline presentation.\n");
        exit(1);
    }
}

if (!str_contains($template, "T('EVerify', 'Email verification')")
    || str_contains($template, '<h1 class="verificationHeading">Email verification</h1>')) {
    fwrite(STDERR, "Email verification must use its all-locale heading key.\n");
    exit(1);
}

echo "Email-verification layout contract passed.\n";
