<?php

declare(strict_types=1);

namespace Controller\Ajax\alliance;

use Controller\Ajax\AjaxBase;
use Core\Village;
use Game\AllianceBonus\AllianceDonation;
use Game\Formulas;
use Model\AllianceBonusModel;
use resources\View\PHPBatchView;

/** "alliance/bonus/form/scale-down": the dialog that shrinks a donation to what the daily limit still allows. Body adds {gold}. */
final class bonusFormScaleDown extends AjaxBase
{
    public function dispatch(): void
    {
        $parameters = AllianceDonation::parametersFromRequest($this->body(), (int)$this->session->getKid());
        $resources = AllianceDonation::resourcesFor($parameters);
        $aid = $this->session->getAllianceId();
        if (!$this->session->isValid()
            || $aid <= 0
            || AllianceDonation::typeFor($parameters['bid']) === null
            || $resources === null) {
            $this->error(T('AllianceBonus', 'Invalid resources entered'));
            return;
        }

        $model = new AllianceBonusModel();
        $limit = Formulas::getAllianceBonusDonationLimit($model->getMaxAllianceBonusLevel($aid));
        $village = Village::getInstance();
        $view = new PHPBatchView('alliance/bonuses/scaleDown');
        $view->vars = [
            'resources' => $resources,
            'gold' => !empty($this->body()['gold']),
            'untilLimit' => max(0, $limit - $this->session->getAllianceContribution()),
            'available' => [
                (int)$village->getCurrentResources(0),
                (int)$village->getCurrentResources(1),
                (int)$village->getCurrentResources(2),
                (int)$village->getCurrentResources(3),
            ],
        ];
        $this->response['form'] = $view->output();
    }
}
