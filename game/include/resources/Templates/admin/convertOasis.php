<?php

declare(strict_types=1);

use Game\AdminUi;
use Game\Formulas;

/** @var array $vars */
$typeOptions = [];
foreach (Formulas::$data['oases'] as $type => $arr) {
    $resources = [];
    foreach ($arr as $t => $v) {
        $resources[] = sprintf('%s: %s', [1 => 'Wood', 'Clay', 'Iron', 'Crop'][$t] ?? $t, 25 * $v);
    }
    $typeOptions[(string) $type] = implode(' | ', $resources);
}
$notices = '';
foreach (($vars['errors'] ?? []) as $err) {
    $notices .= AdminUi::notice((string) $err, 'error');
}
if (!empty($vars['success'])) {
    $notices .= AdminUi::notice('Oasis converted.', 'success');
}

$body = $notices
    . '<form method="post" action="admin.php?action=convertOasis">'
    . getCheckerInput()
    . AdminUi::input('kid', 'Kid', (string) ($vars['kid'] ?? ''), ['type' => 'number', 'required' => true])
    . AdminUi::select('type', 'Target oasis type', $typeOptions, (string) ($vars['type'] ?? ''))
    . AdminUi::button('Convert oasis', 'green')
    . '</form>';

echo AdminUi::page(
    'Convert oasis',
    'Change an oasis tile to another bonus type by kid.',
    $body
);
