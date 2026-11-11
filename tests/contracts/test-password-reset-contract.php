<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/PasswordCtrl.php');

if ($controller === false) {
    fwrite(STDERR, "Could not read the password-reset controller.\n");
    exit(1);
}

foreach ([
    "bodyCssClass'] = 'perspectiveBuildings login passwordReset'",
    'class="passwordResetCard"',
    'class="passwordResetForm"',
    'Session::getCheckerInput()',
    'id="passwordNew"',
    'id="passwordConfirm"',
    'autocomplete="new-password"',
    'DELETE FROM newproc WHERE uid=? AND cpw=?',
    'Password::hash($password)',
] as $contract) {
    if (!str_contains($controller, $contract)) {
        fwrite(STDERR, "Password reset must retain its native form and single-use-token contract.\n");
        exit(1);
    }
}

if (str_contains($controller, 'autocomplete="off"')
    || !str_contains($controller, "htmlspecialchars(\$token, ENT_QUOTES, 'UTF-8')")) {
    fwrite(STDERR, "Password reset must retain explicit password-manager and token escaping behavior.\n");
    exit(1);
}

echo "Password reset contract passed.\n";
