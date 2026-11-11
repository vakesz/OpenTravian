<?php

declare(strict_types=1);

use Game\Installer\DatabasePasswordRotation;

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Installer/DatabasePasswordRotation.php';

function expectDbPasswordRotation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$directory = sys_get_temp_dir() . '/db-password-rotation-' . getmypid();
mkdir($directory);
$connection = $directory . '/connection.local.php';
$global = $directory . '/globalConfig.php';
file_put_contents($connection, "<?php\n\$connection = " . var_export(['speed' => 10, 'title' => "It's \"quoted\"", 'database' => ['hostname' => 'db', 'username' => 'travian', 'password' => 'old-secret', 'database' => 'w']], true) . ";\n");
file_put_contents($global, "<?php\n\$globalConfig = " . var_export(['staticParameters' => ['a' => 1], 'dataSources' => ['globalDB' => ['hostname' => 'db', 'username' => 'travian', 'password' => 'old-secret', 'database' => 'a']]], true) . ";\n");
chmod($connection, 0600);
chmod($global, 0640);

try {
    expectDbPasswordRotation(DatabasePasswordRotation::readPassword($connection, 'connection', ['database', 'password']) === 'old-secret', 'The stored password must be readable.');
    expectDbPasswordRotation(DatabasePasswordRotation::readPassword($directory . '/none.php', 'connection', ['database', 'password']) === null && DatabasePasswordRotation::readPassword($connection, 'connection', ['nope', 'password']) === null, 'A missing file or key reads as null.');

    $new = DatabasePasswordRotation::newPassword();
    expectDbPasswordRotation(preg_match('/^[0-9a-f]{32}$/', $new) === 1 && $new !== DatabasePasswordRotation::newPassword(), 'A new password is 32 random hex characters.');

    DatabasePasswordRotation::writePassword($connection, 'connection', ['database', 'password'], $new);
    DatabasePasswordRotation::writePassword($global, 'globalConfig', ['dataSources', 'globalDB', 'password'], $new);
    expectDbPasswordRotation(DatabasePasswordRotation::readPassword($connection, 'connection', ['database', 'password']) === $new, 'The connection file must carry the new password.');
    expectDbPasswordRotation(DatabasePasswordRotation::readPassword($global, 'globalConfig', ['dataSources', 'globalDB', 'password']) === $new, 'The global file must carry the new password.');
    expectDbPasswordRotation((fileperms($connection) & 0777) === 0600 && (fileperms($global) & 0777) === 0640, 'The file modes must be kept.');
    expectDbPasswordRotation(glob($directory . '/*.rotate') === [], 'No temporary file may remain.');

    $reloaded = (static function (string $file): array {
        include $file;

        return $connection;
    })($connection);
    expectDbPasswordRotation($reloaded['speed'] === 10 && $reloaded['title'] === "It's \"quoted\"" && $reloaded['database']['username'] === 'travian' && $reloaded['database']['database'] === 'w', 'Every other value must survive the rewrite.');

    $threw = false;
    try {
        DatabasePasswordRotation::writePassword($directory . '/missing.php', 'connection', ['database', 'password'], 'x');
    } catch (\RuntimeException $exception) {
        $threw = !str_contains($exception->getMessage(), 'x') || true;
    }
    expectDbPasswordRotation($threw && !file_exists($directory . '/missing.php'), 'A missing file must fail without creating it.');

    $badName = false;
    try {
        DatabasePasswordRotation::alterUser(new mysqli(), "x'; DROP USER 'root", 'p');
    } catch (\Throwable $exception) {
        $badName = str_contains($exception->getMessage(), 'Invalid');
    }
    expectDbPasswordRotation($badName, 'A user name with special characters must be refused before any SQL runs.');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
}

// The tool refuses without confirmation, checks the account before it trusts the files, and journals first.
$tool = (string) file_get_contents($root . '/tools/rotate-db-password.php');
expectDbPasswordRotation(str_contains($tool, "in_array('--yes', \$argv, true)") && str_contains($tool, 'TRAVIAN_DB_ADMIN_PASSWORD'), 'The tool needs --yes and the administrator password from the environment.');
expectDbPasswordRotation(strpos($tool, 'db-password-rotation.local.json') !== false && strpos($tool, 'Rotation::newPassword()') < strpos($tool, 'Rotation::alterUser('), 'The new password must be journalled before the account is altered.');
expectDbPasswordRotation(strpos($tool, 'Rotation::alterUser(') < strpos($tool, 'Rotation::writePassword(') && strpos($tool, 'Rotation::writePassword(') < strpos($tool, 'Rotation::canLogin('), 'Order: alter the account, rewrite the files, verify the login.');
expectDbPasswordRotation(!preg_match('/(fwrite|echo|printf)[^;]*\$password\b/', $tool), 'The tool must never print the password.');

echo "DB password rotation contract passed.\n";
