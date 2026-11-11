<?php

declare(strict_types=1);

/**
 * Contract: Message detail shell matches landmarks
 * (toolList + archiveGoldclub, mark unread id=mark, Answer disabled button).
 */

function failMessagesDetailContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$showMsg = file_get_contents($root . '/game/include/resources/Templates/messages/showMsg.php');
$ctrl = file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
if ($showMsg === false || $ctrl === false) {
    failMessagesDetailContract('Unable to read message detail sources.');
}

foreach ([
    'class="toolList"',
    'id="archiveGoldclub"',
    'name="id[]"',
    'name="mark" id="mark"',
    "value=\"unread\"",
    'id="mark_script"',
    "GoldclubDialog({featureKey: 'messageArchive'",
    "formAction = match (true)",
] as $needle) {
    if (!str_contains($showMsg, $needle)) {
        failMessagesDetailContract('showMsg.php missing detail piece: ' . $needle);
    }
}

foreach ([
    'id="toggleState"',
    'id="deleteMessage"',
    'name="n1"',
    'action="/messages"',
] as $legacy) {
    if (str_contains($showMsg, $legacy)) {
        failMessagesDetailContract('showMsg.php must not keep legacy detail piece: ' . $legacy);
    }
}

foreach ([
    'postedMessageIds()',
    "(string) (\$_POST['mark'] ?? '') === 'unread'",
    "\$view->vars['goldClub'] = Session::getInstance()->hasGoldClub();",
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failMessagesDetailContract('MessagesCtrl missing detail handler piece: ' . $needle);
    }
}

fwrite(STDOUT, "Messages detail contract passed.\n");
