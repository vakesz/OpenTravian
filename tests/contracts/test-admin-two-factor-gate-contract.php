<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Core/Helper/WebService.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/Totp.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/TwoFactorStore.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/AdminRoles.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/AdminAccounts.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/TwoFactorGate.php';

use Game\Admin\Totp;
use Game\Admin\TwoFactorGate;
use Game\Admin\TwoFactorStore;

function failAdminTwoFactorGateContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectTwoFactorGate(bool $condition, string $message): void
{
    if (!$condition) {
        failAdminTwoFactorGateContract($message);
    }
}

$directory = sys_get_temp_dir() . '/travian-2fa-gate-' . bin2hex(random_bytes(4));
mkdir($directory, 0700);
$path = $directory . '/admin-2fa.local.json';
register_shutdown_function(static function () use ($directory): void {
    foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir($directory);
});

$session = [];
$make = static function (?string $override = null, string $ip = '203.0.113.9', int $now = 1_800_000_000) use ($path, &$session): TwoFactorGate {
    return new TwoFactorGate(new TwoFactorStore($path, $override), $session, 'w:', $ip, $now);
};

// Off (and a missing state file, i.e. a headless install): nobody is gated, every login passes.
expectTwoFactorGate(!$make()->applies(0) && $make()->permitLogin(0, false) && $make()->permitLogin(2, true), 'With two-factor off no sign-in may be gated.');

$store = new TwoFactorStore($path);
$store->setMode('optional');
expectTwoFactorGate(!$make()->applies(0), 'Optional mode must not gate an admin who has not enrolled.');
$secret = Totp::generateSecret();
$store->enrol(0, $secret);
expectTwoFactorGate($make()->applies(0) && !$make()->applies(2), 'Optional mode must gate an enrolled admin only.');
expectTwoFactorGate(!$make()->applies(5) && !$make()->applies(1), 'Player accounts must never be gated.');

$store->setMode('required');
expectTwoFactorGate($make()->applies(2), 'Required mode must gate every administrator account.');

// The environment can lower the gate (break-glass) but never raise it.
expectTwoFactorGate(!$make('off')->applies(0), 'The off override must lift the gate.');
$store->setMode('off');
expectTwoFactorGate(!$make('required')->applies(0), 'The environment must not raise the gate.');
$store->setMode('required');

// No grant, no sign-in; a sitter never passes, even with a grant.
expectTwoFactorGate(!$make()->permitLogin(0, false), 'A covered account must not sign in without a grant.');
$make()->grant(0);
expectTwoFactorGate(!$make()->permitLogin(0, true), 'A sitter must never pass the gate, grant or not.');
$make()->grant(0);
expectTwoFactorGate($make()->permitLogin(0, false), 'A live grant must permit exactly one sign-in.');
expectTwoFactorGate(!$make()->permitLogin(0, false), 'A grant must be spent by the sign-in it permitted.');

// A grant is bound to the account, the client IP and its short lifetime.
$make()->grant(0);
expectTwoFactorGate(!$make()->permitLogin(2, false), 'A grant for one account must not permit another.');
$make()->grant(0);
expectTwoFactorGate(!$make(null, '198.51.100.7')->permitLogin(0, false), 'A grant must be bound to the client IP.');
$make()->grant(0);
expectTwoFactorGate(!$make(null, '203.0.113.9', 1_800_000_000 + TwoFactorGate::GRANT_TTL + 1)->permitLogin(0, false), 'A grant must expire.');

// The pending sign-in: random token, five minutes, bound to the IP, no session rights.
$store->disable(0);
$gate = $make();
$token = $gate->begin(0, 'admin', 'pwhash');
$pending = $gate->pending($token);
expectTwoFactorGate($pending !== null && $pending['uid'] === 0 && $pending['state'] === 'enrol' && strlen($pending['secret']) === 32, 'An admin with no enrolment must be taken through enrolment.');
$store->enrol(0, $secret);
$token = $gate->begin(0, 'admin', 'pwhash');
expectTwoFactorGate($gate->pending($token)['state'] === 'verify' && strlen($token) === 32, 'An enrolled admin must be asked for a code.');
expectTwoFactorGate($gate->pending('0123456789abcdef0123456789abcdef') === null && $gate->pending(null) === null, 'The form token must match.');
expectTwoFactorGate($make(null, '198.51.100.7')->pending($token) === null, 'A pending sign-in must be bound to the client IP.');
expectTwoFactorGate($make(null, '203.0.113.9', 1_800_000_000 + TwoFactorGate::PENDING_TTL + 1)->pending($token) === null, 'A pending sign-in must expire after five minutes.');
expectTwoFactorGate($make()->pending($token) === null, 'An expired pending sign-in must be dropped.');
$token = $make()->begin(0, 'admin', 'pwhash');
expectTwoFactorGate(array_keys($session) === ['w:2fa_pending'] && !isset($session['w:uid']), 'A pending sign-in must not create an authenticated session.');
$make()->clear();
expectTwoFactorGate($make()->pending($token) === null, 'Clearing must end the pending sign-in.');

// Wiring: every sign-in path goes through Session::login, which asks the gate; remember-me never bypasses it.
$root = dirname(__DIR__, 2);
$sessionSource = (string) file_get_contents($root . '/game/include/Core/Session.php');
foreach ([
    'TwoFactorGate::fromRequest()',
    '$twoFactor->permitLogin((int)$uid, (bool)$isSitter)',
    '!$twoFactor->applies((int)$uid)',
    'RememberMe::revoke();',
] as $needle) {
    expectTwoFactorGate(str_contains($sessionSource, $needle), "Session must enforce the gate: missing {$needle}");
}
$loginSource = (string) file_get_contents($root . '/game/include/Controller/LoginCtrl.php');
foreach (["T(\"Login\", \"pwErrors.wrong\")", "login_throttle_buckets('admin2fa', \$uid)", '$gate->grant($pending[\'uid\'])', "\$_POST['twoFactorToken']"] as $needle) {
    expectTwoFactorGate(str_contains($loginSource, $needle), "The login must run the second step: missing {$needle}");
}
expectTwoFactorGate(str_contains((string) file_get_contents($root . '/game/include/Game/Admin/AdminMenu.php'), 'action=twoFactor'), 'The admin menu must link the two-factor page.');

echo "Admin two-factor gate contract passed.\n";
