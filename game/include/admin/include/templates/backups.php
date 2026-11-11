<?php

declare(strict_types=1);

use Core\Session;
use Game\AdminUi;

/** @var array $params */
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="5" class="center adminMuted">No backups yet.</td></tr>';
}
$notice = isset($params['error']) ? AdminUi::notice((string) $params['error'], 'error') : '';

$body = $notice
    . '<table class="adminTable"><thead><tr><th colspan="5">Backups</th></tr><tr>'
    . '<th></th><th>#</th><th>Time</th><th>Size</th><th></th>'
    . '</tr></thead><tbody>' . $listBody . '</tbody></table>'
    . '<p class="adminMuted">Total backup size: ' . AdminUi::esc((string) ($params['totalSize'] ?? '0')) . '</p>'
    . '<form action="?action=backups" method="post" class="adminInline">'
    . Session::getCheckerInput()
    . AdminUi::button('Backup now', 'green', ['name' => 'do_backup', 'value' => '1'])
    . ' '
    . AdminUi::confirmButton('Force unlock backup', 'Unlock only when no backup is running: unlocking during one can leave a partial backup. Continue?', 'grey', ['name' => 'force_unlock_backup', 'value' => '1'])
    . ' '
    . AdminUi::confirmButton('Force unlock restore', 'Unlock only when no restore is running: unlocking during one can leave the world half restored. Continue?', 'grey', ['name' => 'force_unlock_restore', 'value' => '1'])
    . '</form>';

echo AdminUi::page(
    'Backup - Restore',
    'Create a database backup, restore an older one, or unlock a stuck backup or restore job.',
    $body
);
