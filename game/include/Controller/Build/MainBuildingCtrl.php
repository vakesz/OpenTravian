<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\GoldHelper;
use resources\View\PHPBatchView;

class MainBuildingCtrl extends AnyCtrl
{
    private function demolitionAction(): void
    {
        $village = Village::getInstance();
        if (isset($_GET['del']) && $village->getOnDemolishBuildingFieldId() > 0) {
            $demolish = $village->getDemolishTask();
            if ((int)$demolish['id'] === (int)$_GET['del']) {
                $db = DB::getInstance();
                $db->query("DELETE FROM demolition WHERE id=" . (int)$_GET['del']);
                $village->setDemolishTask(NULL);
            }
        } else if ($village->getOnDemolishBuildingFieldId() === 0) {
            if (strtolower($_SERVER['REQUEST_METHOD']) != 'post') {
                return;
            }
            if (Session::getInstance()->banned()) {
                $this->innerRedirect("InGameBannedPage");
            } else if (Config::getInstance()->dynamic->serverFinished) {
                $this->innerRedirect("InGameWinnerPage");
            } else if (($_POST['action'] ?? null) === 'demolishBuilding' && Session::claimAction($_POST['c'] ?? null) && isset($_POST['abriss'])) {
                $village->demolishBuilding((int)$_POST['abriss']);
            }
        }
    }

    /** Level at which the Main Building offers demolition (rearranging needs no minimum). */
    private const DEMOLISH_MIN_LEVEL = 10;

    public function __construct(int $level = 0)
    {
        parent::__construct();

        $village = Village::getInstance();
        $session = Session::getInstance();
        $helper = new GoldHelper();
        $this->view = new PHPBatchView("build/mainBuilding");
        $this->view->vars['canDemolish'] = $level >= self::DEMOLISH_MIN_LEVEL;
        $this->view->vars['canRearrange'] = $level >= 1 && !$village->isWW();
        if (!$this->view->vars['canDemolish']) {
            return;
        }
        $this->demolitionAction();
        if ($village->getOnDemolishBuildingFieldId() > 0) {
            $this->view->vars['isDemolishing'] = TRUE;
            $this->view->vars['finishNow'] = $helper->finishNowButton();
            $demolish = $village->getDemolishTask();
            $this->view->vars['demolish']['taskId'] = $demolish['id'];
            $this->view->vars['demolish']['itemId'] = $village->getField($demolish['building_field'])['item_id'];
            $this->view->vars['demolish']['level'] = $demolish['complete'] ? 0 : $village->getField($demolish['building_field'])['level'] - 1;
            $this->view->vars['demolish']['timer'] = appendTimer($demolish['end_time'] - time());
            $this->view->vars['demolish']['endat'] = TimezoneHelper::date("H:i", $demolish['end_time']);
        } else {
            $this->view->vars['isDemolishing'] = FALSE;
            $this->view->vars['checker'] = $session->getChecker();
            $this->view->vars['options'] = [];
            for ($i = 19; $i <= 40; ++$i) {
                if (!$village->getField($i)['item_id'] || !$village->getField($i)['level']) {
                    continue;
                }
                $this->view->vars['options'][$i] = [
                    "itemId" => $village->getField($i)['item_id'],
                    "level"  => $village->getField($i)['level'],
                ];
            }
            $this->view->vars['demolishNow'] = $helper->getCompleteDemolishButton();
        }
    }
}
