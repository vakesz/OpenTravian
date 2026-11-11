<?php

declare(strict_types=1);

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\Mailer;
use Core\Helper\WebService;
use Core\Session;
use resources\View\GameView;
use resources\View\PHPBatchView;

class ReferAFriendCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();

        $session = Session::getInstance();
        $selectedTab = isset($_GET['t']) && (string)$_GET['t'] === 'overview' ? 'overview' : 'invite';
        $inviteGold = (int)Config::getProperty('gold', 'invitePlayerGold');
        $villageReward = $inviteGold;
        $tenthVillageReward = $inviteGold * 2;
        // Reference ladder chrome: 2nd–9th at inviteGold, 10th at 2× → max = 10 × inviteGold.
        $maxReward = $inviteGold * 10;
        $refLimit = (int)Config::getAdvancedProperty('refLimit');
        $invitationOpen = !isServerFinished()
            && $refLimit > 0
            && !(bool)GlobalDB::getInstance()->fetchScalar(
                'SELECT registerClosed FROM gameServers WHERE id=' . (int)Config::getProperty('settings', 'worldUniqueId')
            );

        $inviteLink = WebService::getProtocol()
            . WebService::getJustDomain()
            . '/register?uc=' . getWorldId() . '_' . $session->getPlayerId();

        $inviteFlash = '';
        if (
            $selectedTab === 'invite'
            && $invitationOpen
            && WebService::isPost()
            && isset($_POST['inviteEmail'])
            && Session::claimAction($_POST['c'] ?? null)
        ) {
            $inviteFlash = $this->sendInviteEmail(
                $inviteLink,
                (string)($_POST['friendName'] ?? ''),
                (string)($_POST['friendEmail'] ?? ''),
                (string)($_POST['message'] ?? ''),
                !empty($_POST['sendCopy']),
                (string)$session->getName(),
                (string)$session->getEmail()
            );
        }

        $invitees = [];
        if ($selectedTab === 'overview') {
            $playerId = (int)$session->getPlayerId();
            $result = DB::getInstance()->query(
                'SELECT pr.uid, pr.rewardGiven, u.name, u.total_villages'
                . ' FROM player_references pr'
                . ' LEFT JOIN users u ON u.id = pr.uid'
                . ' WHERE pr.ref_uid = ' . $playerId
                . ' ORDER BY pr.id DESC'
            );
            while ($row = $result->fetch_assoc()) {
                $invitees[] = [
                    'uid' => (int)$row['uid'],
                    'name' => (string)($row['name'] ?? T('ReferAFriend', 'unknownPlayer')),
                    'villages' => (int)($row['total_villages'] ?? 0),
                    'rewardGiven' => (int)$row['rewardGiven'],
                ];
            }
        }

        $defaultMessage = T('ReferAFriend', 'defaultInviteMessage');
        $playerEmail = (string)$session->getEmail();

        $this->view = new GameView();
        // The reference renders the h1 inside #referAFriend; the template emits it.
        $this->view->vars['titleInHeader'] = '';
        // Reference body token is `referAFriend` (no village perspective shell).
        $this->view->vars['bodyCssClass'] = 'referAFriend';
        $this->view->vars['contentCssClass'] = 'referAFriend'
            . ($selectedTab === 'overview' ? ' referralOverview' : ' referAFriendInfo');
        $this->view->vars['content'] .= PHPBatchView::render('referAFriend/main', [
            'selectedTab' => $selectedTab,
            'inviteLink' => $inviteLink,
            'inviteGold' => $inviteGold,
            'villageReward' => $villageReward,
            'tenthVillageReward' => $tenthVillageReward,
            'maxReward' => $maxReward,
            'refLimit' => $refLimit,
            'invitationOpen' => $invitationOpen,
            'invitees' => $invitees,
            'checker' => (string)$session->getChecker(),
            'defaultMessage' => $defaultMessage,
            'emailDefaultMessage' => $defaultMessage,
            'playerEmail' => $playerEmail,
            'inviteFlash' => $inviteFlash,
        ]);
    }

    private function sendInviteEmail(
        string $inviteLink,
        string $friendName,
        string $friendEmail,
        string $message,
        bool $sendCopy,
        string $playerName,
        string $playerEmail
    ): string {
        $friendName = trim(strip_tags($friendName));
        $friendEmail = trim($friendEmail);
        $message = trim(strip_tags($message));

        if ($friendName === '' || $friendEmail === '' || $message === '') {
            return T('ReferAFriend', 'inviteFailed');
        }

        if (filter_var($friendEmail, FILTER_VALIDATE_EMAIL) === false) {
            return T('ReferAFriend', 'friendEmailInvalid');
        }

        if (mb_strlen($message) > 2000) {
            return T('ReferAFriend', 'messageTooLong');
        }

        $subject = sprintf(T('ReferAFriend', 'emailSubject'), $playerName);
        $body = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
            . '<br><br>'
            . htmlspecialchars(T('ReferAFriend', 'emailLinkLabel'), ENT_QUOTES, 'UTF-8')
            . ': <a href="' . htmlspecialchars($inviteLink, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($inviteLink, ENT_QUOTES, 'UTF-8')
            . '</a>';

        Mailer::sendEmail($friendEmail, $subject, $body);

        if ($sendCopy && filter_var($playerEmail, FILTER_VALIDATE_EMAIL) !== false) {
            Mailer::sendEmail($playerEmail, $subject, $body);
        }

        return sprintf(T('ReferAFriend', 'inviteSent'), $friendName);
    }
}
