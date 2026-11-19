<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use Model\DailyQuestModel;
use Model\VillageModel;
use resources\View\PHPBatchView;

class TownhallCtrl extends AnyCtrl
{
    /**
     * @param mixed $index
     */
    public function __construct($index)
    {
        parent::__construct();
        $level = Village::getInstance()->getField($index)['level'];
        $this->view = new PHPBatchView("build/building/Townhall");
        $village = Village::getInstance();
        $helper = new GoldHelper();
        $this->view->vars['smallCelebration']['active'] = $village->getCP() > 0;
        $this->view->vars['smallCelebration']['points'] = Formulas::getCelebrationMaxCP(false, $village->getCP());
        $this->view->vars['smallCelebration']['cost'] = Formulas::celebrationCost(FALSE);
        $this->view->vars['smallCelebration']['time'] = secondsToString(Formulas::celebrationTime(FALSE, $level));
        if (Village::getInstance()->isResourcesAvailable($this->view->vars['smallCelebration']['cost'])) {
            $this->view->vars['smallCelebration']['exchangeButton'] = $helper->getExchangeResourcesButtonByCost($this->view->vars['smallCelebration']['cost']);
        } else {
            $this->view->vars['smallCelebration']['exchangeButton'] = NULL;
        }
        {
            $button = $this->getButton($index, FALSE);
            $this->view->vars['smallCelebration']['contractLink'] = $button['text'];
            $this->view->vars['smallCelebration']['npc'] = $button['npc'];
        }
        $this->view->vars['bigCelebration']['active'] = $level >= 10;
        $this->view->vars['bigCelebration']['points'] = Formulas::getCelebrationMaxCP(true,
            $this->getTotalCulturePoints(Session::getInstance()->getPlayerId()));
        $this->view->vars['bigCelebration']['cost'] = Formulas::celebrationCost(TRUE);
        $this->view->vars['bigCelebration']['time'] = secondsToString(Formulas::celebrationTime(TRUE, $level));
        if (Village::getInstance()->isResourcesAvailable($this->view->vars['bigCelebration']['cost'])) {
            $this->view->vars['bigCelebration']['exchangeButton'] = $helper->getExchangeResourcesButtonByCost($this->view->vars['bigCelebration']['cost']);
        } else {
            $this->view->vars['bigCelebration']['exchangeButton'] = NULL;
        }
        {
            $button = $this->getButton($index, TRUE);
            $this->view->vars['bigCelebration']['contractLink'] = $button['text'];
            $this->view->vars['bigCelebration']['npc'] = $button['npc'];
        }
        if ($this->view->vars['smallCelebration']['points'] == 0 && $this->view->vars['bigCelebration']['points'] == 0) {
            $this->view = NULL;
        } else {
            //process requests.
            if (!$this->celebrationQueueFull() && Session::claimAction($_GET['z'] ?? null) && isset($_GET['type']) && in_array($_GET['type'],
                    [1, 2,])) {
                if (Session::getInstance()->banned()) {
                    $this->innerRedirect("InGameBannedPage");
                } else if (Config::getInstance()->dynamic->serverFinished) {
                    $this->innerRedirect("InGameWinnerPage");
                } else if (Session::getInstance()->isInVacationMode()) {
                    $this->redirect('/options/vacation');
                } else {
                    $type = (int)$_GET['type'];
                    $cost = Formulas::celebrationCost($type == 2);
                    if (Village::getInstance()->isResourcesAvailable($cost) && $this->view->vars[$type == 1 ? 'smallCelebration' : 'bigCelebration']['points'] && $this->view->vars[$type == 1 ? 'smallCelebration' : 'bigCelebration']['active']) {
                        $db = DB::getInstance();
                        $celebrationVillage = Village::getInstance();
                        $duration = Formulas::celebrationTime($type == 2, $level);
                        $celebrationKid = $celebrationVillage->getKid();
                        $celebrating = $celebrationVillage->transaction(static function () use ($celebrationVillage, $cost, $duration, $type, $celebrationKid): bool {
                            $row = DB::getInstance()->query('SELECT celebration, celebration2 FROM vdata WHERE kid=' . (int) $celebrationKid . ' FOR UPDATE');
                            $state = $row ? $row->fetch_assoc() : null;
                            if (!is_array($state)) {
                                return false;
                            }
                            $running = (int) $state['celebration'];
                            $alreadyQueued = (int) ($state['celebration2'] ?? 0);
                            if (($running > time() && $alreadyQueued > 0) || !$celebrationVillage->spend($cost)) {
                                return false;
                            }
                            if ($running > time()) {
                                $update = DB::getInstance()->run(
                                    'UPDATE vdata SET celebration2=?, type2=? WHERE kid=?',
                                    [$running + $duration, $type, $celebrationKid]
                                );
                            } else {
                                $update = DB::getInstance()->run(
                                    'UPDATE vdata SET celebration=?, type=? WHERE kid=?',
                                    [time() + $duration, $type, $celebrationKid]
                                );
                            }

                            return $update !== false;
                        });
                        if ($celebrating) {
                            $fresh = $db->query('SELECT celebration, type, celebration2, type2 FROM vdata WHERE kid=' . (int) $celebrationKid);
                            $saved = $fresh ? $fresh->fetch_assoc() : null;
                            if (is_array($saved)) {
                                Village::getInstance()->setCelebration($saved['celebration']);
                                Village::getInstance()->setCelebrationType($saved['type']);
                                Village::getInstance()->setCelebrationQueue((int) ($saved['celebration2'] ?? 0), (int) ($saved['type2'] ?? 0));
                            }
                            $dailyQuest = new DailyQuestModel();
                            $dailyQuest->setQuestAsCompleted(Session::getInstance()->getPlayerId(), 10);
                            if ($type == 1) {
                                $cp = Formulas::getCelebrationMaxCP(false, $village->getCP());
                            } else {
                                $cp = Formulas::getCelebrationMaxCP(true,
                                    $this->getTotalCulturePoints(Session::getInstance()->getPlayerId()));
                            }
                            $owner = Session::getInstance()->getPlayerId();
                            $db->query("UPDATE users SET cp=cp+$cp WHERE id=$owner");
                            {
                                $button = $this->getButton($index, FALSE);
                                $this->view->vars['smallCelebration']['contractLink'] = $button['text'];
                                $this->view->vars['smallCelebration']['npc'] = $button['npc'];
                            }
                            {
                                $button = $this->getButton($index, TRUE);
                                $this->view->vars['bigCelebration']['contractLink'] = $button['text'];
                                $this->view->vars['bigCelebration']['npc'] = $button['npc'];
                            }
                        }
                    }
                }
            }
        }
        $this->view->vars['isCelebration'] = $this->isCelebrationRunning();
        $queuedEnd = (int) (Village::getInstance()->get('celebration2') ?? 0);
        $this->view->vars['isCelebrationQueued'] = $queuedEnd > time();
        if ($this->view->vars['isCelebration']) {
            $this->view->vars['type'] = Village::getInstance()->getCelebrationType();
            $this->view->vars['timeLeft'] = appendTimer(Village::getInstance()->getCelebration() - time());
            $this->view->vars['endat'] = TimezoneHelper::date("H:i", Village::getInstance()->getCelebration());
        }
        if ($this->view->vars['isCelebrationQueued']) {
            $this->view->vars['queueType'] = (int) (Village::getInstance()->get('type2') ?? 0);
            $this->view->vars['queueTimeLeft'] = appendTimer($queuedEnd - time());
            $this->view->vars['queueEndat'] = TimezoneHelper::date("H:i", $queuedEnd);
        }
    }

