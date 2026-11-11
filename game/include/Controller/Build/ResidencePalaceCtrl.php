<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Controller\BuildCtrl;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Security\Password;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Game\Helpers\LoyaltyHelper;
use Game\TrainingHelper;
use Game\Hero\HeroHelper;
use Core\Locale;
use Model\Quest;
use Model\VillageModel;
use resources\View\PHPBatchView;

class ResidencePalaceCtrl extends AnyCtrl
{
    /** @var mixed */
    private $building_id;

    public function __construct(BuildCtrl $build)
    {
        parent::__construct();

        $buildingInfo = Village::getInstance()->getField($build->selectedBuildingIndex);
        $this->building_id = $buildingInfo['item_id'];
        $this->view = new PHPBatchView("build/building/ResidencePalaceNavigation");
        $this->view->vars['buildingIndex'] = $build->selectedBuildingIndex;
        $this->view->vars['gid'] = (int)$this->building_id;
        $this->view->vars['content'] = '';
        $tabs = ['Management', 'Train', 'CulturePoints', 'Loyalty', 'Expansion',];
        foreach ($tabs as $tab) {
            $this->view->vars[$tab] = get_button_id();
        }
        $this->view->vars['favor'] = Session::getInstance()->getStoredFavoriteTab("buildingExpansion");
        $this->view->vars['buildingLevel'] = $buildingInfo['level'];
        $this->view->vars['selectedTabId'] = isset($_REQUEST['s']) && $_REQUEST['s'] >= 0 && $_REQUEST['s'] <= 4 ? (int)$_REQUEST['s'] : (int)$this->view->vars['favor'];
        if ($this->view->vars['selectedTabId'] == 0 || $buildingInfo['level'] <= 0) {
            $this->view->vars['content'] .= $build->getBuildingContract();
            if ($this->building_id == 26 && $buildingInfo['level'] >= 1) {
                $view = new PHPBatchView("build/building/ResidenceChangeCapital");
                $view->vars['showForm'] = FALSE;
                $view->vars['error'] = '';
                if (isset($_REQUEST['change_capital']) && !Village::getInstance()->isCapital() && !Village::getInstance()->isWW()) {
                    if (isset($_POST['pw'])) {
                        if (Password::verify((string)$_POST['pw'], (string)$_SESSION[Session::getInstance()->fixSessionPrefix('pw')])) {
                            $view->vars['showForm'] = FALSE;
                            $villageModel = new VillageModel();
                            $villageModel->changeCapital(Session::getInstance()->getPlayerId(), Session::getInstance()->getKid());
                            Village::getInstance()->setCapital(1);
                        } else {
                            $view->vars['showForm'] = TRUE;
                            $view->vars['error'] = T("ResidencePalace", "wrongPass");
                        }
                    } else {
                        $view->vars['showForm'] = TRUE;
                    }
                }
                $view->vars['isCapital'] = Village::getInstance()->isCapital();
                $view->vars['isWW'] = Village::getInstance()->isWW();
                $view->vars['checker'] = Session::getInstance()->getChecker();
                $this->view->vars['content'] .= $view->output();
            }
        } else if ($this->view->vars['selectedTabId'] == 1) {
            $dispatcher = new TroopBuilding($build->selectedBuildingIndex);
            $this->view->vars['content'] .= $dispatcher->render();
        } else if ($this->view->vars['selectedTabId'] == 2) {
            $this->culturePoints();
        } else if ($this->view->vars['selectedTabId'] == 3) {
            $this->loyalty($build->selectedBuildingIndex);
        } else if ($this->view->vars['selectedTabId'] == 4) {
            $this->expansion();
        }
        // The tab strip sits above the building body, like the reference.
        $build->addContentBeforeBuildingBody($this->view->output());
    }

    /**
     * @return mixed
     */
    public function render()
    {
        return $this->view->vars['content'];
    }

