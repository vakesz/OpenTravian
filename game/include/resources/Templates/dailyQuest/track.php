<?php

declare(strict_types=1);

use Model\DailyQuestModel;

require_once __DIR__ . '/icons.php';

if (!function_exists('daily_quest_track')) {
    /**
     * The progress of one daily quest as the reference draws it, in the overview row and on the details screen: a pill
     * with the points of each task (filled for the tasks done today) and the laurel with the points earned out of the
     * most the quest gives. The reference isolates every "n/total" with LRO marks: LRO LRO n PDF / LRO total PDF PDF.
     */
    function daily_quest_track(DailyQuestModel $model, int $id, int $stepId): string
    {
        $stepCount = $model->getStepCount($id);
        $perStep = $model->getQuestReward($id, 1);
        $completed = $model->isQuestCompleted($id, $stepId);
        $fraction = "\u{202D}\u{202D}" . $model->getQuestReward($id, $stepId) . "\u{202C}/\u{202D}" . $model->getTotalQuestReward($id) . "\u{202C}\u{202C}";

        $html = '<div class="taskTrack withSummary">';
        for ($step = 1; $step <= $stepCount; ++$step) {
            $html .= '<div class="pill' . ($step <= $stepId ? ' completed' : '') . '">' . daily_quest_svg('laurelIcon') . '<span>' . $perStep . '</span></div>';
        }

        return $html . '</div><div class="questAchievedPoints' . ($completed ? ' completed' : '') . '">'
            . daily_quest_svg('laurel') . '<div class="points">' . $fraction . '</div></div>';
    }
}
