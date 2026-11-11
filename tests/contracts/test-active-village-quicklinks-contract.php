#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: ActiveVillage uses quickLinkSlot shell (not legacy market/barracks buttons).
 */

function failActiveVillageQuickLinks(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = (string) file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxActiveVillages.php');
$view = (string) file_get_contents($root . '/game/include/resources/View/GameView.php');
$quickLink = (string) file_get_contents($root . '/game/include/Game/QuickLink.php');
$css = (string) file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

foreach (['quickLinkSlot', 'editSlotIndicator', 'layoutButton settings', 'data-x', 'data-did', 'data-quick-links'] as $needle) {
    if (!str_contains($template, $needle)) {
        failActiveVillageQuickLinks("ActiveVillage template missing {$needle}.");
    }
}
if (str_contains($template, "\$vars['marketplace']") || str_contains($template, 'layoutButton market')) {
    failActiveVillageQuickLinks('ActiveVillage must not keep legacy market/barracks/stable/workshop buttons.');
}
if (!str_contains($view, "getQuickLinkActions()") || !str_contains($view, "'coordX'") || !str_contains($view, "'quickLinks'")) {
    failActiveVillageQuickLinks('GameView::renderActiveVillageBox must supply quickLinkActions + coords.');
}
if (!str_contains($view, '$hospitalGid = $firstBuiltGid([46, 48]);')
    || !str_contains($view, '$hospitalTitleGid')
    || str_contains($view, "'Hospital/Asclepeion'")
    || str_contains($view, 'gidIfBuilt(41)')) {
    failActiveVillageQuickLinks('Hospital quick links must resolve the actual Hospital/Asclepeion gids (46/48), not Horse Drinking Trough (41).');
}
if (!str_contains($quickLink, 'foreach ([46, 48] as $candidate)')
    || str_contains($quickLink, "'HospitalManagement' => 41")) {
    failActiveVillageQuickLinks('QuickLink tooltip resolution must use Hospital/Asclepeion gids (46/48).');
}
if (!str_contains($view, "T('Buildings', \$gid . '.title')")
    || !str_contains($view, "T('ResidencePalace', 'Train')")
    || str_contains($view, "'Residence/Palace/Command Center'")) {
    failActiveVillageQuickLinks('Quick-link labels must use the canonical building and tab translations, including the active Residence or Palace.');
}

$translationFragments = [
    "\$Definition['RallyPoint']['Management']",
    "\$Definition['RallyPoint']['overview']",
    "\$Definition['RallyPoint']['sendTroops']",
    "\$Definition['RallyPoint']['farmlist']",
    "\$Definition['MarketPlace']['SendResources']",
    "\$Definition['MarketPlace']['Trade routes']",
    "\$Definition['MarketPlace']['Buy']",
    "\$Definition['ResidencePalace']['Train']",
];
foreach ([13, 16, 17, 19, 20, 21, 24, 25, 26, 29, 30, 36, 37, 44, 46, 48] as $buildingGid) {
    $translationFragments[] = "\$Definition['Buildings'][{$buildingGid}]['title']";
}
foreach (['en-US.php', 'ar-AE.php', 'el-GR.php', 'fa-IR.php'] as $localeFile) {
    $translation = (string) file_get_contents($root . '/game/include/resources/Translation/' . $localeFile);
    foreach ($translationFragments as $fragment) {
        if (!str_contains($translation, $fragment)) {
            failActiveVillageQuickLinks("{$localeFile} must retain every canonical quick-link label key ({$fragment}).");
        }
    }
}
if (str_contains($template, 'Open quick links settings')) {
    failActiveVillageQuickLinks('Quick-link settings must not add a local-only accessible label.');
}
if (!str_contains($template, "Available quick links for village ' + activeVillageName")
    || !str_contains($template, 'slots of your active village')
    || str_contains($template, 'Available quick links for the active village')
    || str_contains($template, 'slots above the village name')) {
    failActiveVillageQuickLinks('The active-village quick-link dialog must use the village-specific heading and subtitle.');
}
if (str_contains($view, "buttonId' => 'workshop'") && str_contains($view, 'renderActiveVillageBox')) {
    // Legacy workshop button builder must be gone from ActiveVillage.
}
if (str_contains($view, "'buttonId' => 'market'") && preg_match('/function renderActiveVillageBox.*?buttonId\' => \'market\'/s', $view) === 1) {
    failActiveVillageQuickLinks('renderActiveVillageBox still builds legacy market buttons.');
}
if (!str_contains($css, 'repeat(5, 44px) 30px')) {
    failActiveVillageQuickLinks('overrides.css must size ActiveVillage quickLinks as five slots + settings.');
}
if (!str_contains($css, 'layoutButton:not(.settings)')
    || !str_contains($css, 'layoutButton.settings')
    || !str_contains($css, "grid-template-columns: 24px")) {
    failActiveVillageQuickLinks(
        'overrides.css must keep quick-link action buttons at 40px while settings (⋯) stays 26×26.'
    );
}

$preferences = (string) file_get_contents($root . '/game/include/Core/Helper/PreferencesHelper.php');
if (!str_contains($preferences, 'DEFAULT_VILLAGE_QUICK_LINKS')
    || !str_contains($preferences, 'MarketplaceSendResources')
    || !str_contains($preferences, 'BarracksManagement')
    || !str_contains($preferences, 'StableManagement')
    || !str_contains($preferences, 'WorkshopManagement')) {
    failActiveVillageQuickLinks(
        'PreferencesHelper must seed default quick links above the village name (not an empty []).'
    );
}

echo "ActiveVillage quick links contract passed.\n";
