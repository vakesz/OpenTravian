<?php

use Core\Database\DB;
use Core\Helper\Notification;
use Core\Helper\WebService;
use Core\Session;
use Model\NatarsModel;

class UpgradeVillageCtrl
{
    /** @var mixed */
    private $db;

    public function __construct()
    {
        $this->db = DB::getInstance();
        if (is_string($_REQUEST['kid'] ?? null) && ctype_digit($_REQUEST['kid'])) {
            $this->show();
        } else {
            Dispatcher::getInstance()->appendContent(\Game\AdminUi::notice('Open a village from a player page to upgrade it.', 'info'));
        }
    }

    private function show(): void
    {
        $dispatcher = Dispatcher::getInstance();
        if (!getCustom('allowInterruptionInGame')) {
            $dispatcher->appendContent(\Game\AdminUi::notice('Live-world changes are locked. Allow them with the toggle in the footer to use this page.', 'error'));

            return;
        }
        $params = [];
        $params['kid'] = (int)$_REQUEST['kid'];
        $villageData = $this->db->run('SELECT * FROM vdata WHERE kid=?', [$params['kid']])->get_result();
        if (!$villageData->num_rows) {
            $dispatcher->appendContent(\Game\AdminUi::notice('Village does not exists!', 'error'));
            return;
        }
        $villageData = $villageData->fetch_assoc();
        if ($villageData['isWW']) {
            $dispatcher->appendContent(\Game\AdminUi::notice('WW Villages cannot be upgraded!', 'error'));
            return;
        }
        $player = $this->db->run('SELECT name FROM users WHERE id=?', [(int)$villageData['owner']])->get_result()->fetch_assoc();
        $params['playerId'] = $villageData['owner'];
        $params['playerName'] = $player['name'];
        $params['villageName'] = $villageData['name'];
        $params['pop'] = $villageData['pop'];
        if (!isServerFinished() && WebService::isPost() && Session::validateChecker()) {
            $targetPop = is_string($_POST['pop'] ?? null) ? abs((int)$_POST['pop']) : 0;
            if ($targetPop > $params['pop']) {
                (new NatarsModel())->VillageToPOP($params['kid'], $targetPop);
                WebService::redirect("admin.php?action=editVillage&kid={$params['kid']}");
                Notification::RealTimeNotify("Admin upgrade", "Village {$params['kid']} was upgraded.");
            }
        }
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/upgradeVillage.tpl')->getAsString());
    }
}