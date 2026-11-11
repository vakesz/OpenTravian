<?php

declare(strict_types=1);

namespace Controller\Ajax\player;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Model\PlayerAccusationModel;
use resources\View\PHPBatchView;

/** The profile's Report player dialog: "edit" returns the form, "save" files the report. */
class accuse extends AjaxBase
{
    public function dispatch(): void
    {
        $uid = is_scalar($_POST['uid'] ?? null) ? (int) $_POST['uid'] : 0;
        $reporter = (int) $this->session->getPlayerId();
        $model = new PlayerAccusationModel();
        if ($this->session->isSitter() || $uid <= 2 || $uid === $reporter
            || (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM users WHERE id=?', [$uid]) === 0) {
            $this->error(T('Profile', 'Report player'));
            return;
        }
        if ($this->session->banned()) {
            $this->error(T('Profile', 'reportPlayer_notAllowedWhenBanned'));
            return;
        }
        if ($model->remainingToday($reporter) === 0) {
            $this->error(T('Profile', 'reportPlayer_noReportsLeftForToday'));
            return;
        }

        if (($_POST['action'] ?? null) === 'save') {
            $reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
            $message = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
            if (!$model->file($reporter, $uid, $reason, $message)) {
                $this->error(T('Profile', 'reportPlayer_chooseReason'));
                return;
            }
            $view = new PHPBatchView('ajax/accusePlayerSuccess');
        } else {
            $view = new PHPBatchView('ajax/accusePlayer');
            $view->vars['uid'] = $uid;
            $view->vars['remaining'] = $model->remainingToday($reporter);
        }
        $this->response['html'] = $view->output();
    }
}
