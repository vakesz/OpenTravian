<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $vars */
$notice = '';
if (isset($vars['success'])) {
    $notice = AdminUi::notice((string) $vars['success'], 'success');
} elseif (isset($vars['error'])) {
    $notice = AdminUi::notice((string) $vars['error'], 'error');
}

$forms = '<ul class="cleanup">'
    . '<li><form method="post" action="admin.php?action=Cleanup" class="adminInline">'
    . getCheckerInput()
    . '<input type="hidden" name="do" value="deleteReportsXDays">'
    . 'Remove reports older than '
    . '<input type="number" min="1" name="days" required aria-label="Days">'
    . ' days '
    . AdminUi::confirmButton('Delete', 'Delete reports older than this many days?', 'green')
    . '</form></li>'
    . '<li><form method="post" action="admin.php?action=Cleanup" class="adminInline">'
    . getCheckerInput()
    . '<input type="hidden" name="do" value="deleteGreenReportsXDays">'
    . 'Remove green reports older than '
    . '<input type="number" min="1" name="days" required aria-label="Days">'
    . ' days '
    . AdminUi::confirmButton('Delete', 'Delete green reports older than this many days?', 'green')
    . '</form></li>'
    . '</ul>';

echo AdminUi::page(
    'Cleanup',
    'Delete old battle reports to keep the database small. Green reports are wins without losses.',
    $notice . $forms
);
