<?php

declare(strict_types=1);

// Rotates a throwaway database user's password on the real server: ALTER USER on every host, the
// old password stops working and the new one logs in. Needs a database user that may CREATE USER:
// set TRAVIAN_TEST_ADMIN_PASSWORD (and TRAVIAN_TEST_ADMIN_USER, default root). Without one it
// prints a SKIPPED banner on stderr and exits 0.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "DB password rotation runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/Game/Installer/DatabasePasswordRotation.php';

use Game\Installer\DatabasePasswordRotation;

$skip = static function (string $reason): never {
    fwrite(STDERR, "\n*** SKIPPED: db password rotation: $reason\n*** Set TRAVIAN_TEST_ADMIN_PASSWORD (and TRAVIAN_TEST_ADMIN_USER, default root) to a database administrator.\n\n");
    echo "DB password rotation runtime passed (SKIPPED).\n";
    exit(0);
};
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$password = getenv('TRAVIAN_TEST_ADMIN_PASSWORD');
$connectionFile = (getenv('TRAVIAN_STATE_DIR') ?: '') . '/connection.local.php';
if ($password === false || !is_file($connectionFile)) {
    $skip('no administrator password or no connection file.');
}
$host = (static function (string $file): string {
    include $file;

    return (string) ($connection['database']['hostname'] ?? '');
})($connectionFile);
mysqli_report(MYSQLI_REPORT_OFF);
$admin = @new mysqli($host, getenv('TRAVIAN_TEST_ADMIN_USER') ?: 'root', $password);
if ($admin->connect_error) {
    $skip('cannot connect as the administrator.');
}
$suffix = getmypid();
$user = "zz_rotate_$suffix";
$database = "zz_rotate_db_$suffix";
try {
    if (!$admin->query("CREATE DATABASE `$database`") || !$admin->query("CREATE USER '$user'@'%' IDENTIFIED BY 'first-password'") || !$admin->query("GRANT SELECT ON `$database`.* TO '$user'@'%'")) {
        $skip('the administrator cannot create a throwaway user.');
    }
    if (!DatabasePasswordRotation::canLogin($host, $user, 'first-password', [$database])) {
        $fail('The throwaway user must log in with its first password.');
    }
    $new = DatabasePasswordRotation::newPassword();
    if (DatabasePasswordRotation::alterUser($admin, $user, $new) !== 1) {
        $fail('One account host must have been changed.');
    }
    if (DatabasePasswordRotation::canLogin($host, $user, 'first-password', [$database])) {
        $fail('The old password must stop working.');
    }
    if (!DatabasePasswordRotation::canLogin($host, $user, $new, [$database])) {
        $fail('The new password must log in.');
    }
    $missing = false;
    try {
        DatabasePasswordRotation::alterUser($admin, "zz_nobody_$suffix", 'x');
    } catch (\RuntimeException $exception) {
        $missing = str_contains($exception->getMessage(), 'does not exist');
    }
    if (!$missing) {
        $fail('Rotating a user that does not exist must fail.');
    }
} finally {
    $admin->query("DROP USER IF EXISTS '$user'@'%'");
    $admin->query("DROP DATABASE IF EXISTS `$database`");
}

echo "DB password rotation runtime passed.\n";
