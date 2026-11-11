<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$logoutCtrl = file_get_contents($root . '/game/include/Controller/LogoutCtrl.php');
$authLogout = file_get_contents($root . '/game/include/Controller/AuthLogoutCtrl.php');
if (!is_string($logoutCtrl)
    || !is_string($authLogout)
    || !str_contains($logoutCtrl, 'http_response_code(404);')
    || !str_contains($logoutCtrl, "\$this->view->vars['bodyCssClass'] = 'login fatal_error';")
    || !str_contains($logoutCtrl, "T('notFound', 'title')")
    || str_contains($logoutCtrl, '->logout()')
    || !str_contains($authLogout, "\$this->redirect('/')")
    || !str_contains($authLogout, '$session->logout()')
    || file_exists($root . '/game/include/resources/Templates/system/logout.php')) {
    fwrite(STDERR, "Logout must keep /logout as the guest 404 shell, end the session at /auth/logout, and redirect to the world login without the obsolete re-login card.\n");
    exit(1);
}

echo "Logout re-login contract passed.\n";
