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

$total = (int) ($params['total'] ?? 0);
$listBody = (string) ($params['content'] ?? '');
if ($listBody === '') {
    $listBody = '<tr><td colspan="5" class="center adminMuted">No players are banned.</td></tr>';
}

$body = '<form method="post" action="admin.php?action=bannedList">'
    . getCheckerInput()
    . '<input type="hidden" name="section" value="addBan">'
    . AdminUi::input('uid', 'Player id', (string) ($params['uid'] ?? ''), ['inputmode' => 'numeric', 'required' => true])
    . AdminUi::select('reason', 'Reason', $reasonOptions, (string) ($params['reason'] ?? 'Pushing'))
    . AdminUi::select('time', 'Duration', $durationOptions, (string) ($params['time'] ?? '3600'))
    . AdminUi::button('Ban player', 'green')
    . '</form>'
    . AdminUi::box(
        'Banned players (' . $total . ')',
        '<table class="adminTable"><thead><tr>'
        . '<th>Username</th><th>Length (from/to)</th><th>Reason</th><th></th><th></th>'
        . '</tr></thead><tbody>' . $listBody . '</tbody>'
        . '<tfoot><tr><td colspan="5" class="navi adminMuted">' . ($params['navigator'] ?? '') . '</td></tr></tfoot>'
        . '</table>'
    );

echo AdminUi::page(
    'Ban and unban',
    'Ban a player by id, or remove an active ban from the list below.',
    $body
);
