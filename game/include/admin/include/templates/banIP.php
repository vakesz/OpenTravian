<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$reasons = ['Pushing', 'Cheat', 'Hack', 'Bug', 'Bad Name', 'Multiaccount', 'Swearing', 'Insults', 'Another', 'Spam'];
$reasonOptions = array_combine($reasons, $reasons) ?: [];
$durationOptions = [];
foreach ([1, 2, 5, 10, 12] as $hours) {
    $durationOptions[(string) ($hours * 3600)] = $hours === 1 ? '1 hour' : $hours . ' hours';
}
foreach ([1, 2, 5, 10, 30, 50, 90] as $days) {
    $durationOptions[(string) ($days * 3600 * 24)] = $days === 1 ? '1 day' : $days . ' days';
}
$durationOptions['0'] = 'Forever';

$error = isset($params['error']) ? AdminUi::notice((string) $params['error'], 'error') : '';
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="5" class="center adminMuted">No IP bans.</td></tr>';
}

$body = $error
    . '<form method="post" action="admin.php?action=IPBan">'
    . getCheckerInput()
    . AdminUi::input('ip', 'IP address', (string) ($params['ip'] ?? ''), ['placeholder' => 'IPv4 or IPv6 address', 'required' => true])
    . AdminUi::select('reason', 'Reason', $reasonOptions, 'Pushing')
    . AdminUi::select('time', 'Duration', $durationOptions, (string) (3600))
    . AdminUi::button('Ban IP', 'green')
    . '</form>'
    . AdminUi::box(
        'Banned IPs',
        '<table class="adminTable"><thead><tr>'
        . '<th></th><th>IP</th><th>Location</th><th>Reason</th><th>Length (from/to)</th>'
        . '</tr></thead><tbody>' . $listBody . '</tbody>'
        . '<tfoot><tr><td colspan="5" class="navi adminMuted">' . ($params['navigator'] ?? '') . '</td></tr></tfoot>'
        . '</table>'
    );

echo AdminUi::page(
    'IP bans',
    'Block an address from signing in. Existing bans are listed below.',
    $body
);
