<?php

use Core\Database\DB;
use Core\Helper\Notification;
use Core\Helper\WebService;
use Core\Session;
use Game\Formulas;
use Model\MessageModel;

class GiftsCtrl
{
    private const MAX_GOLD_GRANT = 100000000;

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        $allowInterruption = (bool)getCustom('allowInterruptionInGame');
        // Local operators can opt into gift-gold grants without enabling the
        // rest of the state-changing admin tools.
        $allowGoldGrants = $allowInterruption || (bool)getCustom('allowAdminGoldGrants');
        $section = isset($_POST['section']) ? (string)$_POST['section'] : '';
        $params = [
            'error'             => '',
            'giftUidGold'       => isset($_POST['giftUidGold']) ? (int)$_POST['giftUidGold'] : 0,
            'giftUid'           => isset($_POST['giftUid']) ? (int)$_POST['giftUid'] : 0,
            'resourcesEnabled'  => $allowInterruption,
            'goldGrantsEnabled' => $allowGoldGrants,
        ];

        if (WebService::isPost()) {
            if (isServerFinished()) {
                $params['error'] = 'The game has finished; gifts are unavailable.';
            } else if (!Session::validateChecker()) {
                $params['error'] = 'This form has expired. Refresh the page and try again.';
            } else if ($section === 'giftResources' && isset($_POST['fullResources'])) {
                if (!$allowInterruption) {
                    $params['error'] = 'Resource gifts are disabled for this world.';
                } else {
                    $db = DB::getInstance();
                    $max = Formulas::storeCAP(20) * 2;
                    $db->query("UPDATE vdata SET wood=wood+$max, clay=clay+$max, iron=iron+$max, crop=crop+$max");
                    $params['error'] = 'Resources were filled for every village.';
                    AdminLog::getInstance()->addLog('Filled resources for every village.');
                }
            } else if ($section === 'giftResources') {
                if (!$allowInterruption) {
                    $params['error'] = 'Resource gifts are disabled for this world.';
                } else {
                    $wood = max(0, (int)($_POST['wood'] ?? 0));
                    $clay = max(0, (int)($_POST['clay'] ?? 0));
                    $iron = max(0, (int)($_POST['iron'] ?? 0));
                    $crop = max(0, (int)($_POST['crop'] ?? 0));
                    $db = DB::getInstance();
                    $db->query("UPDATE vdata SET wood=wood+$wood, clay=clay+$clay, iron=iron+$iron, crop=crop+$crop");
                    $params['error'] = 'Resources were gifted to all players.';
                    AdminLog::getInstance()->addLog('Gifted resources to players.[' . implode(',', [$wood, $clay, $iron, $crop]) . ']');
                }
            } else if ($section === 'giftUidGold') {
                $this->giftGoldToPlayer($params, $allowGoldGrants);
            } else if ($section === 'giftGold') {
                $this->giftGoldToPlayers($params, $allowGoldGrants);
            } else {
                $params['error'] = 'Unknown gift action.';
            }
        }

        if (!$allowInterruption && !$allowGoldGrants && !WebService::isPost()) {
            $params['error'] = 'Manual gifts are disabled for this world.';
        }
        $dispatcher->appendContent(\resources\View\PHPBatchView::render('admin/gifts', $params));
    }

    /**
     * @param array<mixed> $params
     */
    private function giftGoldToPlayer(array &$params, bool $allowGoldGrants): void
    {
        if (!$allowGoldGrants) {
            $params['error'] = 'Manual gold grants are disabled for this world.';
            return;
        }
        $amount = $params['giftUidGold'];
        $uid = $params['giftUid'];
        if ($uid < 1 || $amount < 1 || $amount > self::MAX_GOLD_GRANT) {
            $params['error'] = 'Enter a valid recipient and a gold amount between 1 and ' . self::MAX_GOLD_GRANT . '.';
            return;
        }

        $db = DB::getInstance();
        $db->query("UPDATE users SET gift_gold=gift_gold+$amount WHERE id=$uid");
        if (!$db->affectedRows()) {
            $params['error'] = 'The selected player does not exist.';
            return;
        }

        $name = (string)$db->fetchScalar("SELECT name FROM users WHERE id=$uid");
        $message = new MessageModel();
        $message->sendMessage(0, $uid, null, $amount, 4);
        AdminLog::getInstance()->addLog("Granted $amount gift gold to player $uid.");
        Notification::RealTimeNotify('Manual Gold', sprintf('%s gold was granted to %s.', $amount, $name));
        $params['error'] = sprintf('%s gold was granted to player %s and a message was sent.', $amount, $name);
    }

    /**
     * @param array<mixed> $params
     */
    private function giftGoldToPlayers(array &$params, bool $allowGoldGrants): void
    {
        if (!$allowGoldGrants) {
            $params['error'] = 'Manual gold grants are disabled for this world.';
            return;
        }
        $amount = (int)($_POST['give_gold'] ?? 0);
        if ($amount < 1 || $amount > self::MAX_GOLD_GRANT) {
            $params['error'] = 'Enter a gold amount between 1 and ' . self::MAX_GOLD_GRANT . '.';
            return;
        }

        $db = DB::getInstance();
        $db->query("UPDATE users SET gift_gold=gift_gold+$amount WHERE id>2 AND access<>2");
        $users = $db->query('SELECT id FROM users WHERE id>2 AND access<>2');
        $message = new MessageModel();
        while ($row = $users->fetch_assoc()) {
            $message->sendMessage(0, (int)$row['id'], null, $amount, 4);
        }
        $params['error'] = $amount . ' gold was granted to all playable users.';
        AdminLog::getInstance()->addLog($params['error']);
        Notification::RealTimeNotify('Gift Gold', sprintf('%s gold was granted to all playable users.', $amount));
    }
}
