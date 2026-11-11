<?php

namespace Controller\Ajax\vacationMode;

use Controller\Ajax\AjaxBase;
use Core\Config;
use CoreSession;
use Game\Formulas;
use Model\OptionModel;
use resources\View\PHPBatchView;

final class confirmation extends AjaxBase
{
    public function dispatch(): void
    {
        $this->response['html'] = '';
        if (!$this->session->isValid()
            || $this->session->isInVacationMode()
            || (int)Config::getProperty('game', 'vacationDays') <= 0) {
            return;
        }

        $days = $_POST['params'] ?? null;
        if (!is_scalar($days) || !preg_match('/^[1-9][0-9]*$/', (string)$days)) {
            return;
        }
        $days = (int)$days;
        $remainingDays = Formulas::remainingVacationDays((int)$this->session->getUsedVacationDays());
        if ($days > $remainingDays) {
            return;
        }

        $model = new OptionModel();
        $conditions = $model->getVacationConditions(
            $this->session->getPlayerId(),
            $this->session->hasProtection()
        );
        if (array_sum($conditions) !== count($conditions)) {
            return;
        }

        $view = new PHPBatchView('ajax/vacationConfirmation');
        $view->vars['days'] = $days;
        $view->vars['checker'] = $this->session->getChecker();
        $this->response['html'] = $view->output();
    }
}
