<?php

namespace resources\View;

use Core\Session;

class OutOfGameView
{
    /** @var array<mixed> */
    public $vars = [];
    /** @var bool */
    public $renderLoginBox = true;

    public function __construct()
    {
        $this->vars['ajaxToken'] = Session::getInstance()->getAjaxToken();
        $this->vars['_player_uuid'] = Session::getInstance()->getPlayerUUID();
        $this->vars['titleInHeader'] = '';
        $this->vars['bodyCssClass'] = '';
        $this->vars['contentCssClass'] = '';
        $this->vars['containerCssClass'] = 'contentPage';
        $this->vars['autoReload'] = true;
        $this->vars['colorBlind'] = false;
        $this->vars['heroBox'] = '';
        $this->vars['sidebarBeforeContent'] = '';
        $this->vars['sidebarAfterContent'] = '';
        $this->vars['showNavBar'] = false;
        $this->vars['showGuestNavBar'] = false;
        $this->vars['showHeaderBar'] = false;
        $this->vars['showStockbar'] = false;
        $this->vars['showCloseButton'] = false;
        $this->vars['headerBar'] = false;
        $this->vars['content'] = '';
    }

    public function output(): void
    {
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
        if ($this->renderLoginBox) {
            $this->renderSidebarBoxMenu();
        }
        $view = new PHPBatchView('layout/Layout');
        $view->vars = $this->vars;
        $view->display();
    }

    private function renderSidebarBoxMenu(): void
    {
        $view = new PHPBatchView("layout/sidebarBoxMenu");
        $view->vars = $this->vars;
        $this->vars['sidebarBeforeContent'] = $view->output();
    }

}
