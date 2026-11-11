<?php

namespace Controller\Ajax\progressiveTasks;

use Controller\Ajax\AjaxBase;
use Game\Hero\HeroHud;
use Game\Tasks\TaskBoard;
use Model\TasksModel;

/**
 * Collects one progressive-task milestone. The payload is the reference one
 * ({questType, scope, targetLevel, heroLevel, buildingId?}); it is resolved to
 * the local task catalogue and claimed through TasksModel::collect().
 */
class collectReward extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot collect task rewards from this session.', 403);
            return;
        }

        $questType = $_POST['questType'] ?? '';
        $scope = $_POST['scope'] ?? '';
        $targetLevel = filter_var($_POST['targetLevel'] ?? null, FILTER_VALIDATE_INT);
        $buildingId = isset($_POST['buildingId']) && $_POST['buildingId'] !== ''
            ? filter_var($_POST['buildingId'], FILTER_VALIDATE_INT)
            : null;
        if (!is_string($questType) || preg_match('/^[A-Za-z]{1,64}$/', $questType) !== 1
            || !in_array($scope, ['spawnVillage', 'general'], true)
            || $targetLevel === false || $targetLevel < 1
            || $buildingId === false) {
            $this->error('Invalid task reward request.');
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $kid = $scope === 'general' ? 0 : (int)$this->session->getKid();
        $model = new TasksModel();
        $task = TaskBoard::resolve($model, $uid, $scope === 'general' ? 'general' : 'village', $kid, $questType, $buildingId, (int)$targetLevel);
        if ($task === null || !$model->collect($uid, $kid, $task['key'], $task['milestone'])) {
            $this->error('This task reward is no longer available.');
            return;
        }

        $this->response['hero'] = HeroHud::heroBlock();
        $this->response['freeGold'] = $task['key'] === TasksModel::FREE_GOLD_KEY;
    }
}
