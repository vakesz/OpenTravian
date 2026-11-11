<?php

namespace Controller;

use Controller\Build\AcademyCtrl;
use Controller\Build\BreweryCtrl;
use Controller\Build\EmbassyCtrl;
use Controller\Build\HeroMansionCtrl;
use Controller\Build\HospitalCtrl;
use Controller\Build\MainBuildingCtrl;
use Controller\Build\MarketPlaceCtrl;
use Controller\Build\ResidencePalaceCtrl;
use Controller\Build\SmithyCtrl;
use Controller\Build\TownhallCtrl;
use Controller\Build\TreasuryCtrl;
use Controller\Build\HarborCtrl;
use Controller\Build\TroopBuilding;
use Controller\RallyPoint\RallyPointCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\StringChecker;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\ExtraModules;
use Game\Formulas;
use Game\GoldHelper;
use Model\ArtefactsModel;
use resources\View\GameView;
use resources\View\PHPBatchView;
use Game\Buildings\BuildingAction;

use function number_format_x;

class BuildCtrl extends GameCtrl
{
    /** @var mixed */
    public $selectedBuildingIndex = -1;
    /** @var GameView */
    public $view;
    /** @var \resources\View\PHPBatchView */
    private $buildView;

    public function addContentBeforeBuildingBody(string $content): void
    {
        $this->buildView->vars['beforeBuildContent'] .= $content;
    }

