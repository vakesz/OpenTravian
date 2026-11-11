<?php

declare(strict_types=1);

use Core\Session;
use Game\AdminUi;

/** @var array $params */
$rawError = trim((string) ($params['error'] ?? ''));
$error = $rawError === ''
    ? ''
    : AdminUi::notice($rawError, str_contains(strtolower($rawError), 'added') ? 'success' : 'error');
$total = (int) ($params['total'] ?? 0);
$content = is_array($params['content'] ?? null) ? $params['content'] : [];
$checker = Session::getCheckerForUrl();

$cells = [];
foreach ($content as $index => $value) {
    $url = trim((string) $value);
    if ($url === '') {
        continue;
    }
    $cells[] = '<td class="center"><a href="?action=filteredUrls&del=' . (int) $index . '&' . $checker . '">Delete</a> '
        . AdminUi::esc($url) . '</td>';
}
$shown = count($cells);
if ($cells === []) {
    $rows = '<tr><td colspan="3" class="center adminMuted">No filtered URLs.</td></tr>';
} else {
    while (count($cells) % 3 !== 0) {
        $cells[] = '<td></td>';
    }
    $rows = '';
    for ($i = 0, $n = count($cells); $i < $n; $i += 3) {
        $rows .= '<tr>' . $cells[$i] . $cells[$i + 1] . $cells[$i + 2] . '</tr>';
    }
}

$body = $error
    . '<form method="post" action="admin.php?action=filteredUrls">'
    . getCheckerInput()
    . AdminUi::textarea('urls', 'URLs (one per line, or comma-separated)', (string) ($params['urls'] ?? ''), ['rows' => 12])
    . AdminUi::button('Save', 'green')
    . '</form>'
    . AdminUi::box(
        'Filtered URLs (' . $shown . ')',
        '<table class="adminTable"><thead><tr><th>URL</th><th>URL</th><th>URL</th></tr></thead><tbody>'
        . $rows
        . '</tbody></table>'
    );

echo AdminUi::page(
    'Filtered URLs',
    'URLs stripped or blocked in player messages. Add several at once, one per line.',
    $body
);
