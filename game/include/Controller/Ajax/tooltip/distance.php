<?php

namespace Controller\Ajax\tooltip;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Core\Village;
use Game\Formulas;

class distance extends AjaxBase
{
    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        $x = $_POST['x'] ?? null;
        $y = $_POST['y'] ?? null;
        if (!is_numeric($x) || !is_numeric($y)) {
            $this->error('Invalid coordinates.');
            return;
        }
        $from = Formulas::kid2xy(Village::getInstance()->getKid());
        $distance = Formulas::getDistance($from, ['x' => (int) $x, 'y' => (int) $y]);
        $this->response['title'] = null;
        $this->response['text'] = sprintf(T('HeroGlobal', 'distanceTooltip'), number_format($distance, 2, '.', ''));
        $this->response['success'] = true;
    }
}
