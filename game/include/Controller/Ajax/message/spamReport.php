<?php

namespace Controller\Ajax\message;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Model\MessageModel;

final class spamReport extends AjaxBase
{
    private const REASONS = [
        'advertisement' => 1,
        'harassment' => 2,
        'gold' => 3,
        'misc' => 4,
    ];

    public function dispatch(): void
    {
        if (!$this->session->isValid()
            || !$this->session->checkSitterPermission(Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS)) {
            $this->error(T('Messages', 'You dont have permission here'), 403);
            return;
        }

        $messageId = $_POST['messageId'] ?? null;
        $reasonName = $_POST['spamReason'] ?? null;
        if (!is_scalar($messageId)
            || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$messageId)
            || !is_string($reasonName)
            || !isset(self::REASONS[$reasonName])) {
            $this->error('Invalid message report.');
            return;
        }

        $reported = (new MessageModel())->reportReceivedMessage(
            (int)$messageId,
            (int)$this->session->getPlayerId(),
            self::REASONS[$reasonName],
            $reasonName
        );
        if (!$reported) {
            $this->error('The message cannot be reported.', 409);
            return;
        }

        $this->response['reportingSuccessful'] = T('Messages', 'reportingSuccessful');
        $this->response['closeButtonText'] = T('Messages', 'closeButtonText');
    }
}
