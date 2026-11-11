<?php

declare(strict_types=1);

// The session token is stable, a stale or missing token is rejected, and an identical
// state-changing request is accepted once per replay window (a double click is harmless).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Action token runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Helper\WebService;
use Core\Session;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$expect = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$token = Session::getInstance()->getChecker();
$expect(preg_match('/^[0-9a-f]{32}$/', $token) === 1, 'The token must be 128 random bits.');
$expect(Session::getInstance()->getChecker() === $token && Session::getLogoutToken() === $token, 'The token must be stable and shared with the logout link.');
$expect(Session::matchesChecker($token) && !Session::matchesChecker('') && !Session::matchesChecker(null) && !Session::matchesChecker(['x']) && !Session::matchesChecker(substr($token, 1)), 'Only the exact token may match.');

$claimed = new ReflectionProperty(Session::class, 'claimedFingerprint');
$lastAction = WebService::fixSessionPrefix('last_action');
// Each call is a fresh request: new URI/body, and PHP's per-request static state is cleared.
$request = static function (string $uri, array $post = []) use ($claimed): void {
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
    $_POST = $post;
    $claimed->setValue(null, null);
};

unset($_SESSION[$lastAction]);
$request('/dorf1.php?id=1&gid=1&action=build&checksum=' . $token);
$expect(Session::claimAction($token), 'The first request must claim the action.');
$expect(Session::claimAction($token), 'A second check within the same request must agree.');
$request('/dorf1.php?id=1&gid=1&action=build&checksum=' . $token);
$expect(!Session::claimAction($token), 'An identical request right after must be rejected (double click).');
$request('/dorf1.php?id=2&gid=1&action=build&checksum=' . $token);
$expect(Session::claimAction($token), 'A different action must not be blocked by the previous one (second tab).');
$post = ['c' => $token, 'amount' => '5'];
$request('/build.php?id=5', $post);
$expect(Session::claimAction($token), 'The first POST must be claimed.');
$request('/build.php?id=5', $post);
$expect(!Session::claimAction($token), 'An identical POST right after must be rejected.');
$request('/dorf1.php?id=3&gid=1&action=build&checksum=stale');
$expect(!Session::claimAction('stale') && !Session::claimAction(null), 'A stale or missing token must be rejected.');

// After the window the same request is legitimate again (queueing the next level).
$request('/dorf1.php?id=1&gid=1&action=build&checksum=' . $token);
$_SESSION[$lastAction] = ['stale-fingerprint', microtime(true)];
$_SESSION[$lastAction][0] = sha1("GET\n/dorf1.php?id=1&gid=1&action=build&checksum=" . $token . "\n");
$_SESSION[$lastAction][1] -= 10;
$expect(Session::claimAction($token), 'The same request must be accepted again after the replay window.');

// Replacing the session (login/logout) replaces the token.
unset($_SESSION[WebService::fixSessionPrefix('csrf_token')], $_SESSION[$lastAction]);
$expect(Session::getInstance()->getChecker() !== $token, 'A new session must get a new token.');

echo "Action token runtime passed.\n";
