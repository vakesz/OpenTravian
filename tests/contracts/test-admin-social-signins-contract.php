<?php

declare(strict_types=1);

// The control panel's social sign-ins page: reachable from the menu and the player page, unlink only as a POST
// (so the panel's CSRF guard applies), audited, and the new audit pieces exist.

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$controller = $read('game/include/admin/include/Controllers/SocialSignInsCtrl.php');
if (!str_contains($controller, 'WebService::isPost()') || !str_contains($controller, 'LinkedIdentities::unlink') || !str_contains($controller, "AuditLog::record('social.unlink'")) {
    $fail('Unlinking must be a POST that removes the identity and writes a social.unlink audit row.');
}
if (str_contains($controller, "\$_GET['uid']") || str_contains($controller, "\$_GET['provider']") && str_contains($controller, 'unlink(')) {
    $fail('An unlink must never read its target from the query string.');
}
if (!str_contains($read('game/include/Game/Admin/AdminMenu.php'), "['admin.php?action=socialSignIns', 'Social sign-ins'")) {
    $fail('The menu must link the page.');
}
if (!str_contains($read('game/include/admin/include/Controllers/EditPlayerCtrl.php'), 'AdminView::identitiesTable')) {
    $fail('The player page must show the player\'s linked accounts.');
}
if (str_contains($read('game/include/Game/SocialAuth/AdminView.php'), "['subject']") || str_contains($read('game/include/Game/SocialAuth/AdminView.php'), 'i.subject')) {
    $fail('The panel must never read or show the provider subject.');
}
$audit = $read('game/include/Game/Admin/AuditLog.php');
if (!str_contains($audit, "'provider'];") || !str_contains($audit, "'eventPrefix'")) {
    $fail('The audit log must name the provider in a target and filter by action prefix.');
}
foreach (['OAuthLinkCtrl', 'OAuthUnlinkCtrl', 'OAuthCallbackCtrl'] as $name) {
    if (!str_contains($read("game/include/Controller/{$name}.php"), 'SocialAudit::record(')) {
        $fail("{$name} must write to the audit trail.");
    }
}
echo "Admin social sign-ins contract passed.\n";
