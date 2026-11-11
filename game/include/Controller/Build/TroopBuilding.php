<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\ExtraModules;
use Game\Formulas;
use Game\GoldHelper;
use Game\Hero\HeroHelper;
use Game\Hero\SessionHero;
use Game\SpeedShortcuts;
use Game\TrainingHelper;
use Game\TrainingQueueView;
use function getDisplay;
use Model\ArtefactsModel;
use Model\DailyQuestModel;
use Model\Quest;
use Model\TrainingModel;
use resources\View\PHPBatchView;

class TroopBuilding extends AnyCtrl
{
    /** @var mixed */
    private $hero_percents = [0, 0];
    /** @var mixed */
    private $building_id = 19;
    /** @var mixed */
    private $building_level = 1;
    /** @var float|int */
    private $art_eff = 1;
    /** @var array<mixed>|null */
    private $units = [];
    /** @var mixed */
    private $alliance_bonus = 1;
    /** @var array<string, int> */
    private array $smithyLevels = [];
    /** @var list<int> */
    private array $researched = [];
    private int $trainable = 0;

    /**
     * @param mixed $index
     */
    public function __construct($index)
    {
        parent::__construct();
        $session = Session::getInstance();
        $this->view = new PHPBatchView("build/building/TroopBuildingLayout");
        $this->building_id = Village::getInstance()->getField($index)['item_id'];
        $this->building_level = Village::getInstance()->getField($index)['level'];
        $this->art_eff = ArtefactsModel::getArtifactEffectByType(Session::getInstance()->getPlayerId(), Village::getInstance()->getKid(), ArtefactsModel::ARTIFACT_INCREASE_TRAINING_SPEED);
        $m = new TrainingModel();
        if (!in_array($this->building_id, [25, 26, 44, 36]) && $this->session->hero->getHeroHealth() > 0) {
            //hero items here.
            if (
                Config::getProperty('heroConfig', 'allowHeroTrainingHelmetsInAllVillages') ||
                $this->session->hero->hero['kid'] == Village::getInstance()->getKid()) {
                $db = DB::getInstance();
                $helper = new HeroHelper();
                $this->hero_percents = $helper->calcTrainEffect($db->fetchScalar("SELECT helmet FROM inventory WHERE uid=" . Session::getInstance()->getPlayerId()));
            }
        }
        if ($session->getAllianceId() > 0) {
            $this->alliance_bonus = AllianceBonus::getTrainingBonus($session->getAllianceId(), $session->getAllianceJoinTime());
        }
        $technology = $m->getTechnology(Village::getInstance()->getKid());
        if (WebService::isPost()) {
            if (Session::getInstance()->banned()) {
                $this->innerRedirect("InGameBannedPage");
            } else if (Config::getInstance()->dynamic->serverFinished) {
                $this->innerRedirect("InGameWinnerPage");
            } else if (Session::getInstance()->isInVacationMode()) {
                redirect('/options/vacation');
            } else {
                $this->procTraining($technology);
            }
        }
        $this->units = $m->getUnits(Session::getInstance()->getKid());
        if ($this->building_id == 36) {
            $this->view->vars['trap_desc'] = '<p>' . sprintf(T("inGame", "trap_desc"),
                    "<b>" . $this->units['u99'] . "</b>",
                    "<b>" . $m->getFilledTrapCount(Village::getInstance()->getKid()) . "</b>");
        }
        $troopsCanTrain = $this->researched = $this->researchedTroops($technology);
        $expansion = in_array($this->building_id, [25, 26, 44]);
        if ($expansion) {
            $troopsCanTrain = array_map('unitIdToNr', $this->_getTroopBuildingTroops());
            // The reference lists the settlers and chief with the reason they cannot be trained yet.
            $this->view->vars['canTrain'] = true;
        }
        if (!isset($this->view->vars['canTrain'])) {
            $this->view->vars['canTrain'] = sizeof($troopsCanTrain);
        }
        $this->view->vars['expansion'] = $expansion;
        $this->view->vars['item_id'] = $this->building_id;
        $this->view->vars['index'] = $index;
        $this->view->vars['did'] = Village::getInstance()->getKid();
        $this->view->vars['favouriteSlots'] = [
            't1', 't2', 't3', 't4', 't5', 't6', 't7', 't8', 't9', 't10',
            't901', 't902', 't903', 't904',
        ];
        $this->view->vars['finishTraining_button'] = null;
        $this->view->vars['checker'] = Session::getInstance()->getChecker();
        $this->view->vars['wrappers'] = '';
        $this->smithyLevels = $this->loadSmithyLevels((int) Village::getInstance()->getKid());
        foreach ($troopsCanTrain as $v) {
            $this->view->vars['wrappers'] .= $this->getTroopWrapper($v);
        }
        $this->view->vars['canSubmit'] = $this->trainable > 0;
        $this->view->vars['emptySlots'] = array_values(array_diff(
            $this->view->vars['favouriteSlots'],
            array_map(static fn($nr): string => 't' . $nr, $troopsCanTrain)
        ));
        $training = $m->getTraining(Village::getInstance()->getKid(), $this->building_id);
        $this->view->vars['isTraining'] = $training->num_rows;
        $this->view->vars['training'] = '';
        if ($training->num_rows) {
            $race = Session::getInstance()->getRace();
            $this->view->vars['training'] = TrainingQueueView::render(
                $training,
                static function (array $train, string $count) use ($race): string {
                    $unitId = nrToUnitId($train['nr'], $race);
                    $title = T('Troops', "{$unitId}.title");

                    return '<img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . $title . '" title="' . $title . '" /> '
                        . $count . ' ' . $title;
                },
                $this->building_id == 36 ? 'next_creating' : 'next_training'
            ) . '<div class="clear"></div>';
        }
        if (!empty($this->view->vars['training']) && $this->view->vars['canTrain']) {
            $this->view->vars['finishTraining_button'] = ' ' . ExtraModules::finishTrainingButton();
        }
    }

