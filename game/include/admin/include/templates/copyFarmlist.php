<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$notice = !empty($params['result'])
    ? AdminUi::notice('Farm list copied.', 'success')
    : '';

$body = $notice
    . '<form method="post" action="admin.php?action=copyFarmlist">'
    . getCheckerInput()
    . AdminUi::input(
        'kid',
        'Village kid',
        (string) ($params['kid'] ?? ''),
        ['inputmode' => 'numeric', 'required' => true],
        'Copies the Multihunter farm list into that village.'
    )
    . AdminUi::button('Copy farm list', 'green')
    . '</form>';

echo AdminUi::page(
    'Copy farm list',
    'Paste the Multihunter farm list into a player village by kid.',
    $body
);
