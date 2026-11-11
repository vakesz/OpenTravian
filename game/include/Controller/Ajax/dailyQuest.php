<?php

namespace Controller\Ajax;

use Core\Session;
use Model\DailyQuestModel;
use resources\View\PHPBatchView;

class dailyQuest extends AjaxBase
{
    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            $this->error('You must be logged in to use daily quests.');
            return;
        }

        $questId = $_POST['questId'] ?? null;
        if (!is_string($questId) || $questId === '') {
            $this->renderOverview();
            return;
        }

        if (preg_match('/^DailyQuest_(0[1-9]|1[01])$/', $questId, $quest) === 1) {
            $this->renderDetails((int)$quest[1]);
            return;
        }
        $milestone = $this->parseQuestId($questId);
        if ($milestone === null) {
            $this->error('The requested daily quest is not available.');
            return;
        }

        $this->renderReward($milestone);
    }

    /** The milestone (1 to 4) named by DailyQuestsReward_01 to _04; the request is refused for anything else. */
    private function parseQuestId(string $questId): ?int
    {
        return preg_match('/^DailyQuestsReward_0([1-4])$/', $questId, $matches) === 1 ? (int)$matches[1] : null;
    }

    private function renderOverview(): void
    {
        $view = new PHPBatchView('dailyQuest/questachivements');
        $this->response['html'] = $view->output();
        $this->response['title'] = T('DailyQuest', 'Daily Quests');
        // The current V2 dialog uses a parchment frame.  `white` is a legacy
        // utility that forces inherited dialog copy to white, which is only
        // appropriate for the retired dark frame.
        $this->response['cssClass'] = 'basic dailyQuestsDialog';
    }

    /**
     * The reward screen of one milestone, for any state: not reached yet, reachable, or already collected.
     * Only the screen's own markup is answered; the dialog puts it into its middle screen.
     */
    private function renderReward(int $number): void
    {
        $model = new DailyQuestModel();
        $uid = Session::getInstance()->getPlayerId();
        $model->setReward($uid);
        $quest = $model->getQuest($uid);
        if (!is_array($quest)) {
            $this->error('Daily quest progress is unavailable.');
            return;
        }
        $achieved = $model->calcTotalPoints($quest);
        $points = DailyQuestModel::MILESTONES[$number];
        $state = (int)$quest['reward' . $number . 'Done'] !== 0 ? 'collected' : ($achieved >= $points ? 'claimable' : 'locked');

        $view = new PHPBatchView('dailyQuest/reward');
        $view->vars = [
            'tier' => $number,
            'points' => $points,
            'achieved' => $achieved,
            'state' => $state,
            'rotation' => $model->rotation($number),
        ];
        $this->response['html'] = $view->output();
    }

    /** The details screen of one quest (DailyQuest_01 to _11): its sentence and today's progress. */
    private function renderDetails(int $id): void
    {
        $model = new DailyQuestModel();
        $quest = $model->getQuest(Session::getInstance()->getPlayerId());
        if (!is_array($quest)) {
            $this->error('Daily quest progress is unavailable.');
            return;
        }

        $view = new PHPBatchView('dailyQuest/details');
        $view->vars = ['id' => $id, 'stepId' => (int)$quest['qst' . $id]];
        $this->response['html'] = $view->output();
    }
}
