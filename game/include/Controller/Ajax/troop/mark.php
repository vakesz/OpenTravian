<?php

declare(strict_types=1);

namespace Controller\Ajax\troop;

use Controller\Ajax\AjaxBase;
use Core\Village;
use Model\MovementsModel;

/** Persists the marker of an incoming attack: POST troop/<movement id>/mark {state}. */
final class mark extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to mark incoming attacks.', 403);
            return;
        }

        $id = $_GET['id'] ?? null;
        $state = $_POST['state'] ?? null;
        if (!is_scalar($id)
            || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$id)
            || !is_scalar($state)
            || !in_array((string)$state, ['0', '1', '2', '3'], true)) {
            $this->error('Invalid incoming-attack marker.');
            return;
        }

        $movements = new MovementsModel();
        $kid = (int)Village::getInstance()->getKid();
        if (!$movements->setMovementMarkState($kid, (int)$id, (int)$state)) {
            $this->error('The incoming attack is no longer available.', 404);
            return;
        }

        $this->response['incomingAttacks'] = $movements->countIncomingAttacks($kid);
    }
}
