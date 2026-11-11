<?php

declare(strict_types=1);

function failSeasonPassContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);

$requiredFiles = [
    'game/include/Model/SeasonPassModel.php',
    'game/include/Controller/SeasonalRewardsCtrl.php',
    'game/include/resources/Templates/seasonalRewards/main.php',
    'game/include/resources/Templates/seasonalRewards/seasonPass.php',
    'game/include/resources/Templates/seasonalRewards/shards.php',
    'game/include/resources/Templates/seasonalRewards/governors.php',
    'game/include/resources/Templates/seasonalRewards/rewardShapePaths.php',
];
foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        failSeasonPassContract("Missing Season Pass file: {$relative}");
    }
}

$schema = file_get_contents($root . '/database/world.sql');
if ($schema === false
    || !str_contains($schema, 'CREATE TABLE `season_pass`')
    || !str_contains($schema, 'CREATE TABLE `season_pass_missions`')
    || !str_contains($schema, 'CREATE TABLE `season_pass_shards`')
    || !str_contains($schema, 'CREATE TABLE `season_pass_claims`')
    || !str_contains($schema, 'CREATE TABLE `season_pass_governor_progress`')
    || !str_contains($schema, '`rerolled`')) {
    failSeasonPassContract('database/world.sql must define Season Pass tables including governors + mission reroll.');
}

$router = file_get_contents($root . '/game/include/mainInclude.php');
if ($router === false || !str_contains($router, "'seasonalRewards' => 'SeasonalRewardsCtrl'")) {
    failSeasonPassContract('mainInclude.php must route /seasonalRewards to SeasonalRewardsCtrl.');
}

