<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/messages/note.php');

if ($controller === false || $template === false) {
    fwrite(STDERR, "Could not read the Notes sources.\n");
    exit(1);
}

if (!str_contains($controller, "WebService::isPost() && isset(\$_POST['notepad']) && Session::validateChecker()")
    || !str_contains($controller, "is_string(\$_POST['notepad']) ? strip_tags(\$_POST['notepad']) : ''")) {
    fwrite(STDERR, "Notes saves must validate the session checker and accept only scalar text.\n");
    exit(1);
}

if (!str_contains($template, '\\Core\\Session::getCheckerInput();')
    || !str_contains($template, "htmlspecialchars((string)\$vars['note'], ENT_QUOTES, 'UTF-8')")) {
    fwrite(STDERR, "The Notes form must submit a checker token and safely render saved text.\n");
    exit(1);
}

echo "Message Notes contract passed.\n";
