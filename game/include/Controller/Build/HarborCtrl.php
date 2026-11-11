<?php

namespace Controller\Build;

use Controller\AnyCtrl;
use Core\Config;
use Core\Database\DB;
use Core\Helper\WebService;
use Core\Session;
use Core\Village;
use Game\Ships;
use Game\TrainingQueueView;
use Model\TrainingModel;
use resources\View\PHPBatchView;

/**
 * Harbor (gid 49): build, list and destroy ships.
 *
 * Ships queue as training rows (item_id 49, nr 100 + ship type) and are credited
 * to units.ship1..ship4 by TrainingModel. The Harbor level caps every ship the
 * village owns, wherever it is (Knowledge Base articles 24 and 198).
 */
class HarborCtrl extends AnyCtrl
{
    private int $level;
    private int $kid;

    /**
     * @param mixed $index
     */
    public function __construct($index)
    {
        parent::__construct();
        $village = Village::getInstance();
        $this->level = (int)$village->getField($index)['level'];
        $this->kid = (int)$village->getKid();
        if (WebService::isPost() && !Session::getInstance()->banned() && !Session::getInstance()->isInVacationMode()
            && !Config::getInstance()->dynamic->serverFinished) {
            $this->process();
        }
        $this->view = new PHPBatchView("build/building/Harbor");
        $this->fillView($index);
    }

    private function process(): void
    {
        if (!Session::claimAction($_REQUEST['z'] ?? null)) {
            return;
        }
        $village = Village::getInstance();
        $model = new TrainingModel();
        $db = DB::getInstance();
        if (isset($_REQUEST['destroy'])) {
            foreach (Ships::types() as $type) {
                $destroy = min(abs((int)($_REQUEST['d' . $type] ?? 0)), Ships::homeShips($this->kid)[$type]);
                if ($destroy > 0) {
                    $column = Ships::column($type);
                    $db->query("UPDATE units SET $column=$column-$destroy WHERE kid={$this->kid} AND $column>=$destroy");
                }
            }
            return;
        }
        foreach (Ships::types() as $type) {
            $room = Ships::buildRoom($this->kid, $this->level);
            $num = min($room, abs((int)($_REQUEST['t' . $type] ?? 0)));
            if ($num <= 0) {
                continue;
            }
            $cost = array_map(static fn (int $value): int => $value * $num, Ships::cost($type));
            if (!$village->isResourcesAvailable($cost)) {
                continue;
            }
            $kid = $this->kid;
            $buildTime = Ships::buildTime($type, $this->level);
            $village->transaction(static function () use ($village, $model, $cost, $kid, $type, $num, $buildTime): bool {
                if (!$village->spend($cost)) {
                    return false;
                }
                $model->addTraining($kid, Ships::HARBOR_GID, Ships::TRAINING_NR_OFFSET + $type, $num, $buildTime);

                return true;
            });
        }
    }

    private function maxAffordable(int $type, int $room): int
    {
        $max = $room;
        foreach (Village::getInstance()->getCurrentResources(-1, true) as $resource => $stock) {
            $max = min($max, (int)floor($stock / Ships::cost($type)[$resource]));
        }

        return max(0, $max);
    }

    private function fillView(int $index): void
    {
        $session = Session::getInstance();
        $home = Ships::homeShips($this->kid);
        $away = Ships::awayShips($this->kid);
        $queued = Ships::queuedShips($this->kid);
        $room = Ships::buildRoom($this->kid, $this->level);
        $rows = [];
        foreach (Ships::types() as $type) {
            $rows[] = [
                'type' => $type,
                'unitId' => 900 + $type,
                'tribe' => (int)$session->getRace(),
                'home' => $home[$type],
                'cost' => Ships::cost($type),
                'duration' => secondsToString(Ships::buildTime($type, $this->level), true),
                'max' => $this->maxAffordable($type, $room),
                'units' => Ships::unitCapacity($type),
            ];
        }
        $tribe = (int)$session->getRace();
        $queue = TrainingQueueView::render(
            (new TrainingModel())->getTraining($this->kid, Ships::HARBOR_GID),
            static function (array $row, string $count) use ($tribe): string {
                $type = (int)$row['nr'] - Ships::TRAINING_NR_OFFSET;
                $unitId = 900 + $type;

                return '<i class="unit_small ' . ($type == 3 ? '' : 'tribe' . $tribe) . ' t' . $unitId . '"></i> '
                    . $count . ' ' . T('Troops', $unitId . '.title');
            },
            'next_ship'
        );
        $this->view->vars = array_merge($this->view->vars, [
            'index' => $index,
            'checker' => $session->getChecker(),
            'level' => $this->level,
            'maxShips' => Ships::maxShips($this->level),
            'owned' => array_sum($home) + $away,
            'away' => $away,
            'queuedShips' => $queued,
            'room' => $room,
            'rows' => $rows,
            'queue' => $queue,
            'tribe' => (int)$session->getRace(),
        ]);
    }
}
