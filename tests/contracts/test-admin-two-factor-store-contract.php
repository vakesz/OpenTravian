<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/Totp.php';
require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/TwoFactorStore.php';

use Game\Admin\Totp;
use Game\Admin\TwoFactorStore;

function failAdminTwoFactorStoreContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectTwoFactorStore(bool $condition, string $message): void
{
    if (!$condition) {
        failAdminTwoFactorStoreContract($message);
    }
}

$directory = sys_get_temp_dir() . '/travian-2fa-' . bin2hex(random_bytes(4));
mkdir($directory, 0700);
$path = $directory . '/admin-2fa.local.json';
register_shutdown_function(static function () use ($directory): void {
    foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }
    @rmdir($directory);
});

// No state file: two-factor is off and nothing is enrolled, so a fresh or headless install is unaffected.
$store = new TwoFactorStore($path);
expectTwoFactorStore($store->mode() === 'off' && !$store->isEnrolled(0), 'A missing state file must mean off and unenrolled.');
expectTwoFactorStore((new TwoFactorStore(''))->mode() === 'off', 'Without a state directory the mode must be off.');

expectTwoFactorStore($store->setMode('sometimes') === false, 'An unknown mode must be refused.');
expectTwoFactorStore($store->setMode('optional') && $store->mode() === 'optional', 'The mode must be stored.');
expectTwoFactorStore((fileperms($path) & 0777) === 0600, 'The state file must be 0600.');

// Enrolment returns ten one-time codes and stores only hashes.
$secret = Totp::generateSecret();
$codes = $store->enrol(0, $secret);
expectTwoFactorStore(is_array($codes) && count($codes) === 10 && preg_match('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $codes[0]) === 1, 'Enrolment must return ten recovery codes.');
expectTwoFactorStore($store->isEnrolled(0) && !$store->isEnrolled(2) && $store->recoveryCodesLeft(0) === 10, 'Only the enrolled admin must be enrolled.');
$raw = (string) file_get_contents($path);
expectTwoFactorStore(!str_contains($raw, str_replace('-', '', $codes[0])) && !str_contains($raw, $codes[0]) && str_contains($raw, '$2y$'), 'Recovery codes must be stored as password hashes only.');

// TOTP codes verify once.
$now = 1700000000;
$code = Totp::code($secret, Totp::timeStep($now));
expectTwoFactorStore($store->verifyCode(0, $code, $now), 'The current code must verify.');
expectTwoFactorStore(!$store->verifyCode(0, $code, $now), 'The same code must not verify twice.');
expectTwoFactorStore(!$store->verifyCode(0, '000000', $now) && !$store->verifyCode(2, $code, $now + 30), 'A wrong code or an unenrolled admin must fail.');
expectTwoFactorStore($store->verifyCode(0, Totp::code($secret, Totp::timeStep($now) + 1), $now + 30), 'The next step must verify.');

// Recovery codes are single use, tolerant of case and the dash.
expectTwoFactorStore($store->verifyRecoveryCode(0, strtoupper($codes[3])), 'A recovery code must verify, ignoring case.');
expectTwoFactorStore(!$store->verifyRecoveryCode(0, $codes[3]) && $store->recoveryCodesLeft(0) === 9, 'A recovery code must work only once.');
expectTwoFactorStore(!$store->verifyRecoveryCode(0, 'aaaaa-bbbbb'), 'An unknown recovery code must fail.');
$fresh = $store->regenerateRecoveryCodes(0);
expectTwoFactorStore(is_array($fresh) && $store->recoveryCodesLeft(0) === 10 && !$store->verifyRecoveryCode(0, $codes[0]) && $store->verifyRecoveryCode(0, $fresh[0]), 'Regenerating must replace every old code.');

// The environment can only lower the stored mode.
$store->setMode('required');
foreach ([['off', 'off'], ['optional', 'optional'], ['required', 'required'], [null, 'required']] as [$override, $expected]) {
    $effective = (new TwoFactorStore($path, $override))->mode();
    expectTwoFactorStore($effective === $expected, "Override {$override} over a required mode must give {$expected}, got {$effective}.");
}
$store->setMode('off');
expectTwoFactorStore((new TwoFactorStore($path, 'required'))->mode() === 'off', 'The environment must never raise the mode.');
$store->setMode('optional');
expectTwoFactorStore((new TwoFactorStore($path, 'off'))->isOverridden() && !(new TwoFactorStore($path, 'required'))->isOverridden(), 'isOverridden must report only a lowered mode.');

// Disabling removes the secret; an unreadable file fails closed.
expectTwoFactorStore($store->disable(0) && !$store->isEnrolled(0), 'Disabling must remove the enrolment.');
file_put_contents($path, '{not json');
expectTwoFactorStore((new TwoFactorStore($path))->mode() === 'required', 'A corrupt state file must fail closed (required).');
expectTwoFactorStore((new TwoFactorStore($path, 'off'))->mode() === 'off', 'The break-glass override must still lower a corrupt state.');
unlink($path);
symlink($directory . '/elsewhere', $path);
expectTwoFactorStore((new TwoFactorStore($path))->mode() === 'required' && (new TwoFactorStore($path))->setMode('off') === false, 'A symlinked state file must be refused.');

echo "Admin two-factor store contract passed.\n";
