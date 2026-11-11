<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$id = $params['id'] ?? '';
$idValue = $id === null || $id === '' ? '' : (string) (int) $id;
$body = ($params['result'] ?? false)
    ? AdminUi::notice('Messages deleted and the player was banned for spam.', 'success')
    : '';
$body .= '<form method="post" action="admin.php?action=deleteAllMessages">'
    . getCheckerInput()
    . AdminUi::input('id', 'Player id', $idValue, ['inputmode' => 'numeric', 'required' => true], 'Deletes every message for that account and bans them for two hours.')
    . AdminUi::confirmButton(
        'Delete messages and ban',
        'Delete every message for this player and ban them for spam?',
        'green'
    )
    . '</form>';

echo AdminUi::page(
    'Delete player messages',
    'Wipe one player\'s inbox and outbox, clear their spam reports, and ban them for spam.',
    $body
);
