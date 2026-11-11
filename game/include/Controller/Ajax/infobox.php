<?php

namespace Controller\Ajax;

use Model\InfoBoxModel;

final class infobox extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to delete infobox messages.', 403);
            return;
        }

        $id = $_POST['id'] ?? null;
        if (!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$id)) {
            $this->error('Invalid infobox message.');
            return;
        }

        $model = new InfoBoxModel();
        if (!$model->setInfoBoxItemAsDeleted((int)$this->session->getPlayerId(), (int)$id)) {
            $this->error('This infobox message cannot be deleted.', 404);
        }
    }
}
