<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\TrainingQueueView;
use Game\Wounded;
use Model\TrainingModel;
use resources\View\PHPBatchView;

/**
 * Hospital (gid 46) and Asclepeion (gid 48): heal the wounded troops that the
 * village's battles produced. Healing costs the unit's training cost, takes
 * half its training time and runs in the building's own queue.
 */
class HospitalCtrl extends AnyCtrl
{
    private int $buildingId;
    private int $level;
    private int $tribe;
    private int $kid;
    /** @var int[] */
    private array $wounded = [];

    /**
     * @param mixed $index
     */
    public function __construct($index)
    {
        parent::__construct();
        $session = Session::getInstance();
        $village = Village::getInstance();
        $this->buildingId = (int)$village->getField($index)['item_id'];
        $this->level = (int)$village->getField($index)['level'];
        $this->tribe = (int)$session->getRace();
        $this->kid = (int)$village->getKid();
        $this->view = new PHPBatchView('build/building/HospitalLayout');
        if (WebService::isPost() && !$session->banned() && !Config::getInstance()->dynamic->serverFinished && !$session->isInVacationMode()) {
            $this->heal();
        }
        $this->wounded = Wounded::all($this->kid);
        $wrappers = '';
        foreach ($this->wounded as $nr => $count) {
            $wrappers .= $this->wrapper($nr, $count);
        }
        $this->view->vars['index'] = $index;
        $this->view->vars['checker'] = $session->getChecker();
        $this->view->vars['wrappers'] = $wrappers;
        $this->view->vars['hasWounded'] = $this->wounded !== [];
        $this->view->vars['queue'] = $this->queue();
    }

    private function unitId(int $nr): int
    {
        return nrToUnitId($nr, $this->tribe);
    }

    private function wrapper(int $nr, int $count): string
    {
        $unitId = $this->unitId($nr);
        $cost = Formulas::uTrainingCost($unitId);
        $view = new PHPBatchView('build/building/TroopBuildingTrainingWrapper');
        $view->vars = [
            'index' => $nr,
            'nr' => $nr,
            'unitId' => $unitId,
            // Same troopKey shape as Barracks (`t{nr}`); heal UI has no favourites.
            'troopKey' => 't' . $nr,
            'showFavourite' => false,
            'showLevel' => false,
            'available' => $count,
            'durationClass' => 'clock_medium',
            'cost' => $cost,
            'upkeep' => Formulas::uUpkeep($unitId),
            'max' => min($count, $this->affordable($cost)),
            'duration' => secondsToString(Formulas::uHealingTime($unitId, $this->level), true),
            'npc' => '',
            'none_status' => '',
        ];

        return $view->output();
    }

    /**
     * @param array<mixed> $cost
     */
    private function affordable(array $cost): int
    {
        $max = PHP_INT_MAX;
        foreach (Village::getInstance()->getCurrentResources(-1, true) as $resource => $stock) {
            if (isset($cost[$resource]) && $cost[$resource] > 0) {
                $max = min($max, (int)floor($stock / $cost[$resource]));
            }
        }

        return $max === PHP_INT_MAX ? 0 : $max;
    }

    private function heal(): void
    {
        if (!Session::claimAction($_REQUEST['z'] ?? null)) {
            return;
        }
        $village = Village::getInstance();
        $model = new TrainingModel();
        foreach (Wounded::all($this->kid) as $nr => $count) {
            $requested = isset($_REQUEST['t' . $nr]) ? abs((int)$_REQUEST['t' . $nr]) : 0;
            $unitId = $this->unitId($nr);
            $cost = Formulas::uTrainingCost($unitId);
            $num = min($requested, $count, $this->affordable($cost));
            if ($num <= 0) {
                continue;
            }
            foreach ($cost as &$amount) {
                $amount *= $num;
            }
            unset($amount);
            if (!$village->isResourcesAvailable($cost)) {
                continue;
            }
            $kid = $this->kid;
            $buildingId = $this->buildingId;
            $healingTime = Formulas::uHealingTime($unitId, $this->level);
            $village->transaction(static function () use ($village, $model, $cost, $kid, $buildingId, $nr, $num, $healingTime): bool {
                // Wounded troops are taken first so a lost race refunds nothing, and the
                // spend rolls back with the transaction if the queue row cannot be created.
                if (!$village->spend($cost) || !Wounded::take($kid, $nr, $num)) {
                    return false;
                }
                $model->enqueue($kid, $buildingId, $nr, $num, $healingTime);

                return true;
            });
        }
    }

    private function queue(): string
    {
        return TrainingQueueView::render(
            (new TrainingModel())->getTraining($this->kid, $this->buildingId),
            function (array $row, string $count): string {
                $unitId = $this->unitId((int)$row['nr']);
                $title = T('Troops', "{$unitId}.title");

                return '<img class="unit u' . $unitId . '" src="/img/spacer.gif" alt="' . $title . '" title="' . $title . '" /> '
                    . $count . ' ' . $title;
            },
            'next_healing'
        );
    }
}
