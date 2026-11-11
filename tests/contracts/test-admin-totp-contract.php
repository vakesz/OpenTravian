<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Game/Admin/Totp.php';

use Game\Admin\Totp;

function failAdminTotpContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

// RFC 6238 appendix B (SHA-1, secret "12345678901234567890"); the 6-digit code is the last six digits of the listed 8-digit value.
$secret = Totp::base32Encode('12345678901234567890');
if ($secret !== 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ' || Totp::base32Decode($secret) !== '12345678901234567890') {
    failAdminTotpContract('Base32 must round-trip the RFC 6238 test key.');
}
foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037', 20000000000 => '353130'] as $time => $expected) {
    if (Totp::code($secret, Totp::timeStep($time)) !== $expected) {
        failAdminTotpContract("RFC 6238 vector for t={$time} must be {$expected}.");
    }
}

// Verification: window, replay and malformed input.
$now = 1111111109;
$step = Totp::timeStep($now);
if (Totp::verify($secret, '081804', $now) !== $step) {
    failAdminTotpContract('The current code must verify and report its step.');
}
if (Totp::verify($secret, ' 081 804 ', $now) !== $step) {
    failAdminTotpContract('Spaces in a typed code must be ignored.');
}
if (Totp::verify($secret, Totp::code($secret, $step - 1), $now) !== $step - 1 || Totp::verify($secret, Totp::code($secret, $step + 1), $now) !== $step + 1) {
    failAdminTotpContract('One step of clock drift either way must verify.');
}
if (Totp::verify($secret, Totp::code($secret, $step + 2), $now) !== null || Totp::verify($secret, Totp::code($secret, $step - 2), $now) !== null) {
    failAdminTotpContract('Two steps away must be refused.');
}
if (Totp::verify($secret, '081804', $now, 1, $step) !== null) {
    failAdminTotpContract('A step that was already used must be refused (replay).');
}
foreach (['', '12345', '1234567', 'abcdef', '08180x'] as $bad) {
    if (Totp::verify($secret, $bad, $now) !== null) {
        failAdminTotpContract('A malformed code must be refused: ' . $bad);
    }
}

$generated = Totp::generateSecret();
if (preg_match('/^[A-Z2-7]{32}$/', $generated) !== 1 || $generated === Totp::generateSecret()) {
    failAdminTotpContract('Generated secrets must be 32 random base32 characters.');
}
if (Totp::base32Decode('not base32!') !== null || Totp::base32Decode('') !== null) {
    failAdminTotpContract('Invalid base32 must decode to null.');
}

$uri = Totp::uri('JBSWY3DPEHPK3PXP', 'player', 'Travian local');
if ($uri !== 'otpauth://totp/Travian%20local:player?secret=JBSWY3DPEHPK3PXP&issuer=Travian%20local&algorithm=SHA1&digits=6&period=30') {
    failAdminTotpContract('The otpauth URI must carry issuer, secret and the fixed SHA1/6/30 parameters.');
}

echo "Admin TOTP contract passed.\n";
