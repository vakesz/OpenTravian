<?php

declare(strict_types=1);

namespace Game;

use Core\Database\DB;
use Core\Session;
use Model\AllianceModel;
use Model\ArtefactsModel;
use Model\MessageModel;
use Model\PlayerAccusationModel;
use resources\View\PHPBatchView;

/**
 * The icon buttons and the notice under another player's profile details.
 * Ajax handlers that change what the buttons allow (ignore, invite) answer
 * with a fresh render so the page never shows stale state.
 */
final class ProfileActions
{
    private function __construct()
    {
    }

    /**
     * @return array{result: string, notification: string}
     */
    public static function render(int $target): array
    {
        $session = Session::getInstance();
        $viewer = (int) $session->getPlayerId();
        $messages = new MessageModel();
        $ignored = $target > 2 && (bool) $messages->isPlayerIgnored($viewer, $target);
        $invite = self::invite($target);

        $view = new PHPBatchView('profile/actionButtons');
        $view->vars['uid'] = $target;
        $view->vars['ignored'] = $ignored;
        // Ignoring is offered for regular players only, and not once the ignore list is full.
        $view->vars['canIgnore'] = $target > 2 && ($ignored || $messages->checkIgnoreListLimit($viewer));
        $view->vars['canAccuse'] = $target > 2 && !$session->isSitter();
        $view->vars['accuseDisabled'] = $session->banned() || (new PlayerAccusationModel())->remainingToday($viewer) === 0;
        $view->vars['canInvite'] = $invite['allowed'];
        $view->vars['inviteDisabled'] = $invite['disabled'];

        $notification = '';
        if ($ignored) {
            $notification .= '<p><span class="notice">' . T('Profile', 'Player will be ignored') . '</span></p>';
        } elseif ($target > 2 && !$messages->checkIgnoreListLimit($viewer)) {
            $notification .= '<p><span class="warning">' . T('Profile', 'Ignore list is full') . '</span>'
                . '<a href="/messages/ignore"> ' . T('Profile', 'Edit list') . '</a></p>';
        }
        if ($invite['allowed'] && $invite['invited']) {
            $name = (string) DB::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$target]);
            $notification .= '<p class="notice">' . sprintf(
                T('Alliance', 'sentInvitation'),
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                '<a href="/alliance/options/invite">',
                '</a>'
            ) . '</p>';
        }

        return ['result' => $view->output(), 'notification' => $notification];
    }

    /**
     * Whether the viewer may invite the target, and the state of the invite button.
     *
     * @return array{allowed: bool, disabled: bool, invited: bool}
     */
    public static function invite(int $target): array
    {
        $session = Session::getInstance();
        $aid = (int) $session->getAllianceId();
        $allowed = $target > 2
            && $aid > 0
            && !$session->isSitter()
            && (bool) $session->hasAlliancePermission(AllianceModel::INVITE_PLAYER);
        if (!$allowed) {
            return ['allowed' => false, 'disabled' => false, 'invited' => false];
        }
        $db = DB::getInstance();
        $invited = (int) $db->fetchScalar('SELECT COUNT(id) FROM ali_invite WHERE aid=? AND uid=?', [$aid, $target]) > 0;
        $inAlliance = (int) $db->fetchScalar('SELECT aid FROM users WHERE id=?', [$target]) > 0;
        $locked = (bool) getGame('disallowAllianceInviteAfterWWRelease') && ArtefactsModel::wwPlansReleased();

        return ['allowed' => true, 'disabled' => $invited || $inAlliance || $locked, 'invited' => $invited];
    }
}
