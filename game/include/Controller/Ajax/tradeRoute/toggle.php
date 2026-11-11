<?php

namespace Controller\Ajax\tradeRoute;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Core\Village;
use Model\MarketModel;

class toggle extends AjaxBase
{
    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!$session->isValid()) {
            $this->error('You must be logged in to manage trade routes.');
            return;
        }

        if (!$session->hasGoldClub()) {
            $this->error('Trade routes require the Gold Club.');
            return;
        }

        if ($session->isInVacationMode()) {
            $this->error('Trade routes cannot be changed during vacation mode.');
            return;
        }

        $routeId = filter_var($_POST['routeId'] ?? null, FILTER_VALIDATE_INT);
        $enabled = $_POST['enabled'] ?? null;
        if ($routeId === false || $routeId < 1 || !in_array($enabled, ['0', '1'], true)) {
            $this->error('Invalid trade route request.');
            return;
        }

        $kid = Village::getInstance()->getKid();
        $market = new MarketModel();
        if ($market->getTradeRoute($kid, $routeId) === false) {
            $this->error('This trade route does not belong to the selected village.');
            return;
        }

        $market->toggleTradeRoute($kid, $routeId, (int)$enabled);
        $this->response['enabled'] = (int)$enabled;
    }
}
