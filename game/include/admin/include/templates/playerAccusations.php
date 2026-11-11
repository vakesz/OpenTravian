<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$body = '<table class="adminTable"><thead><tr>'
    . '<th></th><th>Reporter</th><th>Reported player</th><th>Reason</th><th>Comments</th><th>Time</th><th>Action</th>'
    . '</tr></thead><tbody>'
    . (string) ($params['content'] ?? '')
    . '</tbody></table>';

echo AdminUi::page(
    'Player reports',
    'Reports filed from player profiles appear here, open ones first.',
    $body
);
