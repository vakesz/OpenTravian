<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\TimezoneHelper;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

class PermissionDeniedCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        http_response_code(403);
        $heading = T('403Error', 'You cannot pass!');
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = $heading;
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings publicStatus';
        $this->view->vars['contentCssClass'] = 'error_site publicStatus';
        $this->view->vars['content'] = PHPBatchView::render('system/statusCard', [
            'tone' => 'error',
            'heading' => $heading,
            'message' => T('403Error', 'I am a servant of the Secret Order, wielder of the flame of 403, You cannot pass'),
            'actionHref' => '/',
            'actionLabel' => 'Back to login',
        ]);
        $this->view->vars['headerBar'] = false;
        $this->view->vars['showTime'] = true;
        $this->view->vars['serverTime'] = TimezoneHelper::date('H:i:s');
        $this->view->renderLoginBox = false;
    }
}
