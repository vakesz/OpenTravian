<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="5" class="center adminMuted">No deletions queued.</td></tr>';
}

$body = AdminUi::box(
    'Deleting queue',
    '<table class="adminTable"><thead><tr>'
    . '<th></th><th>Player</th><th>Remaining time</th><th>Delete at</th><th></th>'
    . '</tr></thead><tbody>' . $listBody . '</tbody>'
    . '<tfoot><tr><td colspan="5" class="navi adminMuted">' . ($params['navigator'] ?? '') . '</td></tr></tfoot>'
    . '</table>'
);

echo AdminUi::page(
    'Delete users',
    'Accounts waiting to be removed. Cancel a queue entry, or delete now when interruption is allowed.',
    $body
);
