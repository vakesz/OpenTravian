<?php

declare(strict_types=1);

function failAdminShell(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$admin = $root . '/game/include/admin/include';
$templates = glob($admin . '/templates/*.php') ?: [];
$controllers = glob($admin . '/Controllers/*.php') ?: [];
$legacyTemplates = glob($root . '/game/include/resources/Templates/admin/*.php') ?: [];
if (!$templates || !$controllers) {
    failAdminShell('The admin templates and controllers must exist.');
}
if (is_file($admin . '/templates.php') || is_dir($root . '/game/deployment/public/img/admin')) {
    failAdminShell('The monolithic admin template file and the legacy img/admin asset directory must stay removed.');
}

// Legacy markup must not return in any admin view or controller-built fragment.
$forbidden = [
    '<font', '<center', 'cellpadding', 'cellspacing', 'type="image"', 'img/admin/', 'img/spacer.gif',
    'class="fm', 'id="member"', 'class="dynamic_img"', 'bgcolor', 'AmirhosseinMatini',
];
foreach (array_merge($templates, $controllers, $legacyTemplates) as $file) {
    $source = (string)file_get_contents($file);
    foreach ($forbidden as $needle) {
        if (str_contains($source, $needle)) {
            failAdminShell(basename($file) . ' contains legacy admin markup: ' . $needle);
        }
    }
}

// The shell must use the self-hosted Gpack stylesheets only.
$layout = (string)file_get_contents($admin . '/templates/layout.php');
if (preg_match('#(?:src|href)="https?://#i', $layout) || !str_contains($layout, 'lang/admin.css')
    || !str_contains($layout, "get_gpack_link_and_hash('imports.css')")) {
    failAdminShell('The admin layout must load imports.css and lang/admin.css, and no external URL.');
}
foreach (['.adminShell', '.adminMenu', '.adminBox', '.adminTable', '.adminForm', '.adminNotice'] as $selector) {
    if (!str_contains((string)file_get_contents($root . '/assets/gpack/travian/mainPage/lang/admin.css'), $selector)) {
        failAdminShell("admin.css is missing $selector.");
    }
}

// Every template a controller loads must exist, and deleted sections must stay deleted.
foreach (array_merge($controllers, [$admin . '/Core/Dispatcher.php']) as $file) {
    $source = (string)file_get_contents($file);
    if (preg_match_all("#'tpl/([A-Za-z_]+)\\.tpl'#", $source, $matches)) {
        foreach ($matches[1] as $name) {
            if (!is_file($admin . "/templates/$name.php")) {
                failAdminShell(basename($file) . " loads the missing admin template $name.");
            }
        }
    }
}
// PaymentsCtrl (provider keys, packages, orders) is the one maintained payment page.
foreach (glob($admin . '/Controllers/Payment*Ctrl.php') ?: [] as $file) {
    if (basename($file) !== 'PaymentsCtrl.php') {
        failAdminShell('The retired ' . basename($file) . ' must stay removed.');
    }
}
if (glob($admin . '/Controllers/InstallNewServer*Ctrl.php')) {
    failAdminShell('The InstallNewServer admin controllers must stay removed.');
}
if (glob($admin . '/templates/payment*.php')) {
    failAdminShell('The payment admin templates must stay removed.');
}

// Every menu entry must resolve to a controller (or a known non-admin page).
$dispatcher = (string)file_get_contents($admin . '/Core/Dispatcher.php');
if (preg_match('/payment(Settings|Locations|Products|Providers|Vouchers|Logs)|installNewServer/', $dispatcher)) {
    failAdminShell('The dispatcher must not link to the removed payment or installer sections.');
}
preg_match_all("#\\['admin\\.php\\?action=([A-Za-z]+)'#", $dispatcher, $actions);
foreach (array_diff(array_unique($actions[1]), ['logout']) as $action) {
    if (!is_file($admin . '/Controllers/' . ucfirst($action) . 'Ctrl.php')) {
        failAdminShell("The admin menu links to the missing controller $action.");
    }
}
// Gold voucher code generators stay: the in-game Vouchers tab redeems their codes.
foreach (['GoldPackageCodeGeneratorCtrl', 'GiftGoldPackageCodeGeneratorCtrl'] as $required) {
    if (!is_file($admin . "/Controllers/$required.php")) {
        failAdminShell("$required must stay available.");
    }
}

echo "Admin shell contract passed.\n";
