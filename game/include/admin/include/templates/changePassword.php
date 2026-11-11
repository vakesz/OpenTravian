<?php

declare(strict_types=1);

use Core\Session;
use Game\AdminUi;

/** @var array $params */
$body = '<form method="post" action="admin.php" class="adminInline">'
    . Session::getCheckerInput()
    . '<input type="hidden" name="action" value="changePassword">'
    . AdminUi::confirmButton('Change password', 'Replace the signed-in account password with a new random one?', 'green')
    . '</form>';
if (!empty($params['error'])) {
    $body .= AdminUi::notice((string) $params['error'], 'info');
}

echo AdminUi::page(
    'Change password',
    'Generates a new random password for the signed-in Multihunter or administrator account and shows it once.',
    $body
);
