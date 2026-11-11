<?php

$template = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/rallypoint/navigation.php');
$bodyTemplate = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/rallypoint/main.php');
$buildTemplate = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/build/main.php');
$controller = file_get_contents(__DIR__ . '/../../game/include/Controller/RallyPoint/RallyPointCtrl.php');
$favouriteHandler = file_get_contents(__DIR__ . '/../../game/include/Controller/Ajax/favouriteTab.php');
$buttonHelper = file_get_contents(__DIR__ . '/../../game/include/functions.general.php');
$english = file_get_contents(__DIR__ . '/../../game/include/resources/Translation/en-US.php');

if ($template === false || $bodyTemplate === false || $buildTemplate === false || $controller === false || $favouriteHandler === false || $buttonHelper === false || $english === false) {
    fwrite(STDERR, "Unable to read Rally Point navigation template.\n");
    exit(1);
}

if (!str_contains($controller, "new PHPBatchView('rallypoint/navigation')")
    || !str_contains($controller, 'addContentBeforeBuildingBody($navigationView->output())')
    || !str_contains($buildTemplate, "<?=\$vars['beforeBuildContent']; ?>")
    || !str_contains($bodyTemplate, "<?= \$vars['content']; ?>")) {
    fwrite(STDERR, "Rally Point navigation must render outside the building body like the native page.\n");
    exit(1);
}

foreach ([
    "\$Definition['RallyPoint']['normal'] = 'Normal';",
    "\$Definition['RallyPoint']['sendTroops'] = 'Send troops';",
    "\$Definition['RallyPoint']['combatSimulator'] = 'Simulators';",
    "\$Definition['RallyPoint']['farmlist'] = 'Farm List';",
] as $fragment) {
    if (!str_contains($english, $fragment)) {
        fwrite(STDERR, "Rally Point navigation wording must match the native English labels.\n");
        exit(1);
    }
}

$requiredFragments = [
    "PHPBatchView::render('layout/tabStrip'",
    "!empty(\$vars['show']) ? [0, 1, 2, 3] : [0]",
    "'featureKey' => 'farmList'",
    "\$farmList['gold'] = true;",
    "'slug' => '99'",
    "'key' => (string)\$selectedTab",
    "'selector' => '.subNavi .favor'",
    "'successGuard' => false",
    '<div class="navigationSpacer"></div>',
];

foreach ($requiredFragments as $fragment) {
    if (!str_contains($template, $fragment)) {
        fwrite(STDERR, "Missing native Rally Point tab fragment: {$fragment}\n");
        exit(1);
    }
}

foreach ([
    "'buildingRallyPoint' => [",
    "'99' => 99,",
    "isset(\$_POST['name'], \$_POST['key'])",
    "\$requestName === 'buildingRallyPoint' && \$position === 99 && !\$session->hasGoldClub()",
] as $fragment) {
    if (!str_contains($favouriteHandler, $fragment)) {
        fwrite(STDERR, "Rally Point favourite tab must preserve the keyed payload and Gold Club gate: {$fragment}\n");
        exit(1);
    }
}

$legacyFragments = [
    'class="container <?=$vars[\'tt\']==0',
    "jQuery(window).trigger('tabClicked'",
];

foreach ($legacyFragments as $fragment) {
    if (str_contains($template, $fragment)) {
        fwrite(STDERR, "Obsolete Rally Point tab markup remains: {$fragment}\n");
        exit(1);
    }
}

foreach (['sendTroops.php', 'prepareSend.php'] as $templateName) {
    $templatePath = __DIR__ . '/../../game/include/resources/Templates/rallypoint/' . $templateName;
    $template = file_get_contents($templatePath);
    if ($template === false) {
        fwrite(STDERR, "Unable to read Rally Point action template: {$templateName}.\n");
        exit(1);
    }
    if (!str_contains($template, 'getButton(')
        || (!str_contains($template, "'class' => 'textButtonV1 green'")
            && !str_contains($template, "'class' => 'textButtonV1 green '"))) {
        fwrite(STDERR, "Rally Point {$templateName} must use the shared native text-button helper.\n");
        exit(1);
    }
    if (str_contains($template, "trigger('buttonClicked'")) {
        fwrite(STDERR, "Rally Point {$templateName} must not maintain a duplicate legacy button handler.\n");
        exit(1);
    }
}

$sendTroops = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/rallypoint/sendTroops.php');
if ($sendTroops === false || str_contains($sendTroops, 'checked="checked="checked"')) {
    fwrite(STDERR, "Rally Point redeploy checkbox markup must remain valid HTML.\n");
    exit(1);
}
if (!str_contains($sendTroops, "new Travian.Game.AutoCompleter.VillageName(jQuery('#enterVillageName'))")
    || str_contains($sendTroops, "VillageName('enterVillageName')")) {
    fwrite(STDERR, "Rally Point village autocomplete must receive the input's jQuery element.\n");
    exit(1);
}

foreach ([
    "if (\$k === 'value')",
    "value=\"' . htmlspecialchars((string) \$v, ENT_QUOTES, 'UTF-8') . '\"'",
] as $fragment) {
    if (!str_contains($buttonHelper, $fragment)) {
        fwrite(STDERR, "The shared button helper must preserve submit-button values.\n");
        exit(1);
    }
}

echo "Rally Point native tab contract passed.\n";