    /**
     * @param mixed $id
     * @param bool $big
     * @return array<string, mixed>|array<string, string|null>
     */
    private function getButton($id, $big)
    {
        if ($this->celebrationQueueFull()) {
            return [
                'npc' => null,
                'text' => '<div class="errorMessage">' . T("inGame", "one celebration is running") . '</div>',
            ];
        }
        $cost = Formulas::celebrationCost($big);
        if (Village::getInstance()->isResourcesAvailable($cost)) {
            return [
                'text' => getButton([
                    "type"    => "button",
                    "class"   => "textButtonV1 green ",
                    "version" => "textButtonV1",
                    "onclick" => "window.location.href = '/build.php?id=$id&type=" . ($big ? 2 : 1) . "&z=" . Session::getInstance()->getChecker() . "'; return false;",
                    'value'   => T("inGame", "run celebration"),
                ],
                    ["data" => ["type" => "button", "version" => "textButtonV1"]],
                    T("inGame", "run celebration")),
                'npc' => null,
            ];
        }
        $contract = Village::getInstance()->contractResourcesLink($cost);
        $npc = null;
        if ($contract['code'] == -1) {
            $helper = new GoldHelper();
            $npc = $helper->getExchangeResourcesButtonByCost($cost);
        }
        return [
            'npc'  => $npc,
            'text' => $contract['text'],
        ];
    }

    private function isCelebrationRunning(): bool
    {
        return Village::getInstance()->getCelebration() > time();
    }

    private function celebrationQueueFull(): bool
    {
        return $this->isCelebrationRunning() && (int) (Village::getInstance()->get('celebration2') ?? 0) > time();
    }

    /**
     * @param mixed $uid
     * @return mixed
     */
    private function getTotalCulturePoints($uid)
    {
        $db = DB::getInstance();
        $rows = $db->query("SELECT kid, cp FROM vdata WHERE isWW=0 AND owner=" . (int) $uid);
        $cp = 0;
        while ($rows && ($row = $rows->fetch_assoc())) {
            $kid = (int) $row['kid'];
            // Article 82: a grey village's culture-point production still counts.
            // Older grey villages stored 0, so their production is read from the buildings.
            $cp += Formulas::isGrayArea($kid)
                ? (int) VillageModel::calculateVillageCulturePointsAndPopulation($kid, false)['cp']
                : (int) $row['cp'];
        }

        return $cp;
    }
}
