<?php

declare(strict_types=1);

function failVillageListContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxVillageList.php');
$view = file_get_contents($root . '/game/include/resources/View/GameView.php');
$svg = file_get_contents($root . '/game/include/resources/Svg/svg.php');
$groups = file_get_contents($root . '/game/include/Model/VillageGroupModel.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$villageModel = file_get_contents($root . '/game/include/Model/VillageModel.php');
$accountDeleter = file_get_contents($root . '/game/include/Model/AccountDeleter.php');
$bundles = [
    $root . '/game/deployment/public/game-client.js',
];

if (!is_string($template) || !is_string($view) || !is_string($svg) || !is_string($groups) || !is_string($routes)
    || !is_string($villageModel) || !is_string($accountDeleter)) {
    failVillageListContract('Village-list sources must be readable.');
}

foreach ([
    "T('inGame', 'villageGroups')",
    'addGroup',
    "\$vars['villageGroups'];",
    "\$vars['villageGroupCount'];",
] as $contract) {
    if (!str_contains($template, $contract)) {
        failVillageListContract('Village list must render the native village-group header and controls.');
    }
}

foreach ([
    'class VillageGroupModel',
    'public const MAX_GROUPS = 20;',
    'setLayout',
    'village_groups',
    'village_group_villages',
] as $contract) {
    if (!str_contains($groups, $contract)) {
        failVillageListContract('Village groups must retain their owner-scoped persistence contract.');
    }
}

if (!str_contains($routes, "'POST village-group' => [null, 'village-group']")
    || !str_contains($routes, "'POST village-list' => [null, 'village-list']")
    || !str_contains($routes, "'#^village-group/(?P<id>[1-9][0-9]*)$#D'")) {
    failVillageListContract('Village-group requests must stay on the legacy Ajax dispatcher.');
}

if (!str_contains($villageModel, 'DELETE FROM village_group_villages WHERE uid=? AND kid=?')
    || !str_contains($accountDeleter, 'DELETE FROM village_group_villages WHERE uid=? AND kid=?')) {
    failVillageListContract('Capture and deletion paths must remove stale village-group memberships.');
}

if (
    !str_contains($view, 'data-color="')
    || !str_contains($view, '$groupColor')
    || !str_contains($template, 'villageGroupColors')
    || !str_contains($template, 'villageGroupColor')
    || !str_contains($groups, "['grey', 'yellow', 'green', 'turquoise', 'blue', 'violet', 'magenta', 'red', 'orange']")
) {
    failVillageListContract('Village groups must expose the native colour-selection contract.');
}

if (!str_contains($template, 'id="sidebarBoxVillageList" class="sidebarBox"')
    || str_contains($template, 'sidebarBoxVillagelist')
    || str_contains($view, 'travian_toggle_villagelist')) {
    failVillageListContract('Village list must use the native expanded sidebar contract instead of the legacy collapsed variant.');
}

if (!str_contains($template, 'class="buttonsWrapper quickLinks"')
    || !str_contains($template, 'class="secondaryIconButton addGroup"')) {
    failVillageListContract('Village list header must use native quickLinks geometry and addGroup control.');
}

if (!str_contains($template, 'textButtonV2 buttonFramed layoutButton villageStatistics round withIcon green')
    || !str_contains($template, 'href="/village/statistics"')
    || !str_contains($template, 'redirectUrl":"\\/village\\/statistics"')
    || str_contains($template, 'href="/dorf3.php"')) {
    failVillageListContract('Village-list quick links must use the reference village-statistics control instead of the obsolete overview button.');
}

foreach ([
    'class="quickLinkSlot"',
    'data-slot-index="<?= $quickLinkSlot; ?>"',
    'class="editSlotIndicator"',
    'layoutButton settings round withIcon grey',
    'quickLinksDialog',
    'quickLinksWrapper',
    'data-quick-links',
    'Travian.Game.Preferences.set(\'villageQuickLinks\'',
    'dragstart',
    'drop',
] as $contract) {
    if (!str_contains($template, $contract)) {
        failVillageListContract('Village-list quick-link slots and settings control must retain the reference shell.');
    }
}

if (str_contains($template, 'Open quick links settings') || str_contains($template, 'Add village group')) {
    failVillageListContract('Village-list icon controls must not add local-only accessible labels.');
}

if (!str_contains($template, 'slots of your village list. The set of quick links is the same for every village, but the state and tooltip reflect the active village')
    || str_contains($template, 'state and tooltip reflect the active village.')) {
    failVillageListContract('The village-list quick-link dialog subtitle must exactly match punctuation.');
}

$activeVillageTemplate = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxActiveVillages.php');
$fixesCss = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
if (!is_string($activeVillageTemplate) || !is_string($fixesCss)) {
    failVillageListContract('Active-village template and overrides.css must be readable.');
}
if (!str_contains($activeVillageTemplate, 'class="buttonsWrapper quickLinks"')
    || !str_contains($fixesCss, '#sidebarBoxActiveVillage .buttonsWrapper.quickLinks')
    || !str_contains($fixesCss, '#sidebarBoxVillageList .buttonsWrapper.quickLinks')
    || !str_contains($fixesCss, 'grid-template-columns: repeat(5, 44px) 30px;')
    || !str_contains($fixesCss, '#sidebarBoxVillageList .buttonsWrapper.quickLinks .layoutButton.settings')
    || !str_contains($fixesCss, '#sidebarBoxVillageList .quickLinkSlot .layoutButton')) {
    failVillageListContract('Shared-shell quickLinks geometry overrides must remain in place.');
}

$preferences = (string)file_get_contents($root . '/game/include/Core/Helper/PreferencesHelper.php');
if (!str_contains($view, "\$data['quickLinks'] = (string) PreferencesHelper::getPreference('villageQuickLinks');")
    || !str_contains($view, "\$data['quickLinkActions'] = \$this->getQuickLinkActions();")
    || !str_contains($preferences, 'DEFAULT_VILLAGE_QUICK_LINKS')
    || !str_contains($preferences, "'villageQuickLinks' => self::DEFAULT_VILLAGE_QUICK_LINKS")
    || !str_contains($preferences, "case 'villageQuickLinks':")
    || !str_contains($view, "'RallyPointSendTroops'")
    || !str_contains($view, "'MarketplaceSendResources'")
    || !str_contains($view, "'ExpansionTrain'")
    || !str_contains($preferences, "'MarketplaceSendResources'")
    || str_contains($preferences, "'marketplace-send-resources'")) {
    failVillageListContract('Village quick-link selections must use the signed-in player preference and render the full active-village catalog.');
}
if (!str_contains($preferences, 'MarketplaceSendResources')
    || !str_contains($preferences, 'BarracksManagement')
    || !str_contains($preferences, 'StableManagement')
    || !str_contains($preferences, 'WorkshopManagement')
    || !str_contains($preferences, 'array_slice($links, 0, 5)')) {
    failVillageListContract('villageQuickLinks must default to the Marketplace/Barracks/Stable/Workshop set and allow five slots.');
}

if (
    !str_contains($view, '$ownedVillageCount = (int) $this->session->get(\'total_villages\');')
    || !str_contains($view, '$maxVillageCount = Formulas::countCPVillages($currentCP);')
    || !str_contains($view, '$data[\'ownedVillageCount\'] = $ownedVillageCount;')
    || !str_contains($view, '$data[\'maxVillageCount\'] = $maxVillageCount;')
    || !str_contains($template, '$vars[\'ownedVillageCount\']')
    || !str_contains($template, '$vars[\'maxVillageCount\']')
) {
    failVillageListContract('Village count must display owned villages before culture-point-supported village capacity.');
}

if (str_contains($preferences, 'travian_toggle_villagelist')) {
    failVillageListContract('Village list must not retain the removed collapsed-sidebar preference.');
}

foreach ([
    "\$sortId = 'village' . (int) \$row['kid'];",
    'class="dropContainer" data-sortid="',
    'class="listEntry village ',
    'data-sortid="',
] as $contract) {
    if (!str_contains($view, $contract)) {
        failVillageListContract('Village-list rows must retain the native list and sort identity contract.');
    }
}

if (!str_contains($view, "'?newdid=' .")
    || !str_contains($view, "\$villageSwitchPath = '/dorf1.php'")
    || str_contains($view, "'<a href=\"?newdid='")) {
    failVillageListContract('Village switch links must keep a root-absolute current-page path (dorf1.php fallback) so they cannot resolve to the login controller.');
}

foreach ([
    "\$requestParts = parse_url((string) (\$_SERVER['REQUEST_URI'] ?? ''));",
    "parse_str(\$requestParts['query'], \$villageSwitchParams);",
    "foreach (['newdid', 'a', 'c', 'd', 'action', 'buildmaster', 'checksum', 'new_building_level'] as \$parameter)",
    "\$villageSwitchQuery = http_build_query(array_merge(\$villageSwitchParams, \$this->newVillagePrefix));",
    "\$villageSwitchQuery !== '' ? '&' . \$villageSwitchQuery : ''",
] as $contract) {
    if (!str_contains($view, $contract)) {
        failVillageListContract('Village switches must retain canonical query-driven view state while stripping action/checksum parameters.');
    }
}

foreach ([
    'BlacksmithManagement', 'RallyPointOverview', 'RallyPointSendTroops',
    'RallyPointFarmList', 'MarketplaceSendResources', 'MarketplaceTradeRoutes',
    'MarketplaceBuy', 'BarracksManagement', 'GreatBarracksManagement',
    'StableManagement', 'GreatStableManagement', 'WorkshopManagement',
    'TownHallCelebration', 'TrapperManagement', 'HerosMansionManagement',
    'HospitalManagement', 'ExpansionTrain',
] as $quickLinkIcon) {
    if (!str_contains($svg, "quickLink_{$quickLinkIcon}")
        || !str_contains($view, "'{$quickLinkIcon}'")) {
        failVillageListContract('Village quick links must keep the exact reference SVG catalog.');
    }
}

if (!str_contains($template, 'append(action.svg ||')
    || str_contains($template, '#sidebarBoxActiveVillage .layoutButton.')) {
    failVillageListContract('Village quick links must render the recovered reference SVG with no native active-village clone fallback.');
}

foreach ($bundles as $bundlePath) {
    $bundle = file_get_contents($bundlePath);
    if (!is_string($bundle)
        || !str_contains($bundle, '#sidebarBoxVillageList')
        || str_contains($bundle, '#sidebarBoxVillagelist')) {
        failVillageListContract('Village-list client selectors must target the native component ID.');
    }
}

echo "Village-list native sidebar contract passed.\n";
