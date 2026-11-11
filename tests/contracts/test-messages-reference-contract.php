<?php

declare(strict_types=1);

// Messages behaviour the reference shows and the 2026-11-08 walk matched: the Archive tab opens for a player
// without the Gold club, every new player gets the Support welcome message, and Support is "TG Support".

$root = dirname(__DIR__, 2);

function expectMessagesReference(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = (string) file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
expectMessagesReference(!str_contains($controller, '$selectedTab == 3 && !Session::getInstance()->hasGoldClub()'), 'The Archive tab opens without the Gold club (it lists nothing, like the reference).');
expectMessagesReference(str_contains($controller, "T('Messages', 'TG Support')") && substr_count($controller, "T('Messages', 'TG Support')") >= 2, 'Support shows as TG Support in the list and in the message.');
expectMessagesReference(str_contains($controller, 'case MessageModel::AUTO_TYPE_WELCOME:'), 'The welcome message has its own auto type, so it cannot be answered.');

$model = (string) file_get_contents($root . '/game/include/Model/MessageModel.php');
expectMessagesReference(str_contains($model, 'sendMessage(0, $uid, \'welcome\', $name, self::AUTO_TYPE_WELCOME)'), 'The welcome message comes from Support.');
expectMessagesReference(str_contains((string) file_get_contents($root . '/game/include/Controller/ActivateCtrl.php'), '->sendWelcomeMessage($uid,'), 'Activation sends the welcome message.');

$english = (string) file_get_contents($root . '/game/include/resources/Translation/en-US.php');
expectMessagesReference(str_contains($english, "\$Definition['Messages']['needClub'] = 'For this feature you need the Gold club activated.';"), 'The Gold club tooltip has the reference wording.');
foreach (['Useful tips & information', 'This is your new home.', 'Consult the Taskmaster', 'Now go out there and show the world what you are made of.'] as $needle) {
    expectMessagesReference(str_contains($english, $needle), "The welcome text must keep the reference wording: $needle");
}

// An answer's subject counts like the reference's: RE:, RE^2:, RE^3: ... (a pure function, so it can be pinned here).
require_once $root . '/game/include/Model/MessageModel.php';
expectMessagesReference(\Model\MessageModel::answerSubject('Parity walk test') === 'RE: Parity walk test', 'The first answer is "RE: subject".');
expectMessagesReference(\Model\MessageModel::answerSubject('RE: Parity walk test') === 'RE^2: Parity walk test', 'The second answer is "RE^2: subject".');
expectMessagesReference(\Model\MessageModel::answerSubject('RE^7: Parity walk test') === 'RE^8: Parity walk test', 'Later answers count on.');
$show = (string) file_get_contents($root . '/game/include/resources/Templates/messages/showMsg.php');
expectMessagesReference(str_contains($show, "window.location.href = '/messages/write?id=") && str_contains($controller, "isset(\$_REQUEST['id'])") && str_contains($controller, "redirect('/messages/inbox')"), 'Answer opens /messages/write?id= and a sent message ends on the inbox, like the reference.');

echo "Messages reference contract passed.\n";
