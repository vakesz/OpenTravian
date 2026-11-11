<?php

namespace Controller\Ajax\progressiveTasks;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\Hero\HeroHud;
use Game\Tasks\TaskBoard;
use Model\TasksModel;
use resources\View\PHPBatchView;

/**
 * Fresh task-board state after a collect: reference-shaped `tasksData` and
 * `heroData`, plus server-rendered overview/detail cards by task key and
 * scope so the client never builds task markup itself.
 */
class reload extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('Invalid session.', 403);
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $kid = (int)$this->session->getKid();
        $model = new TasksModel();
        $tribe = TaskBoard::tribeClass((int)$this->session->getRace());
        $scopes = ['general' => ['general', 0], 'village' => ['village', $kid]];
        $heroBonus = $model->getHeroRewardBonus($uid);

        $tasksData = ['generalTasks' => [], 'activeVillageTasks' => [], 'activeVillageName' => htmlspecialchars((string)DB::getInstance()->fetchScalar('SELECT name FROM vdata WHERE kid=' . $kid . ' AND owner=' . $uid . ' LIMIT 1'), ENT_QUOTES, 'UTF-8')];
        $rows = ['general' => [], 'village' => []];
        $details = ['general' => [], 'village' => []];
        foreach ($scopes as $name => [$scope, $scopeKid]) {
            $list = [];
            foreach (TaskBoard::groups($model, $uid, $scope, $scopeKid) as $key => $group) {
                $rows[$name][$key] = PHPBatchView::render('tasks/row', ['group' => $group, 'tribe' => $tribe, 'scope' => $scope]);
                $details[$name][$key] = PHPBatchView::render('tasks/detail', [
                    'group' => $group,
                    'tribe' => $tribe,
                    'scope' => $scope,
                    'heroBonus' => $heroBonus,
                ]);
                $list[] = [
                    'taskIndex' => $key,
                    'type' => TaskBoard::referenceType($group['current']),
                    'name' => $group['label'],
                    'levels' => array_map(static fn (array $milestone): array => [
                        'level' => (int)$milestone['milestone'],
                        'readyToBeCollected' => (bool)$milestone['eligible'] && !$milestone['claimed'],
                        'wasCollected' => (bool)$milestone['claimed'],
                        'rewardValues' => $milestone['reward'],
                    ], $group['milestones']),
                ];
            }
            $tasksData[$name === 'general' ? 'generalTasks' : 'activeVillageTasks'] = $list;
        }

        $this->response['tasksData'] = $tasksData;
        $this->response['heroData'] = HeroHud::heroBlock();
        $this->response['rows'] = $rows;
        $this->response['details'] = $details;
        $this->response['rewardBonus'] = PHPBatchView::render('tasks/rewardBonus', ['heroBonus' => $heroBonus]);
    }
}
