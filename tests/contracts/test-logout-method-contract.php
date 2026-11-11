<?php

declare(strict_types=1);

function failLogoutMethod(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$logoutCtrl = (string)file_get_contents($root . '/game/include/Controller/LogoutCtrl.php');
if (str_contains($logoutCtrl, '->logout()')
    || !str_contains($logoutCtrl, 'http_response_code(404);')
    || !str_contains($logoutCtrl, "T('notFound', 'title')")) {
    failLogoutMethod('/logout must stay the reference 404 shell and must never end a session.');
}

$authLogout = (string)file_get_contents($root . '/game/include/Controller/AuthLogoutCtrl.php');
if (!str_contains($authLogout, "Session::matchesLogoutToken(\$_GET['c'] ?? null)")
    || !str_contains($authLogout, '->logout()')
    || !str_contains($authLogout, "\$this->redirect('/')")
    || strpos($authLogout, 'matchesLogoutToken') > strpos($authLogout, '->logout()')) {
    failLogoutMethod('/auth/logout must end the session only with the per-session token, then redirect to the world login.');
}

$session = (string)file_get_contents($root . '/game/include/Core/Session.php');
foreach ([
    'function getLogoutToken',
    'function logoutUrl',
    'function matchesLogoutToken',
    'hash_equals(self::getLogoutToken()',
    'RememberMe::revoke()',
    "'/auth/logout?c='",
] as $needle) {
    if (!str_contains($session, $needle)) {
        failLogoutMethod("Session is missing: $needle");
    }
}

$mainInclude = (string)file_get_contents($root . '/game/include/mainInclude.php');
if (!str_contains($mainInclude, "\$requestPathForSupport === '/auth/logout'")
    || !str_contains($mainInclude, "'auth_logout' => 'AuthLogoutCtrl'")) {
    failLogoutMethod('mainInclude must dispatch /auth/logout to AuthLogoutCtrl.');
}

foreach (['Layout.php', 'outOfGame.php'] as $file) {
    $tpl = (string)file_get_contents($root . '/game/include/resources/Templates/layout/' . $file);
    if (str_contains($tpl, 'href="/logout"') || !str_contains($tpl, 'Session::logoutUrl()')) {
        failLogoutMethod("$file must build the logout href with Session::logoutUrl().");
    }
    $offset = 0;
    while (($pos = strpos($tpl, 'Session::logoutUrl()', $offset)) !== false) {
        if (!str_contains(substr($tpl, $pos, 300), 'rel="nofollow"')) {
            failLogoutMethod("$file logout links need rel=\"nofollow\".");
        }
        $offset = $pos + 1;
    }
}

$adminDispatcher = (string)file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
$adminLogoutStart = strpos($adminDispatcher, "if (\$handler == 'logout' && \$session->isValid() && \$session->isAdmin())");
$adminLogoutToken = $adminLogoutStart === false
    ? false
    : strpos($adminDispatcher, "Session::matchesLogoutToken(\$_GET['c'] ?? null)", $adminLogoutStart);
$adminLogoutCall = $adminLogoutStart === false
    ? false
    : strpos($adminDispatcher, '$session->logout()', $adminLogoutStart);
if ($adminLogoutStart === false
    || $adminLogoutToken === false
    || $adminLogoutCall === false
    || $adminLogoutToken > $adminLogoutCall
    || !str_contains($adminDispatcher, "WebService::redirect('/admin.php')")
    || !str_contains((string)file_get_contents($root . '/game/include/Game/Admin/AdminMenu.php'), "['admin.php?action=logout&c=' . rawurlencode(\$context['logoutToken']), 'Log out'")) {
    failLogoutMethod('Admin logout must be an authenticated, tokenized action rather than a GET logout trap.');
}

echo "Logout method contract passed.\n";
