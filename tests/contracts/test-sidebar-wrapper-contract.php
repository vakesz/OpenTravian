<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function failSidebarWrapperContract(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failSidebarWrapperContract("Unable to read {$path}.");
    }
    return $source;
};

$layout = $read('game/include/resources/Templates/layout/Layout.php');
$gameView = $read('game/include/resources/View/GameView.php');
$alliance = $read('game/include/resources/Templates/layout/sidebarBoxAlliance.php');
$fixes = $read('assets/gpack/travian/mainPage/lang/overrides.css');

$before = strpos($layout, 'id="sidebarBeforeContent"');
$after = strpos($layout, 'id="sidebarAfterContent"');
$serverTime = strpos($layout, 'id="servertime"');
if ($before === false || $after === false || $serverTime === false || !($before < $serverTime && $serverTime < $after)) {
    failSidebarWrapperContract('#servertime must render inside #sidebarBeforeContent.');
}
if (substr_count($layout, 'class="sidebarBoxWrapper"') !== 2) {
    failSidebarWrapperContract('Both sidebars must contain exactly one div.sidebarBoxWrapper.');
}
$sidebarRegion = substr($layout, $before, strpos($layout, 'id="contentOuterContainer"', $before) - $before);
$afterRegion = substr($layout, $after, 600);
if (str_contains($sidebarRegion, 'class="clear"') || str_contains($afterRegion, 'class="clear"')) {
    failSidebarWrapperContract('Sidebars must not contain div.clear children.');
}
if (!str_contains($gameView, 'id="villageBoxes" class="contentV2"')
    || !str_contains($gameView, "vars['villageBoxes']")) {
    failSidebarWrapperContract('Active village and village list must be wrapped in #villageBoxes.contentV2.');
}
if (str_contains($gameView, "vars['sidebarAfterContent'] .= PHPBatchView::render('layout/sidebarBoxVillageList'")
    || str_contains($gameView, "'/build.php?gid=18';")) {
    failSidebarWrapperContract('Village boxes must not bypass #villageBoxes; embassy link must carry newdid.');
}

if (!str_contains($alliance, "' expanded'")) {
    failSidebarWrapperContract('The alliance box without news must carry the expanded class.');
}
if (!str_contains($alliance, '/alliance/forum') || str_contains($alliance, '/alliance?s=2')) {
    failSidebarWrapperContract('The alliance forum button must use /alliance/forum.');
}
$contentStart = strpos($alliance, '<div class="content">');
$scriptPos = strpos($alliance, '<script');
$ifPos = strpos($alliance, "<?php if (\$vars['hasNews']): ?>", $contentStart);
if ($scriptPos === false || $ifPos === false || $scriptPos < $ifPos) {
    failSidebarWrapperContract('The alliance toggle script must only render with news, so .boxTitle stays the last child.');
}

if (str_contains($fixes, 'sidebarBoxAllianceNoNews')) {
    failSidebarWrapperContract('Dead #sidebarBoxAllianceNoNews selector must stay removed.');
}

foreach (['game-client.js'] as $bundle) {
    if (!str_contains($read('game/deployment/public/' . $bundle), '.parents(".sidebarBoxWrapper")')) {
        failSidebarWrapperContract("{$bundle} must keep its sidebarBoxWrapper drag selectors.");
    }
}

echo "Sidebar wrapper contract passed.\n";
