<?php

namespace Controller\Ajax\dailyQuest;

use Controller\Ajax\AjaxBase;
use Model\DailyQuestModel;

/**
 * Collects one daily-quest reward: POST /api/v1/daily-quest/award {action: "dailyQuest", questId: "DailyQuestsReward_01".."_04"}
 * as the reference dialog sends it. The dialog stays open, so the answer only says whether another milestone can
 * still be collected (the header indicator hides when none is left).
 */
class award extends AjaxBase
{
    public const QUEST_ID = '/^DailyQuestsReward_0([1-4])$/';

    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to use daily quests.', 403);
            return;
        }
        $questId = $_POST['questId'] ?? null;
        if (($_POST['action'] ?? null) !== 'dailyQuest' || !is_string($questId) || preg_match(self::QUEST_ID, $questId, $matches) !== 1) {
            $this->error('The requested daily quest reward is not available.');
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $model = new DailyQuestModel();
        if (!$model->claim($uid, (int)$this->session->getKid(), (int)$matches[1])) {
            $this->error('The daily reward could not be collected.');
            return;
        }
        $this->response['claimable'] = $model->hasClaimableReward($uid);
    }
}
