<?php

declare(strict_types=1);

use Game\Admin\AdminMenu;

// Every control panel page opens with a title, and the actions that destroy or replace data ask first.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Admin/AdminMenu.php';

function expectPageFrame(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expectPageFrame(AdminMenu::titleFor('backups') === 'Backups' && AdminMenu::titleFor('IPBan') === 'IP bans' && AdminMenu::titleFor('ipban') === 'IP bans', 'A title is the label of the menu entry, whatever the case of the action.');
expectPageFrame(AdminMenu::titleFor('main') === 'Control panel home' && AdminMenu::titleFor('editPlayer') === 'Edit player' && AdminMenu::titleFor('nothingHere') === null, 'Contextual pages have titles, unknown ones none.');
foreach (AdminMenu::definition(['staff' => true, 'gameWorldUrl' => '/', 'preregistration' => true, 'logoutToken' => 't']) as $group) {
    foreach ($group['items'] as [$url, $label]) {
        if (preg_match('~^admin\.php\?action=([A-Za-z0-9]+)$~', $url, $match) === 1 && $match[1] !== 'logout') {
            expectPageFrame(AdminMenu::titleFor($match[1]) === $label, "No title for $url.");
        }
    }
}

$dispatcher = (string) file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
expectPageFrame(str_contains($dispatcher, "stripos((string) \$this->data['content'], '<h1') === false") && str_contains($dispatcher, 'AdminUi::page($title'), 'The dispatcher adds the title when a controller printed none.');

$backups = (string) file_get_contents($root . '/game/include/admin/include/Controllers/BackupsCtrl.php') . (string) file_get_contents($root . '/game/include/admin/include/templates/backups.php');
foreach (['del_backup', 'restore_backup', 'force_unlock_backup', 'force_unlock_restore'] as $name) {
    expectPageFrame(preg_match("~confirmButton\\([^;]*'name' => '$name'~", $backups) === 1, "The $name button must ask before it submits.");
    expectPageFrame(!str_contains($backups, "name=\"$name\""), "The $name button must come from AdminUi::confirmButton.");
}

echo "Admin page frame contract passed.\n";
