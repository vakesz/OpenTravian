<?php

declare(strict_types=1);

// Session::claimAction() blocks a double submit of the same action, but a page rendered in between
// ends the guard: building the next level of the same slot right after reopening it must work.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Action replay guard runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Session;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$_SESSION = [];
$token = (new ReflectionMethod(Session::class, 'sessionToken'))->invoke(null);
$claimed = new ReflectionProperty(Session::class, 'claimedFingerprint');
/** One request: a fresh process state, the same session. */
$request = static function (string $uri) use ($claimed): void {
    $claimed->setValue(null, null);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_POST = [];
};
$build = '/dorf2.php?id=25&gid=15&action=build&checksum=' . $token;

$request($build);
Session::claimAction($token) || $fail('The first build request must be accepted.');
Session::claimAction($token) || $fail('Repeated checks within one request must agree.');

$request($build);
!Session::claimAction($token) || $fail('A double submit without a page in between must be blocked.');

$request('/dorf2.php');
Session::pageRendered();
$request('/build.php?id=25');
Session::pageRendered();

$request($build);
Session::claimAction($token) || $fail('The next level, requested from a freshly rendered page, must be accepted.');

// A page rendered by the action request itself does not end its own guard.
Session::pageRendered();
$request($build);
!Session::claimAction($token) || $fail('A page rendered by the acting request must not reopen the guard.');

!Session::claimAction('wrong') || $fail('A wrong token is never accepted.');

echo "Action replay guard runtime passed.\n";
