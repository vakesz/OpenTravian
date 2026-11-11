<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$error = (string) ($params['error'] ?? '');
$body = ($error !== '' ? AdminUi::notice($error, 'info') : '')
    . '<form method="post" action="admin.php?action=sendTestEmail">'
    . getCheckerInput()
    . AdminUi::input('email', 'Receiver', (string) ($params['email'] ?? ''), ['type' => 'email', 'required' => true])
    . AdminUi::input('subject', 'Subject', (string) ($params['subject'] ?? ''), ['required' => true])
    . AdminUi::textarea('message', 'Message (HTML)', (string) ($params['message'] ?? ''), ['rows' => 10, 'required' => true])
    . AdminUi::button('Send test', 'green')
    . '</form>';

echo AdminUi::page('Send test email', 'Queues one mail to the address below so you can check SMTP delivery.', $body);