$model = file_get_contents($root . '/game/include/Model/SeasonPassModel.php');
$controller = file_get_contents($root . '/game/include/Controller/SeasonalRewardsCtrl.php');
$tasksModel = file_get_contents($root . '/game/include/Model/TasksModel.php');
$gameCtrl = file_get_contents($root . '/game/include/Controller/GameCtrl.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$localFixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
$passTemplate = file_get_contents($root . '/game/include/resources/Templates/seasonalRewards/seasonPass.php');
$mainTemplate = file_get_contents($root . '/game/include/resources/Templates/seasonalRewards/main.php');
$shardsTemplate = file_get_contents($root . '/game/include/resources/Templates/seasonalRewards/shards.php');
$governorsTemplate = file_get_contents($root . '/game/include/resources/Templates/seasonalRewards/governors.php');
$rewardShapePaths = require $root . '/game/include/resources/Templates/seasonalRewards/rewardShapePaths.php';
$trainingModel = file_get_contents($root . '/game/include/Model/TrainingModel.php');
$buildingAction = file_get_contents($root . '/game/include/Game/Buildings/BuildingAction.php');
$goldHelper = file_get_contents($root . '/game/include/Game/GoldHelper.php');
$battleModel = file_get_contents($root . '/game/include/Model/BattleModel.php');

if ($model === false || $controller === false || $tasksModel === false || $gameCtrl === false
    || $layout === false || $localFixes === false || $passTemplate === false || $mainTemplate === false || $shardsTemplate === false
    || $governorsTemplate === false || $trainingModel === false || $buildingAction === false
    || $goldHelper === false || $battleModel === false) {
    failSeasonPassContract('Season Pass sources must be readable.');
}

if (!is_array($rewardShapePaths)
    || ($rewardShapePaths['frame'] ?? '') !== 'M167.5 212.5H.5V.5l83.41 15.9.09.02.09-.02L167.5.5z'
    || ($rewardShapePaths['inner'] ?? '') !== 'M163.5 212.5H4.5V5.63l79.41 14.96.09.02.09-.02L163.5 5.63z'
    || strlen((string)($rewardShapePaths['shapePattern'] ?? '')) < 9000) {
    failSeasonPassContract('rewardShapePaths.php must keep exact Gpack 692 single-plaque SVG geometry.');
}

if (!str_contains($model, 'declare(strict_types=1);')
    || !str_contains($model, 'MISSION_DAILY_LOGIN')
    || !str_contains($model, 'MISSION_TASK_REWARDS')
    || !str_contains($model, 'onPlayerActivity')
    || !str_contains($model, 'onTaskRewardCollected')
    || !str_contains($model, 'onMissionEvent')
    || !str_contains($model, 'rerollRandomMission')
    || !str_contains($model, 'getGovernorsBoard')
    || !str_contains($model, 'claimReward')
    || !str_contains($model, 'grantPremium')) {
    failSeasonPassContract('SeasonPassModel must expose login/task/random hooks, reroll, governors, claim, and premium grant.');
}

if (!str_contains($gameCtrl, 'onPlayerActivity(')
    || !str_contains($tasksModel, 'onTaskRewardCollected($uid)')
    || !str_contains($trainingModel, "onMissionEvent(\$ownerId, 'trainTroops')")
    || !str_contains($buildingAction, "onMissionEvent((int)\$uid, 'gainPopulation')")
    || !str_contains($buildingAction, "onMissionEvent((int)\$uid, 'gainCulturePoints')")
    || !str_contains($goldHelper, "onMissionEvent(\$uid, 'resourceBoost')")
    || !str_contains($battleModel, "onMissionEvent(\$attackerUid, 'defeatAnimals')")) {
    failSeasonPassContract('Random-mission completion hooks must be wired into training/build/boost/battle paths.');
}

if (!str_contains($controller, "contentCssClass'] = 'seasonalRewards'")
    || !str_contains($controller, 'seasonalRewards')
    || !str_contains($controller, 'rerollMission')
    || !str_contains($controller, "'governors'")
    || !str_contains($mainTemplate, 'id="seasonalRewards"')
    || !str_contains($passTemplate, 'class="seasonPass')
    || !str_contains($passTemplate, 'missionsList')
    || !str_contains($passTemplate, 'rewardsWrapper')
    || !str_contains($passTemplate, 'claimReward')
    || !str_contains($passTemplate, 'rerollMission')) {
    failSeasonPassContract('Season Pass UI must use native #seasonalRewards / .seasonPass Gpack selectors and reroll action.');
}

if (!str_contains($controller, '$board === null')
    || !str_contains($controller, '$this->redirect(\'/dorf1.php\');')) {
    failSeasonPassContract('Without an active local season, Seasonal Rewards must follow and redirect to /dorf1.php.');
}

if (str_contains($mainTemplate, 'noActiveSeason')
    || str_contains($mainTemplate, 'No active Season Pass is configured for this local world')) {
    failSeasonPassContract('The inactive local Season Pass fallback must not render an invented player-facing screen.');
}

// Native SeasonalRewards DOM contracts from imports.css selectors.
$passMarkupContracts = [
    'mission typeDaily|typeRandom on .mission' => "missionClasses = 'mission ' . \$typeClass",
    'mission icon i.typeDaily|typeRandom' => 'typeRandom',
    'seasonPassPoint icons' => 'seasonPassPoint',
    'premiumLocked icon' => 'premiumLocked',
    'rewardShape SVG frame' => 'class="rewardShape"',
    'rewardShape color stops' => 'class="colorStop1"',
    'rewardShape frame/inner/pattern' => 'class="frame"',
    'rewardShape exact frame path' => 'rewardShapePaths.php',
    'progressBar filling' => 'class="progressBar"',
    'progressState marker' => 'progressState',
    'rewardsSummary collectable' => 'collectableSummary',
    'description clamped' => 'description clamped',
    '--linearGradient reward fill' => '--linearGradient:url(#',
    'missionAction reroll control' => 'missionAction',
];
foreach ($passMarkupContracts as $label => $needle) {
    if (!str_contains($passTemplate, $needle)) {
        failSeasonPassContract("Season Pass markup contract failed ({$label}): missing {$needle}");
    }
}

if (!str_contains($mainTemplate, "PHPBatchView::render('layout/tabStrip'")
    || !str_contains($mainTemplate, 'tabGovernors')
    || !str_contains($mainTemplate, "'governors'")) {
    failSeasonPassContract('Season Pass tabs must use native contentNavi / tabItem structure and include Governors.');
}

// Tabs must not live inside #seasonalRewards (breaks seasonPass CSS grid).
$seasonalRootPos = strpos($mainTemplate, 'id="seasonalRewards"');
$naviPos = strpos($mainTemplate, "PHPBatchView::render('layout/tabStrip'");
if ($seasonalRootPos === false || $naviPos === false || $naviPos > $seasonalRootPos) {
    failSeasonPassContract('contentNavi must precede #seasonalRewards so .seasonPass grid children stay aligned.');
}

if (!str_contains($shardsTemplate, 'filterSlotsRow')
    || !str_contains($shardsTemplate, 'class="filterSlot')
    || !str_contains($shardsTemplate, "'all'")
    || !str_contains($shardsTemplate, 'class="inventory')
    || !str_contains($shardsTemplate, 'shardsInformation')
    || !str_contains($shardsTemplate, 'inventoryFilter')) {
    failSeasonPassContract('Shards tab must use native inventoryFilter / filterSlot / inventory selectors.');
}

$governorContracts = [
    'governorsBookWrapper',
    'governorsBookList',
    'governorBookCard',
    'governorPortrait',
    'governorLevelWrapper',
    'governorDetail',
    'governorDetailInner',
    'mainBuildingButton',
    'maxLevelBadge',
];
foreach ($governorContracts as $needle) {
    if (!str_contains($governorsTemplate, $needle)) {
        failSeasonPassContract("Governors tab markup contract failed: missing {$needle}");
    }
}

if (str_contains($layout, 'href="/seasonalRewards"')
    || str_contains($layout, 'seasonPassEntry')
    || str_contains($localFixes, 'seasonPassEntry')) {
    failSeasonPassContract('The HUD must match the reference-empty Season Pass shell while the route remains available directly.');
}

if (str_contains($controller, 'phpMyAdmin') || str_contains($controller, 'PaymentWizard')) {
    failSeasonPassContract('Season Pass must not reintroduce payment or phpMyAdmin stacks.');
}

if (str_contains($passTemplate, '<svg') && preg_match('/inline SVG placeholders|placeholder\.svg/i', $passTemplate) === 1) {
    failSeasonPassContract('Season Pass must not invent placeholder SVG skins beside Gpack icons.');
}

// Mission/reward artwork must come from Gpack CSS classes, not hotlinked img tags.
if (preg_match('/<img[^>]+SeasonalRewards/i', $passTemplate . $shardsTemplate . $governorsTemplate) === 1) {
    failSeasonPassContract('Use Gpack CSS icon classes instead of <img> hotlinks for SeasonalRewards art.');
}

$translationKeys = [
    'title', 'tabSeasonPass', 'tabShards', 'tabGovernors', 'claim', 'missions', 'shardsEmpty',
    'grantPremium', 'rerollMission', 'governorsTitle', 'governorGoToMainBuilding',
];
foreach (['ar-AE.php', 'el-GR.php', 'en-US.php', 'fa-IR.php'] as $translationFile) {
    $translation = file_get_contents($root . '/game/include/resources/Translation/' . $translationFile);
    if ($translation === false || !str_contains($translation, "\$Definition['SeasonPass']")) {
        failSeasonPassContract("Missing SeasonPass translations in {$translationFile}.");
    }
    foreach ($translationKeys as $key) {
        if (!str_contains($translation, "'{$key}' =>")) {
            failSeasonPassContract("Missing SeasonPass key {$key} in {$translationFile}.");
        }
    }
}

$gpackCss = file_get_contents($root . '/assets/gpack/travian/mainPage/css_ltr/imports.css');
if ($gpackCss === false
    || !str_contains($gpackCss, '#seasonalRewards.seasonPass')
    || !str_contains($gpackCss, '#seasonalRewards.shards')
    || !str_contains($gpackCss, '#seasonalRewards.governors')
    || !str_contains($gpackCss, 'views/seasonal-rewards/')
    || !str_contains($gpackCss, '.mission i.typeDaily')
    || !str_contains($gpackCss, 'i.premiumLocked')
    || !str_contains($gpackCss, 'svg.rewardShape')
    || !str_contains($gpackCss, '.seasonPassPoint')
    || !str_contains($gpackCss, 'governorsBookWrapper')
    || !str_contains($gpackCss, 'governorBookCard')) {
    failSeasonPassContract('Gpack imports must retain SeasonalRewards selectors including governors.');
}

$requiredAssets = [
    'assets/gpack/travian/mainPage/img_ltr/views/seasonal-rewards/mission-daily.png',
    'assets/gpack/travian/mainPage/img_ltr/views/seasonal-rewards/mission-random.png',
    'assets/gpack/travian/mainPage/img_ltr/views/seasonal-rewards/premium-locked.png',
    'assets/gpack/travian/mainPage/img_ltr/views/seasonal-rewards/season-pass-point-green.png',
    'assets/gpack/travian/mainPage/img_ltr/views/seasonal-rewards/shards/build-time-shortening-large.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/shards/shard-collect-header.jpg',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/cleopatra-portrait-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/apollodorus-portrait-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/teutobod-portrait-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/cleopatra-detail-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/apollodorus-detail-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/teutobod-detail-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/cleopatra-portrait-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/apollodorus-portrait-character.png',
    'assets/gpack/travian/mainPage/img_common/views/seasonal-rewards/governors/teutobod-portrait-character.png',
];
foreach ($requiredAssets as $relative) {
    if (!is_file($root . '/' . $relative)) {
        failSeasonPassContract("Missing SeasonalRewards Gpack asset: {$relative}");
    }
}

echo "Season Pass contract passed.\n";
