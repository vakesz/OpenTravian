<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="4" class="center adminMuted">No pending activations.</td></tr>';
}

$body = '<table class="adminTable"><thead><tr>'
    . '<th colspan="4">Players not activated (' . (int) ($params['total'] ?? 0) . ')</th>'
    . '</tr><tr>'
    . '<th>#</th><th>Username</th><th>Email</th><th>Activation link</th>'
    . '</tr></thead><tbody>' . $listBody . '</tbody></table>';

echo AdminUi::page(
    'Activation list',
    'Accounts that registered but have not finished activation yet.',
    $body
);
