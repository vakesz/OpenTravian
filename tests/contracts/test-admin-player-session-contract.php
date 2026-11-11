<?php

declare(strict_types=1);

function failAdminPlayerSessionContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$session = file_get_contents($root . '/game/include/Core/Session.php');
$dispatcher = file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');

if ($session === false || $dispatcher === false) {
    failAdminPlayerSessionContract('Unable to read the session and admin dispatcher sources.');
}

$checkAdminStart = strpos($session, 'private function checkAdmin()');
$checkAdminEnd = $checkAdminStart === false
    ? false
    : strpos($session, "\n    public function getPlayerId()", $checkAdminStart);
$checkAdmin = $checkAdminStart === false || $checkAdminEnd === false
    ? ''
    : substr($session, $checkAdminStart, $checkAdminEnd - $checkAdminStart);

if ($checkAdmin === '' || !str_contains($checkAdmin, "LOADED_PAGE,")) {
    failAdminPlayerSessionContract('The session must retain the canonical administrator route gate.');
}
if (str_contains($checkAdmin, "basename(\$_SERVER['PHP_SELF']) == 'admin.php'")
    || str_contains($checkAdmin, '->logout()')) {
    failAdminPlayerSessionContract('A regular player reaching /admin.php must be redirected without ending their player session.');
}
if (!str_contains($dispatcher, "if (!\$session->isValid() || !\$session->isAdmin())")
    || !str_contains($dispatcher, "WebService::redirect(\$session->isValid() ? '/dorf1.php' : '/');")) {
    failAdminPlayerSessionContract('The admin dispatcher must return an authenticated player to their village without requiring Session::logout().');
}

echo "Admin player-session contract passed.\n";
