<?php

namespace Controller\Ajax\message;

use Controller\Ajax\AjaxBase;
use Core\Config;
use Core\Session;
use Model\AllianceModel;
use Model\MessageModel;

use function getDisplay;

final class checkRecipient extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()
            || !$this->session->checkSitterPermission(Session::SITTER_CAN_SEND_OR_ANSWER_MESSAGES)) {
            $this->error(T('Messages', 'You dont have permission here'), 403);
            return;
        }

        $input = $_POST['recipients'] ?? null;
        if (!is_array($input)) {
            $this->error('Invalid message recipient.');
            return;
        }

        $recipients = [];
        foreach ($input as $recipient) {
            if (!is_scalar($recipient)) {
                $this->error('Invalid message recipient.');
                return;
            }
            $recipient = trim((string)$recipient);
            if ($recipient !== '') {
                $recipients[$recipient] = true;
            }
        }
        $recipients = array_keys($recipients);
        $maximumRecipients = (int)getDisplay('maximumMultiMessageSendNum');
        if (count($recipients) > $maximumRecipients) {
            $this->error(sprintf(T('Messages', 'You cannot send a message to more than %s users'), $maximumRecipients));
            return;
        }

        $minimumPopulation = (int)Config::getAdvancedProperty('mimMessagePop');
        if ($this->session->isAdmin()) {
            $minimumPopulation = -1;
        }
        $message = new MessageModel();
        foreach ($recipients as $recipient) {
            if ($recipient === '[ally]') {
                if ($this->session->getAllianceId() <= 0
                    || !$this->session->hasAlliancePermission(AllianceModel::IGM_MESSAGE)) {
                    $this->error(T('Messages', 'You dont have permission here'), 403);
                    return;
                }
                continue;
            }
            if ($recipient === '[all]') {
                if (!$this->session->isAdmin() || $this->session->isAdminInAnotherAccount()) {
                    $this->error(T('Messages', 'You dont have permission here'), 403);
                    return;
                }
                continue;
            }

            $recipientId = $message->getPlayerIdByName($recipient);
            if ($recipientId === false) {
                $this->error(sprintf(T('Messages', 'player x does not exists'), $recipient), 404);
                return;
            }
            if ($recipientId > 2 && $this->session->getTotalPopulation() < $minimumPopulation) {
                $this->error(
                    sprintf(
                        T('Messages', 'Spam protection: You must have at least %s pop to be able to send messages'),
                        $minimumPopulation
                    ),
                    403
                );
                return;
            }
            if ($this->session->banned() && $recipientId > 2) {
                $this->error(T('Messages', 'You dont have permission here'), 403);
                return;
            }
        }
    }
}
