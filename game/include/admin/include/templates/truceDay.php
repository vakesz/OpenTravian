<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$reasonOptions = [];
foreach (T('Truce', 'reasons') as $key => $value) {
    $reasonOptions[(string) $key] = (string) $value;
}
$notice = isset($params['result']) ? AdminUi::notice((string) $params['result'], 'success') : '';

$body = $notice
    . AdminUi::notice('Current server time: ' . (string) ($params['now'] ?? ''), 'info')
    . '<form method="post" action="admin.php?action=truce">'
    . getCheckerInput()
    . AdminUi::input('truceFrom', 'Truce from', (string) ($params['truceFrom'] ?? ''), [], 'Local date and time.')
    . AdminUi::input('truceTo', 'Truce to', (string) ($params['truceTo'] ?? ''), [], 'Local date and time.')
    . AdminUi::select('truceReasonId', 'Reason', $reasonOptions, (string) ($params['truceReasonId'] ?? ''))
    . AdminUi::button('Save truce', 'green')
    . '</form>';

echo AdminUi::page(
    'Truce',
    'Schedule a world-wide truce window and the reason shown to players.',
    $body
);
