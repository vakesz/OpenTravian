<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$error = trim((string) ($params['error'] ?? ''));
$body = ($error !== '' ? AdminUi::notice($error, 'error') : '')
    . '<table class="adminTable">'
    . '<thead><tr>'
    . '<th></th><th>Reported player</th><th>Reason</th><th>Time</th><th>Action</th>'
    . '</tr></thead><tbody>'
    . (string) ($params['content'] ?? '')
    . '</tbody></table>';

echo AdminUi::page(
    'Reported messages',
    'Inbox spam reports filed by players. Open a reason to read the message, or delete the report.',
    $body
);
