<?php

declare(strict_types=1);

namespace Controller\Ajax\village;

use Controller\Ajax\AjaxBase;
use Core\Village;
use Game\StockBar;

/** Returns the resource bar of the current village: capacities, production, stock and tooltips. */
final class resources extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to refresh village resources.', 403);
            return;
        }

        $this->response += StockBar::snapshot(Village::getInstance());
    }
}
