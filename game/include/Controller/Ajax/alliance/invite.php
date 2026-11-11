<?php

declare(strict_types=1);

namespace Controller\Ajax\alliance;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\ProfileActions;
use Model\AllianceModel;

/** Invites the player of the open profile into the viewer's alliance (the profile's Invite button). */
class invite extends AjaxBase
{
    public function dispatch(): void
    {
        $target = is_scalar($_POST['playerId'] ?? null) ? (int) $_POST['playerId'] : 0;
        $state = ProfileActions::invite($target);
        if (!$state['allowed'] || $state['disabled']) {
            $this->error(T('Alliance', 'Kicking/inviting is not allowed at this time'));
            return;
        }

        $result = (new AllianceModel())->sendInvite((int) $this->session->getPlayerId(), (int) $this->session->getAllianceId(), $target);
        if ($result === 1 || $result === -1) {
            $name = (string) DB::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$target]);
            $this->error(sprintf(T('Alliance', $result === 1 ? 'test has already received an invitation' : 'The player x does`t exists'), $name));
            return;
        }

        $actions = ProfileActions::render($target);
        $this->response['result'] = $actions['result'];
        $this->response['notification'] = $actions['notification'];
    }
}
