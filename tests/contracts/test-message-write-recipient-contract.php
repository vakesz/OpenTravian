<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include', FilesystemIterator::SKIP_DOTS));
foreach ($sources as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js'], true)) {
        continue;
    }
    // The one exception is the Answer button of an opened message: the reference sends it to /messages/write?id=<message id>.
    if (basename($file->getPathname()) !== 'showMsg.php' && str_contains((string) file_get_contents($file->getPathname()), 'messages/write?id=')) {
        fwrite(STDERR, 'The write route takes ?to= for a recipient; found messages/write?id= in ' . substr($file->getPathname(), strlen($root) + 1) . PHP_EOL);
        exit(1);
    }
}

$controller = (string) file_get_contents($root . '/game/include/Controller/MessagesCtrl.php');
if (!str_contains($controller, "\$recipientParam = \$_GET['to'] ?? null;")) {
    fwrite(STDERR, 'The compose recipient must come from ?to= only.' . PHP_EOL);
    exit(1);
}

$menu = (string) file_get_contents($root . '/game/include/Game/Admin/AdminMenu.php');
$panel = (string) file_get_contents($root . '/game/include/admin/include/Controllers/InGameMessageCtrl.php');
if (!str_contains($menu, 'admin.php?action=inGameMessage')
    || !str_contains($panel, 'id > 2 AND access<>2')
    || !str_contains($controller, "\$recipientParam == '[all]'")) {
    fwrite(STDERR, 'Mass messages are sent from the in-game message page, and ?to=[all] still prefills the compose form.' . PHP_EOL);
    exit(1);
}

echo "Message write recipient contract passed.\n";
