<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\TimezoneHelper;
use resources\View\OutOfGameView;

/**
 * Reference `/logout` is a 404 route. Real logout is `/auth/logout` (see AuthLogoutCtrl).
 * This controller never ends a session.
 */
class LogoutCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        http_response_code(404);
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = T('notFound', 'title');
        $this->view->vars['bodyCssClass'] = 'login fatal_error';
        $this->view->vars['contentCssClass'] = 'login fatal_error';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['content'] = htmlspecialchars((string) T('notFound', 'desc'), ENT_QUOTES, 'UTF-8');
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showTime'] = true;
        $this->view->vars['serverTime'] = TimezoneHelper::date('H:i:s');
        $this->view->renderLoginBox = true;
    }
}
