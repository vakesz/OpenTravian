<?php

declare(strict_types=1);

use Core\Helper\TimezoneHelper;
use Game\AdminUi;

/** @var array $params */
$body = '<p><a class="textButtonV2 buttonFramed rectangle withText grey" href="admin.php?action=reportedMessages"><div>Back to reports</div></a></p>'
    . '<table class="adminTable"><thead><tr><th colspan="2">Reported message</th></tr></thead><tbody>'
    . '<tr><td class="head">Player</td><td>' . ($params['player'] ?? '') . '</td></tr>'
    . '<tr><td class="head">Reason</td><td>' . AdminUi::esc((string) ($params['reason'] ?? '')) . '</td></tr>'
    . '<tr><td class="head">Time</td><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) ($params['time'] ?? 0), true)) . '</td></tr>'
    . '<tr><td colspan="2">' . ($params['message'] ?? '') . '</td></tr>'
    . '</tbody></table>';

echo AdminUi::page(
    'Reported message',
    'One spam report. The message body is shown as the player saw it.',
    $body
);