    public function __construct()
    {
        parent::__construct();
        $village = Village::getInstance();
        if (isset($_REQUEST['id']) && (($_REQUEST['id'] > 0 && $_REQUEST['id'] <= 40) || $_REQUEST['id'] == 99)) {
            $this->selectedBuildingIndex = (int)$_REQUEST['id'];
        } elseif (isset($_REQUEST['gid'])) {
            // `gid` is descriptive in modern map URLs.  The field id remains
            // authoritative, while gid-only legacy links still resolve.
            $this->selectedBuildingIndex = $village->findBuildingByGid((int)$_REQUEST['gid']);
        }
        if ($village->isWW() && in_array($this->selectedBuildingIndex, [21, 26, 30, 31, 32,])) {
            $this->selectedBuildingIndex = 99;
        }
        if ($this->selectedBuildingIndex <= 0) {
            $this->redirect("/dorf2.php");
        }
        $field = $village->getField($this->selectedBuildingIndex);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['id']) && !isset($_GET['gid']) && (int)$field['item_id'] > 0) {
            // The reference canonicalises a slot URL by appending the building's gid.
            WebService::redirect('/build.php?' . http_build_query($_GET + ['gid' => (int)$field['item_id']]));
        }
        $this->view = new GameView();
        $this->view->vars['bodyCssClass'] = $this->selectedBuildingIndex <= 18 || $this->selectedBuildingIndex == 39 ? "build perspectiveResources" : "build perspectiveBuildings";
        // The native V2 GPack scopes the building viewport through
        // `#content.build`.  Keeping the class only on <body> leaves the
        // actual page surface at the generic content width, which makes the
        // building header, Rally Point, and their tables stretch and overlap.
        // Reference content token is `build` (not the contentPage wrapper class).
        $this->view->vars['contentCssClass'] = 'build';
        if ((int)$field['item_id'] === 15) {
            $this->view->vars['contentCssClass'] .= ' buildMainBuilding';
        }
        $this->view->newVillagePrefix['id'] = $this->selectedBuildingIndex;
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;

        if ($field['item_id']) {
            $this->view->vars['manualBuildingId'] = (int)$field['item_id'];
            if (in_array((int)$field['item_id'], [17, 19], true)) {
                // The reference offers contextual help on the Marketplace and Barracks; here it opens the building manual.
                $this->view->vars['contextualHelpBuildingId'] = (int)$field['item_id'];
            }
            $this->view->vars['titleInHeader'] = T("Buildings", $field['item_id'] . '.title') . ' <span class="level">' . T("Buildings", "level") . ' ' . $field['level'] . '</span>';
            if ($field['item_id'] > 4) {
                $this->view->newVillagePrefix['gid'] = $field['item_id'];
            }
        } else {
            $this->view->vars['titleInHeader'] = T("Buildings", "constructNewBuilding");
        }
        $this->buildView = new PHPBatchView("build/main");
        $this->buildView->vars['titleInHeader'] = $this->view->vars['titleInHeader'];
        $this->buildView->vars['selectedBuildingId'] = $field['item_id'];
        $this->buildView->vars['selectedBuildingLevel'] = $field['level'];
        $this->buildView->vars['beforeBuildContent'] = '';
        $this->buildView->vars['content'] = '';
        if (!$field['item_id']) {
            $this->procNewBuilding();
        } else {
            $this->procBuilding();
        }
        $this->view->vars['content'] = $this->buildView->output();

        if (Session::getInstance()->isAdmin()) {
            if (isset($_REQUEST['new_building_level'])) {
                $newLevel = (int)$_REQUEST['new_building_level'];
                if ($newLevel > $field['level']) {
                    // upgrade
                    BuildingAction::upgrade($village->getKid(), $this->selectedBuildingIndex, $newLevel - $field['level']);
                } else {
                    // downgrade
                    if ($newLevel == 0) {
                        BuildingAction::downgrade($village->getKid(), $this->selectedBuildingIndex, $field['level'] - $newLevel, true);
                    } else {
                        BuildingAction::downgrade($village->getKid(), $this->selectedBuildingIndex, $field['level'] - $newLevel);
                    }
                }
                WebService::redirect('/build.php?id=' . $this->selectedBuildingIndex);
            }
            $this->view->vars['content'] .= PHPBatchView::render('build/admin_level_set', [
                'id' => $this->selectedBuildingIndex,
                'level' => $field['level']
            ]);
        }
    }

    private function procNewBuilding(): void
    {
        $village = Village::getInstance();
        $category = isset($_GET['category']) ? (int)$_GET['category'] : -1;
        $noSoon = false;
        if ($this->selectedBuildingIndex == 39 || $this->selectedBuildingIndex == 40) {
            $category = 2;
            $noSoon = true;
        } elseif (in_array($this->selectedBuildingIndex, [21, 26, 31, 31, 32, 99,]) && $village->isWW()) {
            $category = 1;
            $noSoon = true;
        }
        if (!in_array($category, [1, 2, 3, -1])) {
            $category = -1;
        }
        if ($category < 0) {
            $category = 1;
            do {
                if (sizeof($this->getCategoryAvailableBuildings($category)['available'])) {
                    break;
                }
                $category++;
            } while ($category < 3);
        }
        $newBuilds = $this->getCategoryAvailableBuildings($category);
        $view = new PHPBatchView("build/newBuildingMenu");
        $view->vars = ["selectedId" => $this->selectedBuildingIndex, "activeTab" => $category, "InfrastructureTab" => ["id" => get_button_id(), "text" => T("Buildings", "newBuilding.Infrastructure"),], "MilitaryTab" => ["id" => get_button_id(), "text" => T("Buildings", "newBuilding.Military"),], "ResourcesTab" => ["id" => get_button_id(), "text" => T("Buildings", "newBuilding.Resources"),],];
        $content = $view->output();
        foreach ($newBuilds['available'] as $item_id => $build) {
            if ($village->isWW() && $item_id <> 40 && in_array($this->selectedBuildingIndex, [21, 26, 30, 31, 32, 99,])) {
                continue;
            } elseif ($this->selectedBuildingIndex == 39 && $item_id <> 16) {
                continue;
            } elseif ($this->selectedBuildingIndex == 40 && !in_array($item_id, Formulas::WALL_BUILDINGS)) {
                continue;
            }
            $content .= $this->getBuildingWrapper($item_id, $build);
        }
        if (sizeof($newBuilds['available']) === 0) {
            $content .= '<div class="none">';
            $content .= T("Buildings", "no_building_available");
            $content .= "</div>";
        }
        if (!$noSoon && !in_array($this->selectedBuildingIndex, [39, 40,]) && !($village->isWW() && in_array($this->selectedBuildingIndex, [21, 26, 31, 31, 32, 99,]))) {
            $x = sizeof($newBuilds['soon']);
            if ($x) {
                $content .= '<h4 class="round spacer">' . T("Buildings", "soon_available") . '</h4>';
                $content .= '<div id="build_list_soon" class="build">';
                foreach ($newBuilds['soon'] as $item_id => $build) {
                    $content .= $this->getBuildingWrapper($item_id, $build, true);
                }
                $content .= '</div>';
            }
        }
        $this->buildView->vars['content'] = $content;
    }

    /**
     * @param int $category
     * @return array<string, array<int, mixed>>
     */
    private function getCategoryAvailableBuildings($category)
    {
        $categories = [1 =>
            [15, 23, 10, 11, 18, 17, 25, 26, 44, 45, 34, 27, 24, 28, 35, 41, 39, 38, 40,],
            [16, 31, 32, 33, 42, 43, 47, 50, 19, 36, 37, 22, 13, 20, 29, 30, 21, 46, 14, 48],
            [8, 5, 6, 7, 9],
        ];
        $newBuilds = ['available' => [], 'soon' => []];
        $village = Village::getInstance();
        foreach ($categories[$category] as $item_id) {
            $canBuild = $village->canCreateNewBuild($item_id);
            // Lists artefact-gated buildings under the unmet ones instead of hiding them.
            $artifactBlocked = $village->checkArtifactDependencies($item_id) <> 0;
            $build = Formulas::$data['buildings'][$item_id - 1];
            if ($canBuild != -1) {
                if ($canBuild && !$artifactBlocked) {
                    $newBuilds['available'][$item_id] = [];
                    $newBuilds['available'][$item_id] = $build;
                    continue;
                }
                $dependencyCount = 0;
                if (isset($build['breq'])) {
                    foreach ($build['breq'] as $reqId => $reqValue) {
                        if ($reqValue != -1) {
                            $build['pre_requests_dependencyCount'][$reqId] = $reqValue - max($village->getTypeLevel($reqId));
                            $dependencyCount += $build['pre_requests_dependencyCount'][$reqId];
                        }
                    }
                }
                if (!isset($newBuilds['soon'][$dependencyCount])) {
                    $newBuilds['soon'][$item_id] = [];
                }
                $newBuilds['soon'][$item_id] = $build;
            }
        }
        return $newBuilds;
    }

    /**
     * @param mixed $item_id
     * @param mixed $build
     * @param bool $soon
     * @return mixed
     */
    private function getBuildingWrapper($item_id, $build, $soon = false)
    {
        $village = Village::getInstance();
        $goldHelper = new GoldHelper();
        $data = [
            "count" => 0,
            "itemId" => $item_id,
            "cost" => $build['cost'],
            "freeCrop" => $build['cu'],
            "npcButton" => $soon ? null : $goldHelper->getExchangeResourcesButtonByCost($build['cost']),
            "timeInString" => secondsToString(Formulas::buildingUpgradeTime($item_id, 1, $village->getMainBuildingLvl(), $village->isWW())),
            'action' => $soon ? null : $this->getActionText($item_id),
            'benefits' => $this->getNewBuildingBenefits($item_id),
            'soon' => $soon,
            'soonConditions' => '',
        ];
        $multi = Formulas::$data['buildings'][$item_id - 1];
        if (isset($multi['req']) && isset($multi['req']['multi']) && $multi['req']['multi'] == true) {
            switch ($item_id) {
                case 36:
                    $data['count'] = sizeof($village->getTrapperLvls()) + 1;
                    break;
                case 23:
                    $data['count'] = sizeof($village->getCrannyLvls()) + 1;
                    break;
                default:
                    for ($i = 19; $i <= 38; ++$i) {
                        if ($village->getField($i)['item_id'] == $item_id) {
                            ++$data['count'];
                        }
                    }
                    $data['count']++;
                    break;
            }
        }
        if ($soon) {
            // Lists the unmet requirements directly beneath the card;
            // it does not add the legacy "Prerequisites" heading here.
            $conditions = [];
            foreach ($build['breq'] as $reqId => $reqValue) {
                $currentLevel = max($village->getTypeLevel($reqId));
                $unmet = $reqValue - $currentLevel > 0;
                $name = T("Buildings", $reqId . '.title');
                $condition = '<span class="buildingCondition ' . ($unmet ? 'error' : '') . '">'
                    . '<a onclick="Travian.Game.Manual.open(\'building\', ' . (int)$reqId . ');">'
                    . ($reqValue == -1 ? '<strike>' . $name . '</strike>' : $name) . '</a>';
                if ($reqValue != -1) {
                    $title = $unmet
                        ? sprintf(T('Buildings', 'currentLevelTitle'), '&#8237;' . $currentLevel . '&#8236;')
                        : T('Buildings', 'prerequisiteFulfilled');
                    $condition .= ' <span title="' . $title . '">' . T("Buildings", 'level') . ' ' . ($reqValue >= 1 ? $reqValue : 0) . '</span>';
                }
                $conditions[] = $condition . '</span>';
            }
            $data['soonConditions'] = implode(', ', $conditions);
        }
        $data['race'] = $this->session->getRace();
        $data['raceName'] = $this->session->getRaceName();
        return PHPBatchView::render("build/buildingWrapper", $data);
    }

    /**
     * A new-build card describes its level-one outcome.  These values use the
     * same formulas as the regular building-upgrade screen, so tribe-specific
     * capacities and local speed rules remain one source of truth.
     *
     * @return array<int, array{icon:string, value:string, suffix:string}>
     */
    private function getNewBuildingBenefits(int $itemId): array
    {
        $level = 1;
        $cpPop = Formulas::buildingCpPop($itemId, 0, $level);
        $benefits = [
            ['icon' => 'culturePoints_medium', 'value' => (string)$cpPop[1], 'suffix' => ''],
            ['icon' => 'population_medium', 'value' => (string)$cpPop[0], 'suffix' => ''],
        ];
        $add = static function (string $icon, int|float $value, string $suffix = '') use (&$benefits): void {
            $benefits[] = [
                'icon' => $icon,
                'value' => number_format_x($value),
                'suffix' => $suffix,
            ];
        };

        switch ($itemId) {
            case 5:
                $add('lumberBonus_small', $level * 5, '%');
                break;
            case 6:
                $add('clayBonus_small', $level * 5, '%');
                break;
            case 7:
                $add('ironBonus_small', $level * 5, '%');
                break;
            case 8:
            case 9:
                $add('cropBonus_medium', $level * 5, '%');
                break;
            case 10:
                $add('warehouseCap_medium', Formulas::storeCAP($level));
                break;
            case 11:
                $add('granaryCap_medium', Formulas::storeCAP($level));
                break;
            case 14:
                $add('troopSpeed_medium', Formulas::TournamentSqValue($level), '%');
                break;
            case 15:
                $add('constructionTime_medium', Formulas::getMainBuildingValue($level), '%');
                break;
            case 19:
            case 29:
                $add('infantryBonusTime_medium', $this->trainingTimeReduction($level), '%');
                break;
            case 20:
            case 30:
                $add('cavalryBonusTime_medium', $this->trainingTimeReduction($level), '%');
                break;
            case 21:
                $add('siegeTime_small', $this->trainingTimeReduction($level), '%');
                break;
            case 23:
                $add('crannyCap_small', Formulas::crannyCAP($level, $this->session->getRace()));
                break;
            case 28:
                $allianceBonus = $this->session->getAllianceId() > 0
                    ? AllianceBonus::getTradersBonus($this->session->getAllianceId(), $this->session->getAllianceJoinTime())
                    : 1;
                $add('merchantCap_small', Formulas::merchantCAP($this->session->getRace(), $level, $allianceBonus));
                break;
            case 34:
                $add('stabilityBonus_small', Formulas::StonemasonsLodgeValue($level), '%');
                break;
            case 36:
                $add('maxTraps_medium', Formulas::trapperValue($level));
                break;
            case 38:
                $add('warehouseCap_medium', Formulas::bigStoreCAP($level));
                break;
            case 39:
                $add('granaryCap_medium', Formulas::bigStoreCAP($level));
                break;
            case 46:
            case 48:
                $add('healTime_medium', round(100 * pow(0.9, $level - 1), 1), '%');
                break;
            case 49:
                $add('maxShips_medium', \Game\Ships::maxShips($level));
                break;
            default:
                if (in_array($itemId, Formulas::WALL_BUILDINGS, true)) {
                    $add('defenceBonus_medium', Formulas::wallPower($this->session->getRace(), $level));
                }
                break;
        }

        return $benefits;
    }

    /**
     * @param mixed $item_id
     * @return array<string, mixed>
     */
    private function getActionText($item_id = 0)
    {
        $result = [
            'main' => null,
            'master' => null,
            'plus' => null,
            'noResources' => false,
            'extraModuleButton' => null,
        ];
        $village = Village::getInstance();
        $fieldId = $this->selectedBuildingIndex;
        if (($item_id == 16 || in_array($item_id, Formulas::WALL_BUILDINGS))) {
            if ($item_id == 16) {
                $fieldId = 39;
            } else {
                $fieldId = 40;
            }
        }
        $new = false;
        if ($item_id === 0) {
            $item_id = $village->getField($this->selectedBuildingIndex)['item_id'];
        } else {
            $new = true;
        }
        $lvl = $village->getField($fieldId)['level'] + $village->getField($fieldId)['upgrade_state'] + 1;
        if ($village->getField($fieldId)['item_id'] == 40 && $village->isWW()) {
            foreach ($village->onLoadBuildings['master'] as $k => $v) {
                if ($this->selectedBuildingIndex == $v['building_field']) {
                    $lvl++;
                }
            }
        }
        //check status of maxed !
        if ($village->getField($fieldId)['level'] >= Formulas::buildingMaxLvl($item_id, $village->isCapital())) {
            $result['main'] = "<span class=\"none\">" . T("Buildings", $item_id . ".title") . " " . T("Buildings", "upgradeNotices.reachedMaxLvL") . "</span>";
            goto outReturn;
        } elseif ($village->getField($fieldId)['level'] + $village->getField($fieldId)['upgrade_state'] >= Formulas::buildingMaxLvl($item_id, $village->isCapital())) {
            $result['main'] = "<span class=\"none\">" . sprintf(T("Buildings", "upgradeNotices.currentlyReachingMaxLevel"), T("Buildings", $item_id . ".title")) . "</span>";
            goto outReturn;
        } elseif ($item_id == 40 && ($lvl - 1) >= Formulas::buildingMaxLvl($item_id, $village->isCapital())) {
            $result['main'] = "<span class=\"none\">" . sprintf(T("Buildings", "upgradeNotices.currentlyReachingMaxLevel"), T("Buildings", $item_id . ".title")) . "</span>";
            goto outReturn;
        } elseif ($village->getOnDemolishBuildingFieldId() == $fieldId) {
            $result['main'] = "<span class=\"none\">" . T("Buildings", "upgradeNotices.buildingIsOnDemolition") . "</span>";
            goto outReturn;
        }
        $needUpgradeType = $village->checkArtifactDependencies($item_id);
        if ($needUpgradeType <> 0) {
            switch ($needUpgradeType) {
                case 1:
                    $result['main'] = '<span class="errorMessage">' . T("Buildings", "errors.noGreatArtefact") . '</span>';
                    goto outReturn;
                case 2:
                case 3:
                    $result['main'] = '<span class="errorMessage">' . T("Buildings", "errors.wwPlans") . '</span>';
                    goto outReturn;
            }
        }

        $needUpgradeType = $village->checkDependencies($item_id, $lvl);
        if ($needUpgradeType <> 0) {
            switch ($needUpgradeType) {
                case 1:
                    $result['main'] = "<span class=\"none\">" . T("Buildings", "errors.foodShortage") . "</span>";
                    goto outReturn;
                case 2:
                    $result['main'] = "<span class=\"none\">" . T("Buildings", "errors.upgradeWareHouse") . "</span>";
                    goto outReturn;
                case 22:
                    $result['main'] = "<span class=\"none\">" . T("Buildings", "errors.constructWarehouse") . "</span>";
                    goto outReturn;
                case 3:
                    $result['main'] = "<span class=\"none\">" . T("Buildings", "errors.upgradeGranny") . "</span>";
                    goto outReturn;
                case 33:
                    $result['main'] = "<span class=\"none\">" . T("Buildings", "errors.constructGranny") . "</span>";
                    goto outReturn;
            }
        }
        $session = $this->session;
        $workerResult = $village->isWorkersBusy($item_id <= 4, $item_id == 40);
        $pageNamePostfix = ($item_id <= 4 ? '1' : '2');
        $link = '/dorf' . $pageNamePostfix . '.php?id=' . $fieldId
            . '&gid=' . $item_id
            . '&action=build&checksum=' . $session->getChecker();
        $masterBuilderLink = $link . '&buildmaster';
        $result['extraModuleButton'] = $new ? null : $this->maxLevelButton((int)$fieldId);
        $cost = Formulas::buildingUpgradeCosts($item_id, $lvl);
        $goldHelper = new GoldHelper();
        if ((!$village->isWW() || $item_id == 40) && ($workerResult['isBusy'] || !$village->isResourcesAvailable($cost))) {
            //masterBuilder
            $result['master'] = $goldHelper->getMasterBuilderButton($fieldId, $item_id, $village->getField($fieldId)['level'] + $village->getField($fieldId)['upgrade_state'], $masterBuilderLink);
        }
        if (!$session->hasPlus() && $workerResult['isBusy']) {
            $result['plus'] = getButton(["type" => "button", "class" => "textButtonV1 gold builder", "value" => T("Buildings", "buildingQueue.name"),], ["data" => ['type' => 'button', "plusDialog" => ["featureKey" => 'buildingQueue'],],], T("Buildings", "buildingQueue.name"));
            $result['plus'] .= '<br>';
            $result['plus'] .= T("Buildings", "buildingQueue.desc");
        }
        $cost = Formulas::buildingUpgradeCosts($item_id, $lvl);
        if (!$village->isResourcesAvailable($cost)) {
            $result['noResources'] = true;
            $result['main'] = $village->calcWhenResourcesAreAvailable($cost, true);
            goto outReturn;
        }
        $buttonLabel = $lvl == 1 && $item_id > 4 ? T("Buildings", "constructBuilding") : sprintf(T("Buildings", "upgradeBuilding"), $lvl);
        $btn = getButton(["type" => "button", "class" => "textButtonV1 green " . ($new ? 'new' : 'build'), "value" => $buttonLabel, "onclick" => "this.disabled = true; window.location.href = '$link'; return false;",], ['data' => ["class" => "textButtonV1 green " . ($new ? 'new' : 'build')]], $buttonLabel);
        $result['waitLoop'] = $workerResult['isPlusUsed'];
        if ($workerResult['isBusy']) {
            // Shows the Master Builder alternative alone, without a workers-busy message above it.
            $result['main'] = isset($result['master']) ? null : '<span class="errorMessage">' . T("Buildings", "workersBusy") . '</span><br>';
            goto outReturn;
        } else {
            $result['main'] = $btn;
            goto outReturn;
        }
        outReturn:
        return $result;
    }

    private function procBuilding(): bool
    {
        $village = Village::getInstance();
        //process tabs here.
        $showDesc = true;
        switch ($this->buildView->vars['selectedBuildingId']) {
            case 16: //rally point
                $rallyPoint = new RallyPointCtrl($this);
                $this->buildView->vars['content'] .= $rallyPoint->render();
                return true;
            case 25:
            case 26:
            case 44:
                $dispatcher = new ResidencePalaceCtrl($this);
                $this->buildView->vars['content'] .= $dispatcher->render();
                return true;
            case 27:
                $dispatcher = new TreasuryCtrl($this);
                $this->buildView->vars['content'] .= $dispatcher->render();
                return true;
            case 17://Marketplace TOD:
                $dispatcher = new MarketPlaceCtrl($this);
                $this->buildView->vars['content'] .= $dispatcher->render();
                return true;
        }
        if ($showDesc) {
            $this->buildView->vars['content'] .= $this->getBuildingContract();
        }
        switch ($this->buildView->vars['selectedBuildingId']) {
            case 40:
                $db = DB::getInstance();
                $wwName = $db->fetchScalar("SELECT wwname FROM fdata WHERE kid=" . $this->session->getKid());
                $wwNameInput = $_POST['ww_name'] ?? null;
                if (is_string($wwNameInput) && StringChecker::isValidName($wwNameInput)) {
                    if (getDisplay("allowWWUnusualRename") || $village->getField($this->selectedBuildingIndex)['level'] <= 10) {
                        if (strlen($wwNameInput) <= 20) {
                            $wwName = $db->real_escape_string(strip_tags($wwNameInput));
                            $db->query("UPDATE fdata SET wwname='$wwName' WHERE kid=" . $this->session->getKid());
                        }
                    }
                }
                $view = new PHPBatchView("build/wwChangeName");
                $view->vars['level'] = $village->getField($this->selectedBuildingIndex)['level'];
                $view->vars['WWName'] = $wwName;
                $this->buildView->vars['content'] .= $view->output();
                break;
            case 18:
                $view = new EmbassyCtrl($this);
                $this->buildView->vars['content'] .= $view->render();
                break;
            case 15:
                $view = new MainBuildingCtrl((int)$village->getField($this->selectedBuildingIndex)['level']);
                $this->buildView->vars['content'] .= $view->render();
                break;
            case 37:
                $view = new HeroMansionCtrl();
                $this->buildView->vars['content'] .= $view->render();
                break;
            case 24:
                if ($village->getField($this->selectedBuildingIndex)['level'] <= 0) {
                    break;
                }
                $view = new TownhallCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $view->render();
                break;

            case 13:
                $dispatcher = new SmithyCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $dispatcher->render();
                break;
            case 22:
                $dispatcher = new AcademyCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $dispatcher->render();
                break;
            case 19:
            case 20:
            case 21:
            case 29:
            case 30:
            case 36:
                if ($village->getField($this->selectedBuildingIndex)['level'] <= 0) {
                    break;
                }
                $dispatcher = new TroopBuilding($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $dispatcher->render();
                break;
            case 46:
            case 48:
                if ($village->getField($this->selectedBuildingIndex)['level'] <= 0) {
                    break;
                }
                $view = new HospitalCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $view->render();
                break;
            case 49:
                if ($village->getField($this->selectedBuildingIndex)['level'] <= 0) {
                    break;
                }
                $view = new HarborCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $view->render();
                break;
            case 35:
                if ($village->getField($this->selectedBuildingIndex)['level'] <= 0) {
                    break;
                }
                $view = new BreweryCtrl($this->selectedBuildingIndex);
                $this->buildView->vars['content'] .= $view->render();
                break;
        }
        return true;
    }

    /**
     * Speed-world gold "Upgrade to max level": yellow gold button, instant max for gold, no resources and no queue.
     */
    private function maxLevelButton(int $fieldId): ?string
    {
        $button = ExtraModules::showButton('upgradeToMaxLevel', $fieldId);

        return is_string($button) && $button !== '' ? $button : null;
    }

    /**
     * @return mixed
     */
    public function getBuildingContract()
    {
        $village = Village::getInstance();
        $cost = Formulas::buildingUpgradeCosts($village->getField($this->selectedBuildingIndex)['item_id'], $village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state'] + 1);
        $goldHelper = new GoldHelper();
        $curLevel = ($village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state']);
        $nextLevel = 1 + ($village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state']);
        $contract = [
            'building_field' => $this->selectedBuildingIndex,
            "itemId" => $village->getField($this->selectedBuildingIndex)['item_id'],
            "showCosts" => $curLevel < Formulas::buildingMaxLvl($village->getField($this->selectedBuildingIndex)['item_id'], $village->isCapital()),
            "contractText" => sprintf(T("Buildings", "costsForUpgradeToLvl"), $village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state'] + 1),
            "cost" => $cost,
            "freeCrop" => Formulas::buildingCropConsumption($village->getField($this->selectedBuildingIndex)['item_id'], $village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state'] + 1),
            "timeInString" => secondsToString(Formulas::buildingUpgradeTime($village->getField($this->selectedBuildingIndex)['item_id'], $village->getField($this->selectedBuildingIndex)['level'] + $village->getField($this->selectedBuildingIndex)['upgrade_state'] + 1, $village->getMainBuildingLvl(), $village->isWW())), "npcButton" => $goldHelper->getExchangeResourcesButtonByCost($cost),
            "showValuesTable" => false,
            'contractLink' => null,
            "valueTable" => '',
        ];
        $cpPop = Formulas::buildingCpPop($village->getField($this->selectedBuildingIndex)['item_id'], 0, $curLevel);
        $contract['culturePointsAndPopulation'] = [
            'pop' => $cpPop[0],
            'cp' => $cpPop[1],
            'infantryBonusTime' => null,
            'cavalryBonusTime' => null,
            'siegeTime' => null,
            'constructionTime' => null,
            'maxTraps' => null,
            'merchantCap' => null,
            'warehouseCap' => null,
            'granaryCap' => null,
            'troopSpeed' => null,
            'crannyCap' => null,
            'troopVisibility' => null,
            'defenceBonus' => null,
        ];
        $newCpPop = Formulas::buildingCpPop($village->getField($this->selectedBuildingIndex)['item_id'], 0, $nextLevel);
        $contract['nextLevelCpPop'] = [
            'pop' => $newCpPop[0] - $cpPop[0],
            'cp' => $newCpPop[1] - $cpPop[1],
            'infantryBonusTime' => null,
            'cavalryBonusTime' => null,
            'siegeTime' => null,
            'constructionTime' => null,
            'maxTraps' => null,
            'merchantCap' => null,
            'warehouseCap' => null,
            'granaryCap' => null,
            'troopSpeed' => null,
            'crannyCap' => null,
            'troopVisibility' => null,
            'defenceBonus' => null,
        ];
        $item_id = $village->getField($this->selectedBuildingIndex)['item_id'];
        if ($item_id == 14) {
            $contract['culturePointsAndPopulation']['troopSpeed'] = Formulas::TournamentSqValue($curLevel);
            $contract['nextLevelCpPop']['troopSpeed'] = Formulas::TournamentSqValue($nextLevel) - $contract['culturePointsAndPopulation']['troopSpeed'];
        } elseif ($item_id == 10 ||  $item_id == 38) {
            $contract['culturePointsAndPopulation']['warehouseCap'] = $item_id == 38 ? Formulas::bigStoreCAP($curLevel) : Formulas::storeCAP($curLevel);
            $contract['nextLevelCpPop']['warehouseCap'] = ($item_id == 38 ? Formulas::bigStoreCAP($nextLevel) : Formulas::storeCAP($nextLevel)) - $contract['culturePointsAndPopulation']['warehouseCap'];
        } elseif ($item_id == 11 || $item_id == 39) {
            $contract['culturePointsAndPopulation']['granaryCap'] = $item_id == 39 ? Formulas::bigStoreCAP($curLevel) : Formulas::storeCAP($curLevel);
            $contract['nextLevelCpPop']['granaryCap'] = ($item_id == 39 ? Formulas::bigStoreCAP($nextLevel) : Formulas::storeCAP($nextLevel)) - $contract['culturePointsAndPopulation']['granaryCap'];
        } elseif ($item_id == 28) {
            $alliance_bonus = 1;
            if ($this->session->getAllianceId() > 0) {
                $alliance_bonus = AllianceBonus::getTradersBonus($this->session->getAllianceId(), $this->session->getAllianceJoinTime());
            }
            $contract['culturePointsAndPopulation']['merchantCap'] = Formulas::merchantCAP($this->session->getRace(), $curLevel, $alliance_bonus);
            $contract['nextLevelCpPop']['merchantCap'] = Formulas::merchantCAP($this->session->getRace(), $nextLevel, $alliance_bonus) - $contract['culturePointsAndPopulation']['merchantCap'];
        } elseif (in_array($item_id, [20, 30], true)) {
            $contract['culturePointsAndPopulation']['cavalryBonusTime'] = $this->trainingTimeReduction($curLevel);
            $contract['nextLevelCpPop']['cavalryBonusTime'] = $this->trainingTimeReduction($nextLevel)
                - $contract['culturePointsAndPopulation']['cavalryBonusTime'];
        } elseif ($item_id == 21) {
            $contract['culturePointsAndPopulation']['siegeTime'] = $this->trainingTimeReduction($curLevel);
            $contract['nextLevelCpPop']['siegeTime'] = $this->trainingTimeReduction($nextLevel)
                - $contract['culturePointsAndPopulation']['siegeTime'];
        } elseif ($item_id == 36) {
            $contract['culturePointsAndPopulation']['maxTraps'] = Formulas::trapperValue($curLevel);
            $contract['nextLevelCpPop']['maxTraps'] = Formulas::trapperValue($nextLevel) - $contract['culturePointsAndPopulation']['maxTraps'];
        } elseif (in_array($item_id, [19, 29], true)) {
            $contract['culturePointsAndPopulation']['infantryBonusTime'] = $this->trainingTimeReduction($curLevel);
            $contract['nextLevelCpPop']['infantryBonusTime'] = $this->trainingTimeReduction($nextLevel)
                - $contract['culturePointsAndPopulation']['infantryBonusTime'];
        } elseif ($item_id == 15) {
            $contract['culturePointsAndPopulation']['constructionTime'] = Formulas::getMainBuildingValue($curLevel);
            $contract['nextLevelCpPop']['constructionTime'] = Formulas::getMainBuildingValue($curLevel) - Formulas::getMainBuildingValue($nextLevel);
        } elseif ($item_id == 16) {
            // The rally point shows an attacking army's unit types while it has fewer units than its level.
            $contract['culturePointsAndPopulation']['troopVisibility'] = max(0, $curLevel - 1);
            $contract['nextLevelCpPop']['troopVisibility'] = max(0, $nextLevel - 1) - $contract['culturePointsAndPopulation']['troopVisibility'];
        } elseif (in_array($item_id, Formulas::WALL_BUILDINGS, true)) {
            $contract['culturePointsAndPopulation']['defenceBonus'] = Formulas::wallPower($this->session->getRace(), $curLevel);
            $contract['nextLevelCpPop']['defenceBonus'] = Formulas::wallPower($this->session->getRace(), $nextLevel) - $contract['culturePointsAndPopulation']['defenceBonus'];
        } elseif ($item_id == 23) {
            $contract['culturePointsAndPopulation']['crannyCap'] = Formulas::crannyCAP($curLevel, $this->session->getRace());
            $contract['nextLevelCpPop']['crannyCap'] = Formulas::crannyCAP($nextLevel, $this->session->getRace()) - $contract['culturePointsAndPopulation']['crannyCap'];
        }
        switch ($item_id) {
            case 1:
            case 2:
            case 3:
            case 4:
                $percent = $village->calculateResourcesTotalBonusProduction($item_id);
                $this->getValuesTable($contract, ["percent" => $percent], function ($params, $lvl) {
                    return Formulas::fieldProduction($lvl) + round5(Formulas::fieldProduction($lvl) * $params['percent'] / 100);
                });
                break;
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            case 45:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return $lvl * 5;
                });
                break;
            case 10:
            case 11:
            case 38:
            case 39:
                $this->getValuesTable($contract, ["big" => $item_id > 11], function ($params, $lvl) {
                    return $params['big'] ? Formulas::bigStoreCAP($lvl) : Formulas::storeCAP($lvl);
                });
                break;
            case 23:
                $increase = ArtefactsModel::getArtifactEffectByType($this->session->getPlayerId(), $village->getKid(), ArtefactsModel::ARTIFACT_CRANNY);
                $this->getValuesTable($contract, ["race" => $this->session->getRace(), "increase" => $increase,], function ($params, $lvl) {
                    return Formulas::crannyCAP($lvl, $params['race']) * $params['increase'];
                });
                break;
            case 36:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::trapperValue($lvl);
                });
                break;
            case 15:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::getMainBuildingValue($lvl);
                });
                break;
            case 19:
            case 20:
            case 21:
            case 29:
            case 30:
                $this->getTrainingTimeValuesTable($contract);
                break;
            case 31:
            case 32:
            case 33:
            case 42:
            case 43:
            case 47:
            case 50:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::wallPower($this->session->getRace(), $lvl);
                });
                break;
            case 46:
            case 48:
                // Duration of healing relative to a level-1 building (Knowledge Base building effect "healTime").
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return round(100 * pow(0.9, $lvl - 1), 1);
                });
                break;
            case 41:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return 100 + $lvl;
                });
                break;
            case 14:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::TournamentSqValue($lvl);
                });
                break;
            case 28:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::TradeOfficeValue($this->session->getRace(), $lvl);
                });
                break;
            case 34:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return Formulas::StonemasonsLodgeValue($lvl);
                });
                break;
            case 49:
                $this->getValuesTable($contract, [], function ($params, $lvl) {
                    return \Game\Ships::maxShips($lvl);
                });
                break;
        }
        if (is_null($contract['contractLink'])) {
            $contract['contractLink'] = $this->getActionText();
        }
        $contract['isNew'] = !($village->getField($this->selectedBuildingIndex)['item_id'] <> 0);
        $contract['raceName'] = $this->session->getRaceName();
        return PHPBatchView::render("build/buildingReadyWrapper", $contract);
    }

    private function trainingTimeReduction(int $level): int
    {
        return (int)round(100 * (1 - pow(0.9, max(1, $level) - 1)));
    }

    private function trainingTimeValue(int $reduction): string
    {
        $sign = $reduction > 0 ? '&#8237;−&#8237;' : '&#8237;+&#8237;';
        return '<span class="number">' . $sign . $reduction . '&#8236;%&#8236;</span>';
    }

    /**
     * Barracks, Stable, Workshop, Great Barracks and Great Stable all share
     * the percentage-of-level-one training-time table.
     *
     * @param array<mixed> $contract
     */
    private function getTrainingTimeValuesTable(array &$contract): void
    {
        $contract['showValuesTable'] = true;
        $contract['valueTable'] = '';

        $village = Village::getInstance();
        $field = $village->getField($this->selectedBuildingIndex);
        $level = (int)$field['level'];
        $upgradeState = (int)$field['upgrade_state'];
        $itemId = (int)$field['item_id'];
        $maxLevel = Formulas::buildingMaxLvl($itemId, $village->isCapital());

        $contract['valueTable'] .= '<tr class="currentLevel"><th>'
            . T('Buildings', 'trainingTimeCurrent')
            . ':</th><td>' . $this->trainingTimeValue($this->trainingTimeReduction($level)) . '</td></tr>';

        for ($next = $level + 1; $next <= min($level + $upgradeState, $maxLevel); ++$next) {
            $message = $next === $maxLevel
                ? sprintf(T('Buildings', 'upgradeNotices.currentlyReachingMaxLevel'), T('Buildings', "{$itemId}.title"))
                : sprintf(T('Buildings', 'upgradeNotices.currentlyUpgradingToLevel'), $next) . ':';
            $contract['valueTable'] .= '<tr class="underConstruction"><th>' . $message . '</th><td>'
                . $this->trainingTimeValue($this->trainingTimeReduction($next)) . '</td></tr>';
        }

        $nextLevel = $level + $upgradeState + 1;
        if ($nextLevel <= $maxLevel) {
            $contract['valueTable'] .= '<tr class="nextPossible"><th>'
                . sprintf(T('Buildings', 'trainingTimeNext'), $nextLevel)
                . ':</th><td>' . $this->trainingTimeValue($this->trainingTimeReduction($nextLevel)) . '</td></tr>';
        }
    }

    /**
     * @param array<mixed> $contract
     * @param array<mixed>|array<string, bool>|array<string, mixed> $params
     */
    private function getValuesTable(&$contract, $params, callable $callback): void
    {
        $contract['showValuesTable'] = true;
        $contract['valueTable'] = '';
        //no over all for resources
        $village = Village::getInstance();
        $item_id = $village->getField($this->selectedBuildingIndex)['item_id'];
        if ($item_id == 23 || $item_id == 36) {
            // Lists the overall row only once a second one exists.
            $levels = $item_id == 23 ? $village->getCrannyLvls() : $village->getTrapperLvls();
            if (count($levels) > 1) {
                $overall = 0;
                foreach ($levels as $lvl) {
                    $overall += $callback($params, $lvl);
                }
                $contract['valueTable'] .= '<tr class="overall"><th>' . T("Buildings", $item_id . ".overall_storage") . '</th><td><span class="number">' . (int) round($overall) . '</span> ' . T("Buildings", $item_id . ".unit") . '</td></tr>';
            }
        }
        $current_value = $callback($params, $village->getField($this->selectedBuildingIndex)['level']);
        $contract['valueTable'] .= '<tr class="currentLevel"><th>' . T("Buildings", $item_id . ".current_prod") . ':</th><td><span class="number">' . (int) round($current_value) . '</span> ' . T("Buildings", $item_id . ".unit") . '</td></tr>';
        $tmp = $village->getField($this->selectedBuildingIndex)['level'];
        $upgrade_state = $village->getField($this->selectedBuildingIndex)['upgrade_state'];
        $maxLvl = Formulas::buildingMaxLvl($item_id, $village->isCapital());
        if ($upgrade_state) {
            for ($i = $tmp + 1; $i <= $tmp + $upgrade_state; ++$i) {
                if ($i == $maxLvl) {
                    $msg = sprintf(T("Buildings", "upgradeNotices.currentlyReachingMaxLevel"), T("Buildings", "{$item_id}.title"));
                } else {
                    $msg = sprintf(T("Buildings", "upgradeNotices.currentlyUpgradingToLevel"), $i) . ':';
                }
                $contract['valueTable'] .= '<tr class="underConstruction">';
                $contract['valueTable'] .= '<th>' . $msg . '</th>';
                $value = $callback($params, $i);
                $contract['valueTable'] .= '<td><span class="number">' . (int) round($value) . '</span> ' . T("Buildings", $item_id . ".unit") . '</td>';
                $contract['valueTable'] .= '</tr>';
            }
        }
        if ($village->getField($this->selectedBuildingIndex)['level'] >= $maxLvl) {
        } elseif (($village->getField($this->selectedBuildingIndex)['level'] + $upgrade_state) >= $maxLvl) {
        } else {
            $nextLevel = ($village->getField($this->selectedBuildingIndex)['level'] + $upgrade_state) + 1;
            $value = $callback($params, $nextLevel);
            $contract['valueTable'] .= '<tr class="nextPossible"><th>' . T("Buildings", $item_id . ".next_prod") . ' ' . $nextLevel . ':</th><td><span class="number">' . (int) round($value) . '</span> ' . T("Buildings", $item_id . ".unit") . '</td></tr>';
        }
    }
}
