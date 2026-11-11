<?php

declare(strict_types=1);

// Account deletion keeps the reference's UI (typed word, no password) behind CSRF, a sitter refusal, a throttle,
// the audit trail and the three-day queue; the export is a password-confirmed, CSRF-checked, audited download; an
// avatar in deletion is refused at auctions, the exchange office, resource sending and gold buying.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$check = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$check(str_contains($read('game/include/config.php'), "'deletionTime'                         => 3 * 86400,"), 'A deletion takes exactly three days.');

$controller = $read('game/include/Controller/OptionCtrl.php');
$check(str_contains($controller, "if (\$this->session->isSitter()) {\n            \$this->redirect('/options?s=1');"), 'Sitters never reach the Account tab.');
$check(str_contains($controller, 'Session::matchesChecker($_POST[\'a\']') && strpos($controller, 'matchesChecker') < strpos($controller, "isset(\$_POST['exportData'])"), 'The export and the deletion sit behind the CSRF check.');
foreach (["throttle_public_form('account-delete'", "throttle_public_form('account-export'", "AuditLog::record('account.delete-requested'", "AuditLog::record('account.delete-cancelled'", "AuditLog::record('account.export'", 'Password::verify($given, $stored)', "'Content-Disposition: attachment;", "header('Cache-Control: no-store')"] as $needle) {
    $check(str_contains($controller, $needle), "OptionCtrl must contain {$needle}");
}
$check(!str_contains($controller, 'getLatestPayment') && !str_contains($read('game/include/Model/OptionModel.php'), 'getLatestPayment'), 'The dead payment lock is gone.');

$template = $read('game/include/resources/Templates/options/Account.php');
$delete = substr($template, (int)strpos($template, 'id="del_acc"'), 1800);
$check(!str_contains($delete, 'type="password"') && str_contains($delete, 'confirmation_delete'), 'Delete keeps the reference UI: the typed word and no password.');
$check(str_contains($template, 'id="exportData"') && str_contains($template, 'name="exportData"') && str_contains($template, 'type="password"') && str_contains($template, "T('Login', 'input_password_label')"), 'The export form asks for the password with the existing label.');

$en = $read('game/include/resources/Translation/en-US.php');
foreach (['Export data', 'Download a JSON file with your account data, villages, hero, reports, messages and linked social accounts.', 'Download'] as $text) {
    $check(str_contains($en, "\$Definition['Options']['{$text}'] = '{$text}';"), "Approved export copy: {$text}");
}
foreach (['errorUserDel', 'auctionSellNotAllowedDeletion', 'cannot_send_resources_in_deletion', 'in_loeschung'] as $key) {
    $check(str_contains($en, "\$Definition['AccountDeletion']['{$key}']"), "Reference refusal text: {$key}");
}
$check(strpos($en, "\$Definition['AccountDeletion']['errorUserDel']") < strpos($en, 'return $Definition;'), 'The deletion texts come before the catalogue returns.');

foreach ([
    'game/include/Controller/HeroAuctionCtrl.php' => 'DeletionLock::inDeletion',
    'game/include/Controller/Ajax/silver/exchange.php' => 'DeletionLock::refusal',
    'game/include/Controller/Ajax/exchangeResourcesPopup.php' => 'DeletionLock::refusal',
    'game/include/Controller/Ajax/marketplace/exchangeResources.php' => 'DeletionLock::refusal',
    'game/include/Controller/Ajax/marketplace/prepare.php' => "DeletionLock::refusal((int) \$this->session->getPlayerId(), 'cannot_send_resources_in_deletion')",
    'game/include/Controller/Ajax/paymentCheckout.php' => "DeletionLock::refusal((int) \$session->getPlayerId(), 'in_loeschung')",
] as $file => $needle) {
    $check(str_contains($read($file), $needle), "{$file} must refuse an avatar in deletion.");
}

$export = $read('game/include/Game/Account/DataExport.php');
foreach (['password', 'ajax_token', 'private_key', 'subject'] as $secret) {
    $check(!preg_match('/SELECT[^;]*\b' . $secret . '\b/i', $export), "The export must not select {$secret}.");
}
$check(!str_contains($export, "['data']") && !str_contains($export, 'm.ip') && !str_contains($export, 'IpAddress'), 'No report bodies, no IP history in the export.');
echo "Account deletion and export contract passed.\n";
