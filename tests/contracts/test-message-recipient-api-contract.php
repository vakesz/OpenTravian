<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/message/checkRecipient.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/messages/write.php');

if ($controller === false
    || !str_contains($controller, 'SITTER_CAN_SEND_OR_ANSWER_MESSAGES')
    || !str_contains($controller, 'maximumMultiMessageSendNum')
    || !str_contains($controller, 'getPlayerIdByName')
    || !str_contains($controller, "recipient === '[ally]'")) {
    fwrite(STDERR, "Message recipient validation must enforce permission, recipient limits, and recipient existence.\n");
    exit(1);
}

if ($template === false
    || !str_contains($template, "Travian.api('profile-languages/recipients'")
    || !preg_match('/error:\s*function\s*\(\)\s*\{\s*jQuery\([\'\"]#languages #playerLanguages[\'\"]\)\.empty\(\);/s', $template)) {
    fwrite(STDERR, "Message language badges must be cleared when the recipient lookup fails.\n");
    exit(1);
}

if (!preg_match('/data:\s*\{\s*recipients:\s*recipients\s*\}/s', $template)
    || str_contains($template, 'sender: 6109')) {
    fwrite(STDERR, "Message language lookups must only send the current recipient list.\n");
    exit(1);
}

if (!preg_match('/<form\s+method="post"\s+id="messageForm"\s+action="\/messages\/write">/', $template)
    || !str_contains($template, "var form = jQuery('#messageForm');")
    || !str_contains($template, 'Travian.Game.Messages.checkRecipients(recepients, form);')) {
    fwrite(STDERR, "Message recipient validation must bind to the compose form.\n");
    exit(1);
}

echo "Message recipient API contract passed.\n";
