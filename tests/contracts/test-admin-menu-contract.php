<?php

declare(strict_types=1);

use Game\Admin\AdminMenu;

// The panel's navigation is data: every link must lead somewhere, the Simple view must stay short
// and keep what operators need daily, and no page may be reachable only through a hidden menu.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Admin/AdminRoles.php';
require_once $root . '/game/include/Game/Admin/AdminMenu.php';

function expectAdminMenu(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$context = ['staff' => true, 'gameWorldUrl' => '/', 'preregistration' => true, 'logoutToken' => 'tok'];
$definition = AdminMenu::definition($context);
$urls = [];
$simple = [];
foreach ($definition as $group) {
    foreach ($group['items'] as [$url, $label, $isSimple]) {
        expectAdminMenu(!isset($urls[$url]), "The menu lists $url twice.");
        $urls[$url] = $label;
        if ($isSimple) {
            $simple[] = $url;
        }
        if (preg_match('~^admin\.php\?action=([A-Za-z0-9]+)(?:&|$)~', $url, $match) === 1 && $match[1] !== 'logout') {
            expectAdminMenu(is_file($root . '/game/include/admin/include/Controllers/' . ucfirst($match[1]) . 'Ctrl.php'), "The menu links $url but there is no controller for it.");
        }
    }
}

// Pages reached from a list or a form, not from the menu.
$contextual = ['EditAlliance', 'EditPlayer', 'EditVillage', 'Main', 'NotFound', 'UpgradeVillage'];
$linked = array_map(static fn (string $url): string => preg_match('~action=([A-Za-z0-9]+)~', $url, $m) === 1 ? ucfirst($m[1]) : '', array_keys($urls));
foreach (glob($root . '/game/include/admin/include/Controllers/*Ctrl.php') ?: [] as $file) {
    $name = basename($file, 'Ctrl.php');
    expectAdminMenu(in_array($name, $linked, true) || in_array($name, $contextual, true), "$name has a controller but no menu entry (add one, or list it as contextual).");
}

// Simple: short, and it keeps the daily essentials, including moderation's ban list and player reports.
expectAdminMenu(count($simple) <= 16, 'The Simple view must stay short (' . count($simple) . ' entries).');
foreach (['admin.php', 'admin.php?action=users', 'admin.php?action=bannedList', 'admin.php?action=playerAccusations', 'admin.php?action=configurationDetails', 'admin.php?action=truce', 'admin.php?action=engineHealth', 'admin.php?action=backups', 'admin.php?action=twoFactor', 'admin.php?action=changePassword', 'admin.php?action=adminLog'] as $needed) {
    expectAdminMenu(in_array($needed, $simple, true), "The Simple view must list $needed.");
}

$view = static fn (bool $advanced, string $current): array => array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 0), AdminMenu::groups($context, $advanced, $current)));
$simpleView = $view(false, 'admin.php');
$advancedView = $view(true, 'admin.php');
expectAdminMenu(count($advancedView) === count($urls) && $simpleView !== [] && count($simpleView) < count($advancedView), 'Advanced lists every entry and Simple fewer.');
expectAdminMenu(!in_array('admin.php?action=roundReset', $simpleView, true) && in_array('admin.php?action=roundReset', $view(false, 'admin.php?action=roundReset'), true), 'An Advanced page opened by URL appears in the Simple sidebar while it is the current page.');
expectAdminMenu(array_diff($simpleView, $advancedView) === [], 'Everything in Simple is in Advanced.');
foreach (AdminMenu::groups($context, false, 'admin.php') as $group) {
    expectAdminMenu($group['items'] !== [], 'A group with nothing to show is dropped.');
}
expectAdminMenu(count(AdminMenu::definition(['preregistration' => false] + $context)) === count($definition) - 1, 'The Registration group only exists when a pre-registration key is required.');
$noStaff = array_merge(...array_map(static fn (array $group): array => array_column($group['items'], 0), AdminMenu::definition(['staff' => false] + $context)));
expectAdminMenu(!in_array('admin.php?action=inGameMessage', $noStaff, true) && !in_array('/', $noStaff, true), 'Support staff links are for staff only.');

echo "Admin menu contract passed.\n";
