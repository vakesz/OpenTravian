<?php

declare(strict_types=1);

function failTaskUiContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$taskKeys = [
    'categories', 'forVillage', 'yourVillage', 'general',
    'empty', 'collect', 'collected', 'pages', 'previous', 'next', 'page', 'bonusRewards',
    'bonusDescription', 'nextLevel', 'allBuildings', 'allWoodcutters', 'allClayPits', 'allIronMines', 'allCroplands',
    'fieldWoodcutters', 'fieldClayPits', 'fieldIronMines', 'fieldCroplands', 'progressToLevel', 'culturePointProduction', 'freeGold', 'freeGoldInfo', 'evenGrowth', 'evenGrowthTitle', 'completeEconomy', 'completeEconomyTitle',
    'backToOverview', 'reachPopulation', 'reachCpProduction', 'buildLevel', 'upgradeToLevel', 'oneBuildingOfLevel', 'allBuildingsOfLevel',
    'warehouseInfo', 'granaryInfo', 'barracksInfo', 'mainBuildingInfo', 'crannyInfo', 'marketInfo', 'embassyInfo', 'residenceInfo', 'rallyPointInfo', 'cityWallInfo', 'millInfo',
    'completeEconomyInfo', 'evenGrowthInfo', 'woodcuttersInfo', 'clayPitsInfo', 'cropFieldsInfo', 'ironMinesInfo', 'culturePointsProductionInfo', 'populationInfo',
];
$translationFiles = [
    // Publishes this modern task-board vocabulary only for these
    // reference locales; fa-IR has no reference catalog to copy verbatim.
    'ar-AE.php',
    'el-GR.php',
    'en-US.php',
];
foreach ($translationFiles as $translationFile) {
    $translation = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Translation/' . $translationFile);
    if ($translation === false || !str_contains($translation, '$Definition[\'Tasks\']')) {
        failTaskUiContract("Missing task-board translation section in {$translationFile}.");
    }
    foreach ($taskKeys as $taskKey) {
        if (!str_contains($translation, "'{$taskKey}' =>")) {
            failTaskUiContract("Missing task-board translation {$taskKey} in {$translationFile}.");
        }
    }
}

