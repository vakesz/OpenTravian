<?php

declare(strict_types=1);

use Game\Admin\AdminAccounts;
use Game\Admin\AdminMenu;
use Game\Admin\AdminRoles;

// Two panel roles: the role table covers every page, owners-only pages stay out of a moderator's menu and
// reach, and every admin check goes through AdminAccounts (no hard-coded ids 0 and 2 left in Session).

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Admin/AdminRoles.php';
require_once $root . '/game/include/Game/Admin/AdminAccounts.php';
require_once $root . '/game/include/Game/Admin/AdminMenu.php';

function expectAdminAccounts(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

// The table lists every control panel controller and nothing else.
$controllers = [];
foreach (glob($root . '/game/include/admin/include/Controllers/*Ctrl.php') ?: [] as $file) {
    $controllers[] = strtolower(basename($file, 'Ctrl.php'));
}
sort($controllers);
$listed = array_keys(AdminRoles::MINIMUM);
sort($listed);
expectAdminAccounts($controllers === $listed, 'AdminRoles::MINIMUM must list every control panel controller: ' . implode(', ', array_merge(array_diff($controllers, $listed), array_diff($listed, $controllers))));

// Owners: everything. Moderators: the pages listed for them. Unlisted pages: owners only.
foreach (['payments', 'roundReset', 'adminAccounts', 'goldPackageCodeGenerator', 'giftGoldPackageCodeGenerator'] as $action) {
    expectAdminAccounts(AdminRoles::allows('owner', $action) && !AdminRoles::allows('moderator', $action), "$action is for owners only.");
    expectAdminAccounts(AdminRoles::MINIMUM[strtolower($action)] === AdminRoles::OWNER, "$action has its own single line in the role table.");
}
foreach (['editPlayer', 'IPBan', 'backups', 'worldRules', 'adminLog', 'changePassword', 'main'] as $action) {
    expectAdminAccounts(AdminRoles::allows('moderator', $action), "A moderator may open $action.");
}
expectAdminAccounts(AdminRoles::allows('owner', 'aNewPage') && !AdminRoles::allows('moderator', 'aNewPage') && !AdminRoles::allows(null, 'main'), 'An unlisted page needs an owner, and nobody without a role gets in.');

// The menu follows the role.
$context = ['staff' => true, 'gameWorldUrl' => '/', 'preregistration' => true, 'logoutToken' => 't'];
$urls = static function (?string $role) use ($context): array {
    $urls = [];
    foreach (AdminMenu::groups($context + ['role' => $role], true, 'admin.php') as $group) {
        foreach ($group['items'] as [$url]) {
            $urls[] = $url;
        }
    }

    return $urls;
};
$owner = $urls('owner');
$moderator = $urls('moderator');
foreach (['admin.php?action=adminAccounts', 'admin.php?action=payments', 'admin.php?action=roundReset'] as $url) {
    expectAdminAccounts(in_array($url, $owner, true) && !in_array($url, $moderator, true), "$url is in an owner's menu and not in a moderator's.");
}
expectAdminAccounts(in_array('admin.php?action=editPlayer', $owner, true) === in_array('admin.php?action=editPlayer', $moderator, true) && in_array('admin.php?action=backups', $moderator, true), 'A moderator keeps the other pages.');
expectAdminAccounts(count(array_filter($moderator, static fn (string $url): bool => str_starts_with($url, 'admin.php?action=logout'))) === 1, 'Everybody keeps the log-out link.');

// One lookup answers "is this an administrator".
AdminAccounts::useResolver(static fn (int $uid): ?array => match ($uid) {
    7 => ['role' => 'moderator', 'disabled' => false, 'mustChange' => true],
    8 => ['role' => 'owner', 'disabled' => true, 'mustChange' => false],
    9 => ['role' => 'owner', 'disabled' => false, 'mustChange' => false],
    default => null,
});
expectAdminAccounts(AdminAccounts::role(0) === 'owner' && AdminAccounts::role(2) === 'owner' && AdminAccounts::isPanelOnly(0) && !AdminAccounts::isPanelOnly(2), 'Support and Multihunter are built-in owners; only Support has no village.');
expectAdminAccounts(AdminAccounts::role(7) === 'moderator' && AdminAccounts::isAdmin(7) && AdminAccounts::isPanelOnly(7) && AdminAccounts::mustChangePassword(7), 'An extra account takes its role from the table and starts with a temporary password.');
expectAdminAccounts(!AdminAccounts::isAdmin(8) && AdminAccounts::isDisabled(8) && AdminAccounts::role(8) === null, 'A disabled account is not an administrator.');
expectAdminAccounts(!AdminAccounts::isAdmin(1) && !AdminAccounts::isAdmin(5) && !AdminAccounts::isAdmin(500) && !AdminAccounts::isDisabled(5), 'Players and the Natars are never administrators.');
expectAdminAccounts(AdminAccounts::refusal(2, 9, 'remove') !== null && AdminAccounts::refusal(0, 9, 'disable') !== null, 'Built-in accounts cannot be changed.');
expectAdminAccounts(AdminAccounts::refusal(7, 7, 'disable') !== null, 'Nobody changes their own account.');
AdminAccounts::useResolver(null);

// No hard-coded administrator ids are left where the checks live.
$session = (string) file_get_contents($root . '/game/include/Core/Session.php');
expectAdminAccounts(!str_contains($session, '[0, 2]') && !str_contains($session, "== 2 || \$this->data['access']") && !str_contains($session, "<= 2 && \$this->checkAdminIP()"), 'Session must not hard-code the administrator ids.');
expectAdminAccounts(str_contains($session, 'AdminAccounts::isDisabled((int)$uid)') && str_contains($session, 'endDisabledAdminSession'), 'A disabled administrator cannot sign in and loses a live session.');
$gate = (string) file_get_contents($root . '/game/include/Game/Admin/TwoFactorGate.php');
expectAdminAccounts(!str_contains($gate, 'ADMIN_IDS') && str_contains($gate, 'AdminAccounts::isAdmin($uid)'), 'The two-factor gate uses the same lookup.');
$dispatcher = (string) file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
expectAdminAccounts(str_contains($dispatcher, 'AdminRoles::allows($role, $handlerName)') && str_contains($dispatcher, "'access-denied'") && str_contains($dispatcher, 'AdminAccounts::mustChangePassword'), 'The dispatcher enforces the role, audits a refusal and sends a temporary password to the change page.');
$ctrl = (string) file_get_contents($root . '/game/include/admin/include/Controllers/AdminAccountsCtrl.php');
expectAdminAccounts(str_contains($ctrl, 'isPost() && Session::validateChecker()') && substr_count($ctrl, 'confirmButton(') >= 4 && str_contains($ctrl, 'AdminAccounts::refusal('), 'Every change is a POST with the CSRF token, asks first and passes the guards.');
$reset = (string) file_get_contents($root . '/game/include/Game/Round/RoundReset.php');
expectAdminAccounts(str_contains($reset, 'restoreAdmins') && str_contains($reset, 'NOT IN (SELECT uid FROM admin_accounts)'), 'A round reset keeps the administrators and does not treat them as players.');
expectAdminAccounts(str_contains((string) file_get_contents($root . '/database/world.sql'), 'CREATE TABLE `admin_accounts`'), 'The schema has the admin_accounts table.');

echo "Admin accounts contract passed.\n";