    /**
     * Unit numbers this building trains that the village has researched (settlers, the first infantry unit and
     * traps need no research).
     *
     * @param array<string, mixed>|null $technology
     * @return list<int>
     */
    private function researchedTroops($technology): array
    {
        $numbers = array_map('unitIdToNr', $this->_getTroopBuildingTroops());
        $researched = array_filter($numbers, static fn (int $nr): bool => in_array($nr, [1, 10, 99], true) || !empty($technology['u' . $nr]));
        sort($researched, SORT_NUMERIC);

        return $researched;
    }

    /**
     * @param array<string, mixed>|null $technology
     */
    private function procTraining($technology): void
    {
        $troopsCanTrain = $this->researchedTroops($technology);
        $m = new TrainingModel();
        $action = $_POST['action'] ?? null;
        if ($action !== 'trainTroops' || !Session::claimAction($_POST['checksum'] ?? null)) {
            return;
        }
        $helper = new TrainingHelper();
        $great = $this->building_id == 30 || $this->building_id == 29;
        // "Train maximum" (a speed-world shortcut) orders everything one troop can be afforded; otherwise the typed amounts.
        $maximum = SpeedShortcuts::enabled() && isset($_POST['trainMaximum']) && is_scalar($_POST['trainMaximum']) ? (int)$_POST['trainMaximum'] : null;
        $orders = [];
        foreach ($troopsCanTrain as $u) {
            if ($maximum !== null) {
                if ($u === $maximum) {
                    $orders[$u] = (int)$helper->getMaxUnitByNr($u, $great);
                }
            } elseif (isset($_REQUEST['t' . $u])) {
                $orders[$u] = min($helper->getMaxUnitByNr($u, $great), abs((int)$_REQUEST['t' . $u]));
            }
        }
        foreach ($orders as $u => $num) {
            if ($num > 0) {
                $this->queueOrder((int)$u, (int)$num, $great, $m);
            }
        }
    }

    /** Pays for and queues `$num` units of troop `$u`; false when the stock falls short or the payment fails. */
    private function queueOrder(int $u, int $num, bool $great, TrainingModel $m): bool
    {
        $dailyQuest = new DailyQuestModel();
        $quest = Quest::getInstance();
        if ($num >= 20 && ($this->building_id == 19 || $this->building_id == 29)) {
            $dailyQuest->setQuestAsCompleted(Session::getInstance()->getPlayerId(), 8);
        } else if ($num >= 20 && ($this->building_id == 20 || $this->building_id == 30)) {
            $dailyQuest->setQuestAsCompleted(Session::getInstance()->getPlayerId(), 9);
        }
        if ($this->building_id == 19 && $num >= 2) {
            $quest->setQuestBitwise('battle', 5, 1);
        }
        $cost = Formulas::uTrainingCost(nrToUnitId($u, Session::getInstance()->getRace()), $great);
        foreach ($cost as &$v) {
            $v *= $num;
        }
        $village = Village::getInstance();
        if (!$village->isResourcesAvailable($cost)) {
            return false;
        }
        $buildingId = $this->building_id;
        $trainingTime = $this->_getTroopTrainingTime(nrToUnitId($u, Session::getInstance()->getRace()));

        return $village->transaction(static function () use ($village, $m, $cost, $buildingId, $u, $num, $trainingTime): bool {
            if (!$village->spend($cost)) {
                return false;
            }
            $m->addTraining($village->getKid(), $buildingId, $u, $num, $trainingTime);

            return true;
        });
    }

    /**
     * @return mixed
     */
    public function _getTroopBuildingTroops()
    {
        return self::_getTroopBuildingTroopsStatic(Session::getInstance()->getRace(), $this->building_id);
    }

