<?php

namespace Controller\Ajax\premium;

use Controller\Ajax\AjaxBase;
use resources\View\PHPBatchView;

final class productionBoost extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to view production bonuses.', 403);
            return;
        }

        $view = new PHPBatchView('ajax/productionBoost');
        $this->response['html'] = $view->output();
    }
}
