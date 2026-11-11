<?php

declare(strict_types=1);

use Core\Session;
use Game\AdminUi;

/** @var array $params */
$rawError = trim((string) ($params['error'] ?? ''));
$notice = $rawError === ''
    ? ''
    : AdminUi::notice(
        $rawError,
        str_contains(strtolower($rawError), 'blacklisted') && !str_contains(strtolower($rawError), 'no ') ? 'success' : 'error'
    );
$total = (int) ($params['total'] ?? 0);
$checker = Session::getCheckerForUrl();

$rows = '';
$content = $params['content'] ?? null;
if ($total > 0 && is_object($content) && method_exists($content, 'fetch_assoc')) {
    $cells = [];
    while ($value = $content->fetch_assoc()) {
        $id = (int) ($value['id'] ?? 0);
        $email = AdminUi::esc((string) ($value['email'] ?? ''));
        $cells[] = '<td class="center"><a href="?action=blackListEmail&del=' . $id . '&' . $checker . '">Delete</a> '
            . $email . '</td>';
    }
    while (count($cells) % 2 !== 0) {
        $cells[] = '<td></td>';
    }
    for ($i = 0, $n = count($cells); $i < $n; $i += 2) {
        $rows .= '<tr>' . $cells[$i] . $cells[$i + 1] . '</tr>';
    }
} else {
    $rows = '<tr><td colspan="2" class="center adminMuted">No blacklisted emails.</td></tr>';
}

$body = $notice
    . '<form method="post" action="admin.php?action=blackListEmail">'
    . getCheckerInput()
    . AdminUi::textarea('emails', 'Emails (one per line)', (string) ($params['emails'] ?? ''), ['rows' => 12])
    . AdminUi::button('Save', 'green')
    . '</form>'
    . AdminUi::box(
        'Emails (' . $total . ')',
        '<table class="adminTable"><thead><tr><th>Email</th><th>Email</th></tr></thead><tbody>'
        . $rows
        . '</tbody></table>'
    );

echo AdminUi::page(
    'Email blacklist',
    'Addresses that registration and login must reject. Add several at once, one per line.',
    $body
);
