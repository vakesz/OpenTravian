<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="5" class="center adminMuted">No multi-account matches.</td></tr>';
}

$body = '<table class="adminTable"><thead><tr>'
    . '<th colspan="5">Multi-account users</th>'
    . '</tr><tr>'
    . '<th>No.</th><th>Username</th><th>Related accounts</th><th>Priority</th><th>Actions</th>'
    . '</tr></thead><tbody>' . $listBody . '</tbody>'
    . '<tfoot><tr><td colspan="5" class="navi adminMuted">' . ($params['navigator'] ?? '') . '</td></tr></tfoot>'
    . '</table>';

echo AdminUi::page(
    'Multi-account users',
    'Accounts that share enough signals to look like multi-accounts, sorted by priority.',
    $body
);
