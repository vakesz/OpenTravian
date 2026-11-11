<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Database\GlobalDB;
use Core\Session;
use resources\View\PHPBatchView;

use function getWorldUniqueId;

/** Order history dialog: the player's own checkouts in this world, newest first. */
class paymentHistory extends AjaxBase
{
    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!$session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            $this->error(T('PaymentWizard', 'paymentUnAvailable'));

            return;
        }
        $statement = GlobalDB::getInstance()->run(
            'SELECT created, gold, amountMinor, currency, status FROM paymentOrder WHERE uid=? AND worldUniqueId=? ORDER BY id DESC LIMIT 50',
            [(int) $session->getPlayerId(), (int) getWorldUniqueId()]
        );
        $view = new PHPBatchView('payment/history');
        $view->vars['orders'] = $statement ? $statement->get_result()->fetch_all(MYSQLI_ASSOC) : [];
        $this->response['functionToCall'] = 'renderDialog';
        $this->response['options'] = [
            'dialogOptions' => ['saveOnUnload' => false, 'buttonOk' => false, 'version' => 2, 'cssClass' => 'scrollable transactionHistory', 'context' => 'transactionHistory'],
            'html' => $view->output(),
        ];
    }
}
