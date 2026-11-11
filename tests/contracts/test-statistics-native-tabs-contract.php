<?php

declare(strict_types=1);

function failStatisticsNavigationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/statistics/main.php');
$controller = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$config = file_get_contents($root . '/game/include/config.php');
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($template === false || $controller === false || $layout === false || $config === false || $fixes === false) {
    failStatisticsNavigationContract('Unable to read the statistics navigation sources.');
}

if (!str_contains($config, "'showBonusTabInStatistics'                             => false,")) {
    failStatisticsNavigationContract('The default statistics navigation must match the reference tab set without a bonus tab.');
}

$primaryNavigation = strstr($template, "<?php if (\$vars['error']", true);
if ($primaryNavigation === false) {
    failStatisticsNavigationContract('Statistics navigation must precede the page error content.');
}

foreach ([
    'layout/tabStrip',
    '$statisticsTabs = [',
    "'property' => 'playersTabProperties'",
    "'property' => 'GeneralTabProperties'",
    "'property' => 'WonderTabProperties'",
    'foreach ($statisticsTabs as $tab)',
] as $requiredFragment) {
    if (!str_contains($primaryNavigation, $requiredFragment)) {
        failStatisticsNavigationContract("Statistics primary navigation is missing {$requiredFragment}.");
    }
}

if (str_contains($primaryNavigation, "jQuery(window).trigger('tabClicked'")) {
    failStatisticsNavigationContract('Statistics primary navigation must not retain legacy per-tab bindings.');
}

$secondaryNavigation = strstr($template, "<?php if (\$selectedTabIndex < 2): ?>");
if ($secondaryNavigation === false) {
    failStatisticsNavigationContract('Statistics player and alliance views must expose their secondary navigation.');
}
$secondaryNavigation = strstr($secondaryNavigation, "<?php if (Session::getInstance()->isAdmin()) {", true);
if ($secondaryNavigation === false) {
    failStatisticsNavigationContract('Statistics secondary navigation must end before administrator-only content.');
}
foreach ([
    '<div class="contentNavi tabNavi">',
    'foreach ($statisticsSubTabs as $tab)',
    "'slug' => 'overview'",
    "'slug' => 'attackers'",
    "'slug' => 'defenders'",
    "'slug' => 'top10'",
    'favorKey<?=$tab[\'slug\']; ?>',
    'id="nestedTabFavorButton"',
    'jQuery(\'.tabNavi .favor\')',
    "key: '<?=\$selectedSubSlug; ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey",
] as $requiredFragment) {
    if (!str_contains($secondaryNavigation, $requiredFragment)) {
        failStatisticsNavigationContract("Statistics secondary navigation is missing {$requiredFragment}.");
    }
}
foreach ([
    "'name' => 'statistics'",
    "'key' => \$statisticsSlugs[\$selectedTabIndex]",
    "'selector' => '.subNavi .favor'",
    "'successGuard' => false",
] as $requiredFragment) {
    if (!str_contains($primaryNavigation, $requiredFragment)) {
        failStatisticsNavigationContract("Statistics primary favourite is missing the keyed payload: {$requiredFragment}.");
    }
}

$favouriteApi = file_get_contents($root . '/game/include/Controller/Ajax/favouriteTab.php');
if ($favouriteApi === false
    || !str_contains($favouriteApi, "'statistics' => [")
    || !str_contains($favouriteApi, "'wonderoftheworld' => 6")
    || substr_count($favouriteApi, "['overview', 'attackers', 'defenders', 'top10']") < 2) {
    failStatisticsNavigationContract('Favourite-tab API must map statistics keys to the existing numeric preference slots.');
}
if (str_contains($secondaryNavigation, 'tabFavorSubWrapper')
    || str_contains($secondaryNavigation, 'favorIcon')
    || str_contains($secondaryNavigation, "jQuery(window).trigger('tabClicked'")) {
    failStatisticsNavigationContract('Statistics secondary navigation must use the current native tab structure.');
}
if (!str_contains($fixes, 'div.statistics > .contentNavi.tabNavi .container .content {')
    || !str_contains($fixes, '  padding: 0;')) {
    failStatisticsNavigationContract('Statistics secondary tabs must not inherit legacy content padding.');
}
if (str_contains($template, 'statisticsV2') || str_contains($template, 'tabNavi statisticsSubTabs')) {
    failStatisticsNavigationContract('Statistics must not wrap its content in a local-only statisticsV2 root.');
}
if (!str_contains($template, '<div class="navigationSpacer"></div>')
    || !str_contains($template, 'banner-template-svg.svg')
    || !str_contains($template, 'in_array($selectedTabIndex, [0, 1, 3], true)')) {
    failStatisticsNavigationContract('Statistics must render the spacer and banner template SVG on player, alliance and hero tabs.');
}

foreach ([
    '$statisticsTabLabel = static function',
    "'alliances' => 'Alliance'",
    "'villages' => 'Village'",
    "'General' => 'General'",
    "'WonderOfTheWorld' => 'WonderOfTheWorld'",
] as $requiredFragment) {
    if (!str_contains($controller, $requiredFragment)) {
        failStatisticsNavigationContract("Statistics labels must retain an English scalar fallback for {$requiredFragment}.");
    }
}

if (!str_contains($controller, "'bodyCssClass'] = 'statistics'")
    || !str_contains($controller, 'public static function cleanPath')
    || str_contains($template, 'statistiken.php')) {
    failStatisticsNavigationContract('Statistics must use the reference body class and nested /statistics/* tab hrefs.');
}
foreach (['$headerHelpIsPlaceholder', '$showHeaderHelp', '$headerHelpButtonId', 'knowledgeBaseButton'] as $forbidden) {
    if (str_contains($layout, $forbidden)) {
        failStatisticsNavigationContract("Statistics must not revive a generic Knowledge Base control ({$forbidden}).");
    }
}
if (!str_contains($layout, '$closeContentHref = (string)')
    || !str_contains($layout, 'VillagePerspective::closeContentHref()')
    || !str_contains($layout, 'href="<?= htmlspecialchars($closeContentHref, ENT_QUOTES, "UTF-8") ?>"')) {
    failStatisticsNavigationContract('Statistics must render the title close control through the stored village perspective.');
}
if (!str_contains($template, 'StatisticsCtrl::cleanPath(0, 0, false)')
    || !str_contains($template, 'StatisticsCtrl::cleanPath($selectedTabIndex, $tab[\'index\'])')
    || !str_contains($template, '$selectedSubIndex = (int)$vars[\'selectedSubIndex\'];')) {
    failStatisticsNavigationContract('Statistics primary and secondary tabs must build clean nested paths.');
}

echo "Statistics native tabs contract passed.\n";