    private function culturePoints(): void
    {
        Quest::getInstance()->setQuestBitwise('world', 11, 1);
        $view = new PHPBatchView("build/building/ResidenceCulturePoints");
        $db = DB::getInstance();
        $villages = $db->query("SELECT SUM(cp) AS `total_cp`, COUNT(kid) `total_village_count`  FROM vdata WHERE owner=" . Session::getInstance()->getPlayerId())->fetch_assoc();
        $view->vars['total_villages'] = $villages['total_village_count'];
        
        $currentCP = Session::getInstance()->getCP();
        $i = Formulas::countCPVillages($currentCP);
        $newVillageCP = Formulas::newVillageCP($i + 1);

        $helmet = $db->fetchScalar("SELECT helmet FROM inventory WHERE uid=" . Session::getInstance()->getPlayerId());
        $view->vars['max_village_count'] = $i;
        $view->vars['curCP'] = $currentCP;
        $view->vars['nextCP'] = $newVillageCP;
        $view->vars['activeVillageCP'] = Village::getInstance()->getCP();
        $view->vars['otherVillageCP'] = $villages['total_cp'] - Village::getInstance()->getCP();
        $view->vars['hasAlliance'] = Session::getInstance()->getAllianceId() > 0;
        $view->vars['allianceBonus'] = !$view->vars['hasAlliance'] ? 1 : AllianceBonus::getCulturePointProductionBonus(
            Session::getInstance()->getAllianceId(),
            Session::getInstance()->getAllianceJoinTime()
        );
        $helper = new HeroHelper();
        $view->vars['heroCP'] = $helper->calcMoreCulturePoints($helmet);
        $totalInSecond = ($view->vars['activeVillageCP'] + $view->vars['otherVillageCP'] + $view->vars['heroCP']) / 86400;
        $totalInSecond = min($totalInSecond, 1e8);
        $nextVillageAt = $totalInSecond == 0 ? null : (int) (time() + (($newVillageCP - $currentCP) / $totalInSecond));
        $view->vars['date'] = $nextVillageAt === null ? '?' : TimezoneHelper::date(TimezoneHelper::getDateFormat(), $nextVillageAt);
        $view->vars['time'] = $nextVillageAt === null ? '?' : TimezoneHelper::date(TimezoneHelper::getTimeFormat(false), $nextVillageAt);
        $view->vars['needCP'] = $newVillageCP - $currentCP;
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @param mixed $index
     */
    private function loyalty($index): void
    {
        //TODO: cache
        $view = new PHPBatchView("build/building/ResidenceLoyaltyTab");
        $view->vars['loyalty'] = round(Village::getInstance()->getLoyalty());
        $view->vars['villageId'] = (int) Village::getInstance()->getKid();
        $view->vars['allowLoyaltyDecrease'] = (int) DB::getInstance()->fetchScalar('SELECT allowLoyaltyDecrease FROM vdata WHERE kid=?', [$view->vars['villageId']], 0) === 1;
        $view->vars['content'] = NULL;
        $db = DB::getInstance();
        LoyaltyHelper::updateUserVillagesLoyalty(Session::getInstance()->getPlayerId());
        $villages = $db->query("SELECT kid, loyalty, pop, name FROM vdata WHERE owner=" . Session::getInstance()->getPlayerId());
        while ($row = $villages->fetch_assoc()) {
            $view->vars['content'] .= '<tr>';
            $view->vars['content'] .= '<th><a href="/build.php?id=' . $index . '&amp;s=3&amp;gid=' . $this->building_id . '&amp;newdid=' . $row['kid'] . '">' . $row['name'] . '</a></th>';
            $view->vars['content'] .= '<td>' . $row['pop'] . '</td>';
            if ($row['loyalty'] > 100) {
                $class = 'high';
            } else if ($row['loyalty'] <= 50 || $row['loyalty'] == 100) {
                $class = 'medium';
            } else {
                $class = 'low';
            }
            $view->vars['content'] .= '<td class="' . $class . '">&#8237;&#8237;' . round($row['loyalty']) . '&#8236;%&#8236;</td>';
            $view->vars['content'] .= '</tr>';
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function expansion(): void
    {
        $view = new PHPBatchView("build/building/ResidenceExpansion");
        $db = DB::getInstance();
        $race = (int) Session::getInstance()->getRace();
        $villages = $db->query("SELECT kid, pop, name, created FROM vdata WHERE expandedfrom=" . Village::getInstance()->getKid() . " ORDER BY created ASC")->fetch_all(MYSQLI_ASSOC);
        $direction = strtolower(getDirection());
        $slots = [];
        foreach (TrainingHelper::slotLevels((int) $this->building_id) as $i => $level) {
            $slot = ['number' => $i + 1, 'level' => $level, 'state' => $i === count($villages) ? 'free' : 'next', 'village' => ''];
            if (isset($villages[$i])) {
                $row = $villages[$i];
                $xy = Formulas::kid2xy($row['kid']);
                $slot['state'] = 'taken';
                $slot['village'] = '<a href="/karte.php?d=' . $row['kid'] . '">' . $row['name'] . '</a> '
                    . '<a href="/karte.php?x=' . $xy['x'] . '&amp;y=' . $xy['y'] . '">&#8237;<span class="coordinates coordinatesWrapper coordinatesAligned coordinates' . $direction . '"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>&#8236;</a> '
                    . TimezoneHelper::autoDate($row['created'], false);
            }
            $slots[] = $slot;
        }
        $view->vars['slots'] = $slots;
        $view->vars['gid'] = (int) $this->building_id;
        $view->vars['race'] = $race;
        $view->vars['settlerUnit'] = nrToUnitId(10, $race);
        $view->vars['chiefUnit'] = nrToUnitId(9, $race);
        $view->vars['settleCost'] = Formulas::newVillageCost();
        $this->view->vars['content'] .= $view->output();
    }
}
