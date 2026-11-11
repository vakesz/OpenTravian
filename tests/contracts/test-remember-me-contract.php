<?php

declare(strict_types=1);

/**
 * Behavioural contract for Core\RememberMe against the local world DB. Run in
 * the php container. Creates a dedicated throw-away account so it never
 * reads, rotates, or revokes a real player's persistent-login tokens.
 */
function failRememberMe(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$schema = (string)file_get_contents($root . '/database/world.sql');
if (!str_contains($schema, 'CREATE TABLE IF NOT EXISTS `remember_tokens`')
    || !str_contains($schema, '`validator_hash` CHAR(64)') || !str_contains($schema, 'UNIQUE KEY `selector`')) {
    failRememberMe('world.sql must define remember_tokens with a unique selector and a hashed validator.');
}

$src = (string)file_get_contents($root . '/game/include/Core/RememberMe.php');
if (!str_contains($src, "hash('sha256', \$validator)") || str_contains($src, "'validator' =>")) {
    failRememberMe('Only a hash of the validator may be stored.');
}

if (!is_file('/travian/game/include/bootstrap.php') || getenv('TRAVIAN_STATE_DIR') === false) {
    echo "Remember-me static contract passed (DB behaviour skipped outside the php container).\n";
    exit(0);
}

require '/travian/game/deployment/include/env.php';
$_SERVER += ['HTTP_HOST' => 'localhost:8080', 'REMOTE_ADDR' => '127.0.0.1', 'REQUEST_URI' => '/', 'PHP_SELF' => '/index.php'];
require '/travian/game/include/bootstrap.php';

use Core\Database\DBI;
use Core\RememberMe;

$db = DBI::getInstance();
$fixtureUuid = 'remember-contract-' . bin2hex(random_bytes(8));
$fixtureName = 'remember_' . bin2hex(random_bytes(5));
$pwBefore = password_hash('remember-contract-fixture', PASSWORD_DEFAULT);
if (!is_string($pwBefore)) {
    failRememberMe('Could not hash the dedicated remember-me fixture password.');
}
$uid = 0;

$cleanup = static function () use ($db, &$uid, $fixtureUuid): void {
    if ($uid < 1) {
        return;
    }

    // Bind cleanup to this random fixture identity as a final guard against
    // deleting a player account should a future change corrupt $uid.
    $fixtureExists = (int)$db->fetchScalar(
        'SELECT COUNT(*) FROM users WHERE id=? AND uuid=?',
        [$uid, $fixtureUuid]
    ) === 1;
    if (!$fixtureExists) {
        return;
    }

    $db->run('DELETE FROM remember_tokens WHERE uid=?', [$uid]);
    $db->run('DELETE FROM users WHERE id=? AND uuid=?', [$uid, $fixtureUuid]);
};
register_shutdown_function($cleanup);

try {
    $db->run(
        'INSERT INTO users (uuid, name, password, email, race, kid, desc1, desc2, note) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$fixtureUuid, $fixtureName, $pwBefore, $fixtureName . '@test.local', 1, 0, '', '', '']
    );
    $uid = (int)$db->lastInsertId();
    if ($uid < 1) {
        failRememberMe('Could not create the dedicated remember-me fixture account.');
    }

    $fixturePassword = $db->fetchScalar('SELECT password FROM users WHERE id=? AND uuid=?', [$uid, $fixtureUuid]);
    if (!is_string($fixturePassword) || !hash_equals($pwBefore, $fixturePassword)) {
        failRememberMe('Dedicated remember-me fixture account is unavailable.');
    }

    RememberMe::issue($uid);
    $cookie1 = $_COOKIE[RememberMe::COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{18}:[a-f0-9]{64}$/', $cookie1)) {
        failRememberMe('Cookie must be selector:validator.');
    }
    [$selector, $validator] = explode(':', $cookie1);
    $row = $db->fetchSingleRow('SELECT * FROM remember_tokens WHERE selector=?', [$selector]);
    if (!$row || $row['validator_hash'] !== hash('sha256', $validator) || str_contains(json_encode($row), $validator)) {
        failRememberMe('The validator must be stored hashed only.');
    }
    if ((int)$row['expires'] < time() + 29 * 86400) {
        failRememberMe('Token must last about 30 days.');
    }

    $got = RememberMe::consume();
    if ($got === null || $got[0] !== $uid || $got[1] !== $pwBefore) {
        failRememberMe('A valid cookie must authenticate the owner.');
    }
    $cookie2 = $_COOKIE[RememberMe::COOKIE];
    if ($cookie2 === $cookie1 || explode(':', $cookie2)[0] !== $selector) {
        failRememberMe('Validator must rotate on use (same selector, new validator).');
    }

    $_COOKIE[RememberMe::COOKIE] = $cookie1;
    if (RememberMe::consume() === null) {
        failRememberMe('The previous validator must stay valid for the short rotation grace (parallel tabs).');
    }
    $db->run('UPDATE remember_tokens SET rotated=? WHERE selector=?', [time() - 3600, $selector]);
    $_COOKIE[RememberMe::COOKIE] = $cookie1;
    if (RememberMe::consume() !== null || $db->fetchSingleRow('SELECT id FROM remember_tokens WHERE selector=?', [$selector])) {
        failRememberMe('A stale validator must be rejected and the selector revoked.');
    }

    RememberMe::issue($uid);
    // Password change simulation: the stored fingerprint no longer matches the account hash.
    // (The account row is never modified by this contract.)
    $db->run('UPDATE remember_tokens SET pw_fingerprint=? WHERE uid=?', [RememberMe::fingerprint('some other hash'), $uid]);
    if (RememberMe::consume() !== null) {
        failRememberMe('A password change must invalidate remembered logins.');
    }

    RememberMe::issue($uid);
    RememberMe::revoke();
    if ($db->fetchScalar('SELECT COUNT(*) FROM remember_tokens WHERE uid=?', [$uid]) != 0) {
        failRememberMe('Logout must revoke the token.');
    }
    RememberMe::issue($uid);
    RememberMe::issue($uid);
    RememberMe::revokeAll($uid);
    if ($db->fetchScalar('SELECT COUNT(*) FROM remember_tokens WHERE uid=?', [$uid]) != 0) {
        failRememberMe('revokeAll must remove every token of the account.');
    }
} finally {
    $cleanup();
}

echo "Remember-me contract passed.\n";
