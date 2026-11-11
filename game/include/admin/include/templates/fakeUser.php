<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$result = $params['result'] ?? false;
$notice = $result
    ? AdminUi::notice((int) $result . ' fake users added.', 'success')
    : '';

$body = $notice
    . '<form method="post" action="admin.php?action=fakeUser">'
    . getCheckerInput()
    . '<input type="hidden" name="section" value="add">'
    . AdminUi::textarea(
        'names',
        'Names (comma-separated)',
        'ali, reza, bozi, khale, DeadMaster',
        ['rows' => 12],
        'Creates inactive filler accounts with those names.'
    )
    . AdminUi::confirmButton('Add fake users', 'Create fake accounts for every name in the list?', 'green')
    . '</form>';

echo AdminUi::page(
    'Fake users',
    'Seed the world with inactive accounts for testing density and rankings.',
    $body
);
