<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Game\Hero\HeroInventoryPayload;

/** Current hero item list for the inventory page (reference hero inventory query). */
class inventoryItems extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('Invalid session.', 403);
            return;
        }

        foreach (HeroInventoryPayload::build((int)$this->session->getPlayerId()) as $field => $value) {
            $this->response[$field] = $value;
        }
    }
}
