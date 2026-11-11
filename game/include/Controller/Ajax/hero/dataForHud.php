<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Game\Hero\HeroHud;

/** Top-bar hero HUD state (reference GET hero/dataForHUD). */
class dataForHud extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('Invalid session.', 403);
            return;
        }

        foreach (HeroHud::dataForHud() as $field => $value) {
            $this->response[$field] = $value;
        }
    }
}