    /**
     * @param mixed $race
     * @param mixed $building_id
     * @return array<int>|list<int>
     */
    public static function _getTroopBuildingTroopsStatic($race, $building_id)
    {
        if ($building_id == 36) {
            return (int)$race === 3 ? [99] : [];
        }

        return \Game\Tribes::trainingUnitIds((int)$race, (int)$building_id);
    }

    /**
     * @param mixed $u
     * @return mixed
     */
    public function _getTroopTrainingTime($u)
    {
        $percent = Session::getInstance()->get("fasterTraining") > time() ?
            Config::getInstance()->extraSettings->generalOptions->fasterTraining->percent : 0;
        $time = Formulas::uTrainingTime($u,
            $this->building_level,
            Village::getInstance()->getHorseDrinkingPoolLvl(),
            $this->hero_percents,
            $this->art_eff,
            $percent,
            $this->alliance_bonus);
        return $time;
    }

    /**
     * @param mixed $nr
     * @return mixed
     */
    public function getTroopWrapper($nr)
    {
        $helper = new GoldHelper();
        $training = new TrainingHelper();
        $view = new PHPBatchView('build/building/TroopBuildingTrainingWrapper');
        $view->vars['index'] = $nr;
        $view->vars['nr'] = $nr;
        $view->vars['troopKey'] = 't' . (int) $nr;
        $view->vars['showFavourite'] = (int) $this->building_id !== 36;
        $view->vars['available'] = $this->units['u' . $nr] ?? 0;
        $view->vars['smithyLevel'] = $this->smithyLevels['u' . (int) $nr] ?? 0;

        $view->vars['durationClass'] = [
            20 => 'cavalryBonusTime_medium',
            30 => 'cavalryBonusTime_medium',
            19 => 'infantryBonusTime_medium',
            29 => 'infantryBonusTime_medium',
            21 => 'siegeTime_small',
            36 => 'maxTraps_medium',
            25 => 'clock_medium',
            26 => 'clock_medium',
        ][$this->building_id];

        $view->vars['unitId'] = nrToUnitId($nr, Session::getInstance()->getRace());
        $view->vars['cost'] = Formulas::uTrainingCost($view->vars['unitId'], $this->building_id == 30 || $this->building_id == 29);
        $view->vars['upkeep'] = Formulas::uUpkeep($view->vars['unitId'], Village::getInstance()->getHorseDrinkingPoolLvl());
        $view->vars['max'] = $training->getMaxUnitByNr($nr, $this->building_id == 30 || $this->building_id == 29);
        $view->vars['duration'] = secondsToString($this->_getTroopTrainingTime($view->vars['unitId']), true);
        $view->vars['npc'] = $helper->getExchangeResourcesButtonByNr($nr, $this->building_id == 30 || $this->building_id == 29);
        $view->vars['maximumShortcut'] = SpeedShortcuts::enabled();
        $view->vars['none_status'] = '';
        $view->vars['expansion'] = in_array($this->building_id, [25, 26, 44]);
        $view->vars['notResearched'] = false;
        $view->vars['slotNeeded'] = null;
        if ($view->vars['expansion']) {
            $view->vars['showFavourite'] = false;
            $view->vars['showLevel'] = false;
            $view->vars['notResearched'] = !in_array($nr, $this->researched, true);
            $view->vars['slotNeeded'] = $this->slotNeeded((int) $nr);
        }
        if (!$view->vars['notResearched'] && $view->vars['slotNeeded'] === null) {
            ++$this->trainable;
        }
        if (!$view->vars['max']) {
            $contract = Village::getInstance()->contractResourcesLink($view->vars['cost']);
            if ($contract['code'] == -1) {
                $view->vars['none_status'] = $contract['text'];
            }
        }
        return $view->output();
    }

    /**
     * The reference's "Expansion slot needed" line for a settler or chief this building cannot train yet, or null
     * while a slot is free (or no further slot unlocks).
     */
    private function slotNeeded(int $nr): ?string
    {
        if ((new TrainingHelper())->getAvailableExpansionTraining($nr, true) > 0) {
            return null;
        }
        foreach (TrainingHelper::slotLevels((int) $this->building_id) as $level) {
            if ($level > $this->building_level) {
                $link = '<a href="/build.php?gid=' . (int) $this->building_id . '&amp;s=0">' . T('Buildings', $this->building_id . '.title') . ' </a>';

                return sprintf((string) T('ResidencePalace', 'expansionSlotNeeded'), $link, $level);
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function loadSmithyLevels(int $kid): array
    {
        if ($kid < 1) {
            return [];
        }
        $db = DB::getInstance();
        $statement = $db->run('SELECT * FROM smithy WHERE kid=?', [$kid]);
        if ($statement === false) {
            return [];
        }
        $result = $statement->get_result();
        if (!$result || $result->num_rows < 1) {
            return [];
        }
        $row = $result->fetch_assoc();
        if (!is_array($row)) {
            return [];
        }
        $levels = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, 'u')) {
                $levels[$key] = (int) $value;
            }
        }

        return $levels;
    }
}
