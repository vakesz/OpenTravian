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
    $word = trim((string) $value);
    if ($word === '') {
        continue;
    }
    $cells[] = '<td class="center"><a href="?action=badWords&del=' . (int) $index . '&' . $checker . '">Delete</a> '
        . AdminUi::esc($word) . '</td>';
}
$shown = count($cells);
if ($cells === []) {
    $rows = '<tr><td colspan="3" class="center adminMuted">No bad words.</td></tr>';
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
    . '<form method="post" action="admin.php?action=badWords">'
    . getCheckerInput()
    . AdminUi::textarea('words', 'Words (one per line, or comma-separated)', (string) ($params['words'] ?? ''), ['rows' => 12])
    . AdminUi::button('Save', 'green')
    . '</form>'
    . AdminUi::box(
        'Bad words (' . $shown . ')',
        '<table class="adminTable"><thead><tr><th>Word</th><th>Word</th><th>Word</th></tr></thead><tbody>'
        . $rows
        . '</tbody></table>'
    );

echo AdminUi::page(
    'Bad words',
    'Blocked words filtered from player chat and messages. Add several at once, one per line.',
    $body
);
