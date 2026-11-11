<?php

use Model\DailyQuestModel;

require_once __DIR__ . '/track.php';

/**
 * The details screen of one daily quest, as the reference draws it: Back, the quest's name, its sentence, the tip
 * some quests carry, and the same progress track as the overview row. The Gpack paints the quest's picture behind it
 * from the DailyQuest_NN class.
 *
 * @var array{id: int, stepId: int} $vars
 */
$model = new DailyQuestModel();
$id = (int)$vars['id'];
$entry = ((array)T('DailyQuest', 'questData'))[$id];
$name = (string)$entry['name'];
$description = (string)$entry['description'];
$tip = (string)($entry['additionalInformation'] ?? '');
$amount = DailyQuestModel::getAllianceContributionNeededForQuest();
?>
<div id="dailyQuestsDetailsScreen" class="DailyQuest_<?= sprintf('%02d', $id); ?>">
    <button type="button" class="textButtonV2 buttonSecondary backButton withTextAndIcon rectangle withText green">
        <div>
            <svg viewBox="0 0 10.2 16.5"><path d="m.1 8.1-.1.2.1.2L7 15.9c.7.7 1.9.8 2.6.1.7-.7.8-1.8.1-2.5L5 8.3l4.7-5.2c.7-.7.7-1.9-.1-2.6-.7-.7-1.9-.7-2.6.1l-.1.1L.1 8.1z"></path></svg>
            <span><?= T('DailyQuest', 'Back'); ?></span>
        </div>
    </button>
    <h1><?= htmlspecialchars($id === 11 ? sprintf($name, $amount) : $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
    <div class="description"><?= htmlspecialchars($id === 11 ? sprintf($description, $amount) : $description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php if ($tip !== ''): ?>
        <div class="additionalInformation"><?= htmlspecialchars($tip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="dailyQuest"><?= daily_quest_track($model, $id, (int)$vars['stepId']); ?></div>
</div>
