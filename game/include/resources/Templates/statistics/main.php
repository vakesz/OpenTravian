<?php

use Core\Session;
use Controller\StatisticsCtrl;

?>
<?php
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$selectedTabIndex = (int)$vars['selectedTabIndex'];
$selectedSubIndex = (int)$vars['selectedSubIndex'];
$favoriteTabId = \Core\Session::getInstance()->getStoredFavoriteTab('statistics');
$statisticsTabs = [
    ['index' => 0, 'property' => 'playersTabProperties', 'href' => StatisticsCtrl::cleanPath(0, 0, false)],
    ['index' => 1, 'property' => 'alliancesTabProperties', 'href' => StatisticsCtrl::cleanPath(1, 0, false)],
    ['index' => 2, 'property' => 'villagesTabProperties', 'href' => StatisticsCtrl::cleanPath(2)],
    ['index' => 3, 'property' => 'heroTabProperties', 'href' => StatisticsCtrl::cleanPath(3)],
    ['index' => 5, 'property' => 'GeneralTabProperties', 'href' => StatisticsCtrl::cleanPath(5)],
];

if (getDisplay('showBonusTabInStatistics')) {
    $statisticsTabs[] = ['index' => 7, 'property' => 'BonusTabProperties', 'href' => StatisticsCtrl::cleanPath(7)];
}
if (getDisplay('showFarmsInStatistics')) {
    $statisticsTabs[] = ['index' => 8, 'property' => 'farmTabProperties', 'href' => StatisticsCtrl::cleanPath(8)];
}
$statisticsTabs[] = ['index' => 6, 'property' => 'WonderTabProperties', 'href' => StatisticsCtrl::cleanPath(6)];
?>

<?php
$statisticsSlugs = [0 => 'player', 1 => 'alliance', 2 => 'village', 3 => 'hero', 5 => 'general', 6 => 'wonderoftheworld', 7 => 'bonus', 8 => 'farm'];
$stripTabs = [];
foreach ($statisticsTabs as $tab) {
    $properties = $vars[$tab['property']];
    $stripTabs[] = [
        'slug' => $statisticsSlugs[$tab['index']],
        'label' => $properties['text'],
        'href' => $tab['href'],
        'active' => !empty($properties['active']),
    ];
}
echo \resources\View\PHPBatchView::render('layout/tabStrip', [
    'tabs' => $stripTabs,
    'favor' => true,
    'favorSlug' => $favoriteTabId === null ? null : ($statisticsSlugs[$favoriteTabId] ?? null),
    'favorButton' => [
        'name' => 'statistics',
        'key' => $statisticsSlugs[$selectedTabIndex] ?? 'player',
        'slug' => $statisticsSlugs[$selectedTabIndex] ?? 'player',
        'title' => $vars['favorText'],
        'selector' => '.subNavi .favor',
        'successGuard' => false,
    ],
]);
?>

<?php if ($vars['error'] != ""): ?>

<p class="error"><?=$vars['error']; ?></p>

<?php endif; ?><div class="navigationSpacer"></div>
<?php if ($selectedTabIndex < 2): ?>
<?php
$favoriteSubTabName = $selectedTabIndex === 0 ? 'statisticsTablePlayer' : 'statisticsTableAlly';
$favoriteSubTabIndex = \Core\Session::getInstance()->getStoredFavoriteTab($favoriteSubTabName);
$statisticsSubTabs = [
    ['index' => 0, 'slug' => 'overview', 'property' => 'overviewSubTabProperties'],
    ['index' => 1, 'slug' => 'attackers', 'property' => 'attackerSubTabProperties'],
    ['index' => 2, 'slug' => 'defenders', 'property' => 'defenderSubTabProperties'],
    ['index' => 3, 'slug' => 'top10', 'property' => 'top10SubTabProperties'],
];
$selectedSubSlug = 'overview';
foreach ($statisticsSubTabs as $tab) {
    if ($tab['index'] === $selectedSubIndex) {
        $selectedSubSlug = $tab['slug'];
    }
}
?>
<div class="contentNavi tabNavi">
    <?php foreach ($statisticsSubTabs as $tab): ?>
        <?php
        $properties = $vars[$tab['property']];
        $isActive = !empty($properties['active']);
        $isFavorite = $favoriteSubTabIndex === $tab['index'];
        $tabHref = StatisticsCtrl::cleanPath($selectedTabIndex, $tab['index']);
        ?>
        <div class="container <?=$isActive ? 'active' : 'normal'; ?>">
            <div class="background-start"></div>
            <div class="background-end"></div>
            <div class="content favor favorKey<?=$tab['slug']; ?><?=$isFavorite ? ' favorActive' : ''; ?>">
                <a id="<?=$escape($properties['id']); ?>"
                   href="<?=$escape($tabHref); ?>"
                   class="tabItem"><?=$escape($properties['text']); ?></a>
            </div>
        </div>
    <?php endforeach; ?>
    <a type="submit" id="nestedTabFavorButton"
       class="icon nestedTabRowButton"
       title="<?=$escape($vars['favorText']); ?>"
       onclick="Travian.api('favourite-tab', {data: {name: '<?=$favoriteSubTabName; ?>',key: '<?=$selectedSubSlug; ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?=$selectedSubSlug; ?>').addClass('favorActive');}});return false;" useicon="">
        <img src="/img/spacer.gif" class="&nbsp;" alt="">
    </a>
    <div class="clear"></div>
</div>
<?php endif; ?>
<?php if (in_array($selectedTabIndex, [0, 1, 3], true)): ?>
<?= (string) file_get_contents(__DIR__ . '/../alliance/banner/banner-template-svg.svg'); ?>
<?php endif; ?>

<?php if (Session::getInstance()->isAdmin()) {

    $showFakeUsers = Session::getCookie('showFakeUsers', 0);

    echo '<div>';

    echo getButton([ "type" => "button", "class" => "textButtonV1 green" . (!$showFakeUsers ? ' disabled' : null), "style" => "cursor: pointer", 'onclick' => "window.location.href='/statistics?showFakeUsers=".($showFakeUsers ? 0 : 1)."'",], ["data" => ["type" => "button", "class" => "textButtonV1 green" . (!$showFakeUsers ? ' disabled' : null)]], 'Show fake users: ' . ($showFakeUsers ? 'On' : 'Off'));

    echo '<br/>';

    echo '<br/>';

    echo '<div class="clear"></div>';

    echo '</div>';

}?><?=$vars['other']; ?>
