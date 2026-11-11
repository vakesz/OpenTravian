<?php

namespace Controller;

use Core\Database\DB;
use Core\Session;
use Game\Tasks\TaskBoard;
use Model\TasksModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

/** Renders and collects the current task-board milestones. */
class TasksCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();

        $uid = (int)$this->session->getPlayerId();
        $scope = ($_GET['t'] ?? 'village') === 'general' ? 'general' : 'village';
        $villages = $this->getOwnedVillages($uid);
        $activeKid = $this->selectVillage($villages, (int)$this->session->getKid(), $_GET['kid'] ?? null);
        $kid = $scope === 'general' ? 0 : $activeKid;

        $villageName = '';
        foreach ($villages as $village) {
            if ($village['kid'] === $activeKid) {
                $villageName = $village['name'];
                break;
            }
        }
        $model = new TasksModel();

        $groups = TaskBoard::groups($model, $uid, $scope, $kid);
        $pageCount = max(1, (int)ceil(count($groups) / TaskBoard::TASKS_PER_PAGE));
        $page = min($pageCount, max(1, (int)($_GET['page'] ?? 1)));
        $visibleGroups = array_slice($groups, ($page - 1) * TaskBoard::TASKS_PER_PAGE, TaskBoard::TASKS_PER_PAGE, true);

        $this->view = new GameView();
        // The `<h1 class="titleInHeader">` is emitted inside #tasks (tasks/main).
        $this->view->vars['titleInHeader'] = '';
        // Reference body/content tokens are `tasks` / `tasks tasksVillage|tasksGeneral`
        // (not the village perspective shells used by inventory-style pages).
        $this->view->vars['bodyCssClass'] = 'tasks';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['contentCssClass'] = $scope === 'general'
            ? 'tasks tasksGeneral'
            : 'tasks tasksVillage';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;

        $view = new PHPBatchView('tasks/main');
        $view->vars = [
            'scope' => $scope,
            'kid' => $kid,
            'activeKid' => $activeKid,
            'villageName' => $villageName,
            'villages' => $villages,
            'groups' => $visibleGroups,
            'taskCount' => count($groups),
            'page' => $page,
            'pageCount' => $pageCount,
            'heroBonus' => $model->getHeroRewardBonus($uid),
        ];
        $this->view->vars['content'] = $view->output();
    }

    protected function beforeRender(): void
    {
    }

    protected function afterRender(): void
    {
    }

    /**
     * @return array<mixed>
     */
    private function getOwnedVillages(int $uid): array
    {
        $statement = DB::getInstance()->run(
            'SELECT kid, name FROM vdata WHERE owner=? AND isFarm=0 ORDER BY created ASC, kid ASC',
            [$uid]
        );
        if (!$statement) {
            return [];
        }

        $villages = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $villages[] = ['kid' => (int)$row['kid'], 'name' => (string)$row['name']];
        }
        return $villages;
    }

    /**
     * @param array<mixed> $villages
     * @param mixed $requestedKid
     */
    private function selectVillage(array $villages, int $activeKid, $requestedKid): int
    {
        $kid = filter_var($requestedKid, FILTER_VALIDATE_INT);
        if ($kid !== false && $this->isOwnedVillage($villages, (int)$kid)) {
            return (int)$kid;
        }
        if ($this->isOwnedVillage($villages, $activeKid)) {
            return $activeKid;
        }
        return (int)($villages[0]['kid'] ?? 0);
    }

    /**
     * @param array<mixed> $villages
     */
    private function isOwnedVillage(array $villages, int $kid): bool
    {
        foreach ($villages as $village) {
            if ($village['kid'] === $kid) {
                return true;
            }
        }
        return false;
    }
}
