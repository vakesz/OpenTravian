<?php

declare(strict_types=1);

namespace Controller\Ajax\alliance;

use Controller\Ajax\AjaxBase;
use Game\AllianceBonus\AllianceDonation;
use resources\View\PHPBatchView;

/** "alliance/bonus/form/gold": the "Your contribution will be tripled" dialog for the donation in the body. */
final class bonusFormGold extends AjaxBase
{
    public function dispatch(): void
    {
        $parameters = AllianceDonation::parametersFromRequest($this->body(), (int)$this->session->getKid());
        $resources = AllianceDonation::resourcesFor($parameters);
        if (!$this->session->isValid()
            || $this->session->getAllianceId() <= 0
            || AllianceDonation::typeFor($parameters['bid']) === null
            || $resources === null
            || array_sum($resources) <= 0) {
            $this->error(T('AllianceBonus', 'Invalid resources entered'));
            return;
        }

        $view = new PHPBatchView('alliance/bonuses/donateWithGold');
        $view->vars = ['resources' => $resources];
        $this->response['form'] = $view->output();
    }
}
