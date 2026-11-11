#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: an empty building site uses the card chooser.  Each available
 * building is its own `#contract_building{gid}.buildingWrapper` card; it is
 * not the old description/contract pair separated by horizontal rules.  The
 * card reuses the native upgrade block so costs, benefits, master-builder and
 * video actions remain aligned with ordinary building upgrades.
 */

function failNewBuildingChooser(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/build/buildingWrapper.php');
if ($controller === false || $template === false) {
    failNewBuildingChooser('Unable to read the new-building chooser sources.');
}

foreach ([
    "'<div id=\"build_list_soon\" class=\"build\">'",
    "'</div>'",
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failNewBuildingChooser("Soon-available buildings must stay inside the build_list_soon wrapper: {$needle}");
    }
}

if (str_contains($controller, "<hr />")) {
    failNewBuildingChooser('The new-building chooser must not insert legacy horizontal-rule separators between cards.');
}
if (!str_contains($controller, "'action' => \$soon ? null : \$this->getActionText(\$item_id)")) {
    failNewBuildingChooser('Available building cards must receive structured native action data, not a concatenated contract link.');
}
if (!str_contains($controller, '$link = \'/dorf\' . $pageNamePostfix . \'.php?id=\' . $fieldId')) {
    failNewBuildingChooser('New-building action URLs must be root-absolute under the shared document base.');
}
if (!str_contains($controller, "'benefits' => \$this->getNewBuildingBenefits(\$item_id)")) {
    failNewBuildingChooser('Available building cards must receive their level-one building benefits.');
}
if (str_contains($controller, "'infoIcon' => '\\/help.php'")) {
    failNewBuildingChooser('The new-building Gold Club dialog must not reintroduce generic help chrome.');
}
if (str_contains($controller, "\$data['soonConditions'] .= '<div class=\"contractText\">' . T(\"Buildings\", \"preRequests\")")) {
    failNewBuildingChooser('Soon-available cards must list unmet requirements directly, without the legacy Prerequisites heading.');
}
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($translation === false) {
    failNewBuildingChooser('The English catalogue must be readable.');
}
if (!str_contains($translation, "['waitLoop'] = 'waiting loop'")) {
    failNewBuildingChooser('The queue label must match the canonical lowercase "waiting loop" text.');
}

foreach ([
    '<div id="contract_building<?= (int)$vars[\'itemId\']; ?>" class="buildingWrapper">',
    '<div class="build_desc">',
    '<h2><?= $vars[\'count\'] > 1 ? $vars[\'count\'] . \'. \' : \'\'; ?><?= T("Buildings", $vars[\'itemId\'] . \'.title\'); ?></h2>',
    '<div class="upgradeBuilding">',
    '<div id="contract" class="contractWrapper">',
    '<div class="buildingBenefits">',
    '<div class="upgradeButtonsContainer<?= $hasSection2 ? \' section2Enabled\' : \'\'; ?>">',
    '<div class="section1">',
    '<div class="section2">',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failNewBuildingChooser("New-building card markup is missing the structure: {$needle}");
    }
}

foreach (['contractNew', 'contract_building23', '<div class="contractLink">'] as $legacy) {
    if (str_contains($template, $legacy)) {
        failNewBuildingChooser("Obsolete chooser markup must be removed: {$legacy}");
    }
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

use resources\View\PHPBatchView;

$view = new PHPBatchView('build/buildingWrapper');
$view->vars = [
    'count' => 2,
    'itemId' => 23,
    'cost' => [40, 50, 30, 10],
    'freeCrop' => 0,
    'npcButton' => '',
    'timeInString' => '0:01:40',
    'action' => [
        'main' => '<button class="textButtonV1 green new">Construct building</button>',
        'master' => '<button class="textButtonV1 gold">Construct with master builder</button>',
        'plus' => null,
        'noResources' => false,
        'extraModuleButton' => null,
                'waitLoop' => false,
    ],
    'benefits' => [
        ['icon' => 'culturePoints_medium', 'value' => '1', 'suffix' => ''],
        ['icon' => 'population_medium', 'value' => '0', 'suffix' => ''],
        ['icon' => 'crannyCap_small', 'value' => '300', 'suffix' => ''],
    ],
    'soon' => false,
    'soonConditions' => '',
    'raceName' => 'gaul',
];
$availableHtml = $view->output();
if (preg_match('#<div id="contract_building23" class="buildingWrapper">\s*<div class="build_desc">\s*<h2>2\. Cranny</h2>.*?</div>\s*<img class="build_logo building big g23 gaul".*?<div class="upgradeBuilding">.*?<div id="contract" class="contractWrapper">.*?<div class="buildingBenefits">.*?culturePoints_medium.*?population_medium.*?crannyCap_small.*?<div class="upgradeButtonsContainer">\s*<div class="section1">.*?Construct with master builder.*?clock_medium#s', $availableHtml) !== 1) {
    failNewBuildingChooser('Available cards must render the description, artwork, cost, benefits and split action rows in order.');
}

$view->vars['soon'] = true;
$view->vars['action'] = null;
$view->vars['soonConditions'] = '<span class="buildingCondition error">Main Building Level 10</span>';
$soonHtml = $view->output();
if (preg_match('#<div id="contract_building23" class="buildingWrapper">.*?<div id="contract" class="contractWrapper">.*?<div class="buildingBenefits">.*?<div class="upgradeButtonsContainer"><span class="buildingCondition error">Main Building Level 10</span>#s', $soonHtml) !== 1
    || str_contains($soonHtml, 'Prerequisites')) {
    failNewBuildingChooser('Soon-available cards must retain costs and benefits before their heading-free requirement row.');
}

$view->vars['soon'] = false;
$view->vars['action'] = [
    'main' => '<span class="errorMessage">Not enough resources</span>',
    'master' => '<button class="textButtonV1 gold">Construct with master builder</button>',
    'plus' => null,
    'noResources' => true,
    'extraModuleButton' => null,
    'waitLoop' => false,
];
$blockedHtml = $view->output();
if (preg_match('#<div class="upgradeBlocked">.*?Not enough resources.*?</div>.*?<div class="upgradeButtonsContainer">.*?Construct with master builder#s', $blockedHtml) !== 1) {
    failNewBuildingChooser('Resource-blocked cards must keep their Master Builder alternative.');
}

echo "New-building chooser contract passed.\n";
