<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\TimezoneHelper;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

class InGameMaintenanceCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $message = T('inGame', 'MaintenanceWork');
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = $message;
        $this->view->vars['bodyCssClass'] = 'perspectiveResources publicStatus';
        $this->view->vars['contentCssClass'] = 'error_site publicStatus';
        $this->view->vars['headerBar'] = true;
        $this->view->vars['showTime'] = true;
        $this->view->vars['serverTime'] = TimezoneHelper::date('H:i:s');
        $this->view->vars['content'] = PHPBatchView::render('system/statusCard', [
            'tone' => 'warning',
            'heading' => $message,
            'message' => $message,
            'actionHref' => '',
            'actionLabel' => '',
        ]);
    }
}
