<?php

declare(strict_types=1);

use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Game\AdminUi;
use Model\MessageModel;

/**
 * Compose an in-game message to every playable account (the panel stand-in for
 * messages/write?to=[all], which left the control panel shell).
 */
class InGameMessageCtrl
{
    private const MAX_SUBJECT = 45;
    private const MAX_MESSAGE = 10000;

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (!getCustom('allowInterruptionInGame')) {
            $dispatcher->appendContent(
                AdminUi::page(
                    'In-game message',
                    'Send a Multihunter message into every playable inbox.',
                    AdminUi::notice('Live-world changes are locked. Allow them with the toggle in the footer to use this page.', 'error')
                )
            );

            return;
        }

        $subject = is_string($_POST['subject'] ?? null) ? trim($_POST['subject']) : '';
        $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        $notice = '';
        if (WebService::isPost()) {
            $notice = $this->send($subject, $message);
        }

        $count = (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM users WHERE id > 2 AND access<>2');
        $body = $notice
            . '<form method="post" action="admin.php?action=inGameMessage">' . getCheckerInput()
            . AdminUi::input('subject', 'Subject', $subject, ['maxlength' => (string) self::MAX_SUBJECT, 'required' => true])
            . AdminUi::textarea('message', 'Message', $message, ['rows' => 12, 'required' => true, 'maxlength' => (string) self::MAX_MESSAGE])
            . AdminUi::confirmButton('Send to all players', 'Send this message to ' . $count . ' players?', 'green')
            . '</form>';

        $dispatcher->appendContent(
            AdminUi::page(
                'In-game message',
                'Delivers one Multihunter inbox message to each playable account on this world (' . $count . ' right now). This is not the newsletter and not the public banner.',
                $body
            )
        );
    }

    private function send(string $subject, string $message): string
    {
        if (isServerFinished()) {
            return AdminUi::notice('The server has finished.', 'error');
        }
        if ($subject === '' || $message === '') {
            return AdminUi::notice('Subject and message are required.', 'error');
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT || mb_strlen($message) > self::MAX_MESSAGE) {
            return AdminUi::notice('Subject or message is too long.', 'error');
        }

        $session = Session::getInstance();
        if (!$session->isAdmin() || $session->isAdminInAnotherAccount()) {
            return AdminUi::notice('Only a Multihunter session can send to all players.', 'error');
        }

        $from = (int) $session->getPlayerId();
        $model = new MessageModel();
        $users = DB::getInstance()->query('SELECT id FROM users WHERE id > 2 AND access<>2');
        $sent = 0;
        while ($row = $users->fetch_assoc()) {
            $model->sendMessage($from, (int) $row['id'], $subject, $message);
            $sent++;
        }
        AdminLog::getInstance()->addLog('Sent an in-game message to ' . $sent . ' players.');

        return AdminUi::notice('Message sent to ' . $sent . ' players.', 'success');
    }
}