$mainTemplate = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/tasks/main.php');
$rowTemplate = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/tasks/row.php');
$detailTemplate = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/tasks/detail.php');
$template = $mainTemplate . $rowTemplate . $detailTemplate;
$taskBoard = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Tasks/TaskBoard.php');
$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/TasksCtrl.php') . $taskBoard;
$tasksModel = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/TasksModel.php');
$gameView = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/View/GameView.php');
$layout = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/Layout.php');
$css = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/lang/tasks.css');
$gpackCss = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/css_ltr/imports.css');
$fixesCss = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/lang/overrides.css');
$questmasterTemplate = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/sidebarBoxQuestmaster.php');
$taskBoardScript = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/tasks-board.js');
if ($template === false || $detailTemplate === false || $controller === false || $tasksModel === false || $gameView === false || $layout === false || $css === false || $gpackCss === false || $fixesCss === false || $questmasterTemplate === false || $taskBoardScript === false) {
    failTaskUiContract('Task-board source files must be readable.');
}
if (str_contains($controller, 'transitionTutorial(')
    || str_contains($gameView, "['isTutorial']")
    || str_contains($questmasterTemplate, 'isTutorial')) {
    failTaskUiContract('The maintained task board must not retain a legacy tutorial branch.');
}
if (!str_contains($rowTemplate, "T('Tasks', 'collect')")
    || !str_contains($rowTemplate, "T('Tasks', 'collected')")
    || str_contains($rowTemplate, "T('Tasks', 'collect',")
    || str_contains($rowTemplate, "T('Tasks', 'collected',")
    || str_contains($rowTemplate, "T('Tasks', 'progressToLevel',")
    || str_contains($taskBoard, "T('Tasks', \$translationKey,")
    || str_contains($template, '>Collect<')
    || str_contains($template, '>Collected<')) {
    failTaskUiContract('Task-board labels must use canonical locale translations without English fallbacks.');
}
if (!str_contains($rowTemplate, 'class="summer')
    || !str_contains($rowTemplate, 'src="img/spacer.gif"')
    || !str_contains($taskBoard, "' building g' . \$buildingId . ' ' . \$tribe")
    || !str_contains($taskBoard, "8 => 'spartan', 9 => 'viking'")
    || str_contains($template, 'buildingIllustration')
    || str_contains($template, 'get_gpack_cdn_mainPage_url()')) {
    failTaskUiContract('Task artwork must use the native shared sprite and all seven tribe classes, without an alternate illustration path.');
}
if (!str_contains($controller, 'function label(')
    || !str_contains($controller, "T('Buildings', \$m[1] . '.title')")) {
    failTaskUiContract('Task labels must use the locale-aware building names.');
}
foreach ([17 => 'Marketplace', 19 => 'Barracks', 25 => 'Residence'] as $gid => $label) {
    if (!str_contains($tasksModel, "[[{$gid}], '{$label}', [1]]")) {
        failTaskUiContract("The observed reference task catalogue must include the {$label} level-one objective.");
    }
}
if (!str_contains($tasksModel, '[1 => [2, 3, 4, 5, 8, 10], 2 => [2, 3, 4, 5, 8, 10], 3 => [2, 3, 4, 5, 8, 10], 4 => [2, 3, 4, 5, 8, 10]]')) {
    failTaskUiContract('Aggregate field tasks must start at the observed level 2-4 milestones before the later 5/8/10 targets.');
}
if (!str_contains($controller, '$achieved + $open + $collected')
    || !str_contains($controller, 'always-on-top entries precede')
    || !str_contains($controller, 'strnatcasecmp')) {
    failTaskUiContract('Task groups must promote claimable cards then open progress while preserving catalogue order inside each bucket.');
}
foreach (["'general-population'", "'general-culture'", "'free-gold'", "'alwaysOnTop' => true"] as $freeGoldContract) {
    if (!str_contains($tasksModel, $freeGoldContract)) {
        failTaskUiContract("The General task catalogue must retain the Free Gold/order contract: {$freeGoldContract}.");
    }
}
if (!str_contains($controller, "'bodyCssClass'] = 'tasks'")
    || !str_contains($controller, "'tasks tasksVillage'")
    || !str_contains($controller, "'tasks tasksGeneral'")) {
    failTaskUiContract('Task board body/content classes must match the reference tasks / tasksVillage|tasksGeneral tokens.');
}
if (!str_contains($template, '<div class="task <?= $state; ?> group <?= $e($referenceType); ?> "')
    || str_contains($template, '<article class="task')
    || !str_contains($controller, "'building' => 'buildingProgress'")) {
    failTaskUiContract('Task cards must use the reference div surface and emit its state/group/type class tokens.');
}
foreach (['$headerHelpButtonId', '$showHeaderHelp', 'knowledgeBaseButton', 'contextualHelpButton'] as $forbidden) {
    if (str_contains($layout, $forbidden)) {
        failTaskUiContract("The task board must not expose a generic knowledge-base control ({$forbidden}).");
    }
}
if (!str_contains($mainTemplate, "'bareAnchors' => true")
    || !str_contains($mainTemplate, "'href' => '/tasks?t=village'")
    || !str_contains($taskBoardScript, 'jQuery("#content").hasClass("tasksGeneral")')
    || !str_contains($taskBoardScript, '"/tasks?t=general"')
    || !str_contains($taskBoardScript, '"/tasks?t=village"')) {
    failTaskUiContract('Task tabs must keep bare-anchor markup while the task runtime provides its observed pointer routes.');
}
if (!str_contains($rowTemplate, '<div class="title"><?= $e($group[\'label\']); ?></div>')
    || str_contains($rowTemplate, 'formatMilestoneLabel')
    || str_contains($rowTemplate, 'fieldMilestone')
    || str_contains($rowTemplate, 'ucwords($label)')) {
    failTaskUiContract('Task overview cards must keep their native label and show milestones only in the progress line.');
}
if (!str_contains($template, 'class="taskOverview"')
    || !str_contains($template, 'class="pagination"')
    || !str_contains($detailTemplate, 'class="taskDetailview"')
    || !str_contains($detailTemplate, 'class="taskDescription"')
    || !str_contains($detailTemplate, 'backToOverview')
    || !str_contains($taskBoard, 'function detailMilestones(')
    || str_contains($template, 'taskOpenLink')
    || str_contains($controller, 'selectedGroup')
    || str_contains($controller, 'taskGroup')) {
    failTaskUiContract('The V2 task board must retain the native in-place detail state without a retired detail route.');
}
if (!str_contains($css, 'Task cards keep their native light surface in both world moods.')
    || str_contains($css, 'body[data-theme="night"] #tasks')) {
    failTaskUiContract('The task board must retain its native light card surface in every world mood.');
}
if (str_contains($css, '@media (max-width: 700px)')
    || str_contains($css, 'grid-template-columns: 60px')
    || str_contains($css, 'grid-row: 1 / 3;')) {
    failTaskUiContract('Task cards must keep the native four-column geometry on mobile instead of switching to a local-only responsive grid.');
}
if (!str_contains($questmasterTemplate, 'id="questmasterButton"')
    || !str_contains($questmasterTemplate, 'class="sidebarBox expanded"')
    || !str_contains($questmasterTemplate, 'class="progressiveTasksTitle"')
    || !str_contains($questmasterTemplate, '<button type="button"')
    || str_contains($questmasterTemplate, 'helperAndTasks')
    || preg_match('/id="questmasterButton"[^>]*(?:title=|aria-label=)/', $questmasterTemplate) === 1
    || !str_contains($questmasterTemplate, 'window.location.assign(')
    || !str_contains($questmasterTemplate, "'/tasks'")
    || str_contains($questmasterTemplate, 'Travian.Game.Quest.mentorClick()')
    || str_contains($questmasterTemplate, 'Travian.Game.Quest.bindListDelegation')
    || str_contains($questmasterTemplate, 'questButtonDeactivateTips')) {
    failTaskUiContract('The mentor must be a nameless button that assigns /tasks without a retired tutorial control.');
}
foreach ([1, 2, 3, 6, 7, 8, 9] as $race) {
    if (!str_contains($gpackCss, "#questmasterButton.vid_{$race} .mentor")
        || preg_match('/#questmasterButton\\.vid_' . $race . '\\s+\\.bigSpeechBubble/', $gpackCss) !== 1) {
        failTaskUiContract("The task-master must retain the native mentor and reward-marker geometry for tribe {$race}.");
    }
}
if (!str_contains($questmasterTemplate, '<div class="bigSpeechBubble newQuestSpeechBubble"')
    || str_contains($questmasterTemplate, 'class="bigSpeechBubble newQuestSpeechBubble vid_')) {
    failTaskUiContract('A pending reward must remain part of the native mentor button that opens the V2 task board.');
}
if (str_contains($questmasterTemplate, 'sidebarBoxWrapper')) {
    failTaskUiContract('The task-master must use the reference sidebar-box root without an obsolete wrapper.');
}
if (str_contains($questmasterTemplate, 'taskBoardPreview')
    || str_contains($questmasterTemplate, 'taskOverviewState')
    || str_contains($questmasterTemplate, 'taskOverviewText')
    || str_contains($tasksModel, 'getSidebarTasks')
    || str_contains($gpackCss, '.taskOverviewState')) {
    failTaskUiContract('The post-tutorial task-master must remain the native title-only entry point.');
}
if (!str_contains($gpackCss, '#questmasterButton.vid_1 .mentor')
    || !str_contains($gpackCss, '#questmasterButton.vid_9 .mentor')
    || !str_contains($gpackCss, 'hud/sidebar/tasks/mentor-roman.png')
    || !str_contains($gpackCss, 'hud/sidebar/tasks/mentor-viking.png')
    || !str_contains($fixesCss, '#questmasterButton')
    || !str_contains($fixesCss, 'pointer-events: auto;')) {
    failTaskUiContract('The claimable task widget must retain the native centred mentor geometry.');
}
if (!str_contains($questmasterTemplate, '<svg width="160" height="122" viewBox="0 0 160 122"')
    || !str_contains($questmasterTemplate, 'MentorSilhouettes::path($race)')) {
    failTaskUiContract('The task-master must use the current native mentor silhouette.');
}
if (substr_count($questmasterTemplate, '<svg') !== 1
    || substr_count($questmasterTemplate, 'class="mentor"') !== 1
    || substr_count($questmasterTemplate, 'bigSpeechBubble newQuestSpeechBubble') !== 1) {
    failTaskUiContract('The task-master must render exactly one silhouette, one tribe mentor, and one optional reward marker.');
}
$hasBubbleRule = str_contains($gpackCss, '#questmasterButton .bigSpeechBubble');
$hasBubbleZIndex = preg_match('/#questmasterButton\s*\.bigSpeechBubble\{[^}]*z-index:\s*3[;}]/', $gpackCss) === 1
    || str_contains($gpackCss, 'z-index: 3;');
$hasBubbleLeftOffsets = (
        str_contains($gpackCss, 'left: -10px;') || str_contains($gpackCss, 'left:-10px')
    ) && (
        str_contains($gpackCss, 'left: 0;') || preg_match('/#questmasterButton\.vid_9\s*\.bigSpeechBubble\{[^}]*left:\s*0[;}]/', $gpackCss) === 1
    );
if (!$hasBubbleRule || !$hasBubbleZIndex || !$hasBubbleLeftOffsets) {
    failTaskUiContract('The claimable task bubble must retain its native mentor-relative anchor.');
}
if (!str_contains($fixesCss, 'a.taskOverviewLink')
    || str_contains($fixesCss, 'ul#mentorTaskList.taskOverviewLink')) {
    failTaskUiContract('Every visible tutorial step must be an accessible V2 task-page link.');
}

// Bonus lines read LRO LRO n PDF % PDF: the number is isolated first, then the whole string.
$rewardBonus = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/tasks/rewardBonus.php');
if (!str_contains($rewardBonus, '$percent . "\u{202C}") . "\u{202C}"')) {
    failTaskUiContract('The bonus percentages must isolate the number before the percent sign, like the reference.');
}

echo "Task-board UI contract passed.\n";
