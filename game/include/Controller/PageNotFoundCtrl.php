<?php

declare(strict_types=1);

namespace Controller;

use resources\View\OutOfGameView;

class PageNotFoundCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        http_response_code(404);
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = T("notFound", "title");
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings login fatal_error';
        $this->view->vars['contentCssClass'] = 'login fatal_error';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['content'] = htmlspecialchars((string)T("notFound", "desc"), ENT_QUOTES, 'UTF-8');
        $this->view->renderLoginBox = false;
    }
}
