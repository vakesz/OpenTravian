<?php

declare(strict_types=1);

/**
 * Contract: authenticated top bar uses currency SVGs and
 * nav#outOfGame layoutButtons — not legacy goldCoin_medium icons or ul#outOfGame.
 */

function failTopBarOutOfGameContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$coin = file_get_contents($root . '/game/include/resources/Templates/layout/currencyCoin.php');
$out = file_get_contents($root . '/game/include/resources/Templates/layout/outOfGame.php');
$goldSvg = file_get_contents($root . '/assets/gpack/travian/mainPage/img_common/hud/top-bar/currency/gold-coin.svg');
$silverSvg = file_get_contents($root . '/assets/gpack/travian/mainPage/img_common/hud/top-bar/currency/silver-coin.svg');

foreach ([$layout, $coin, $out, $goldSvg, $silverSvg] as $source) {
    if ($source === false) {
        failTopBarOutOfGameContract('Unable to read top-bar shell templates or currency SVGs.');
    }
}

if (!str_contains($layout, "require __DIR__ . '/currencyCoin.php'")
    || !str_contains($layout, "require __DIR__ . '/outOfGame.php'")) {
    failTopBarOutOfGameContract('Layout must include currencyCoin and outOfGame partials.');
}

if (!str_contains($layout, 'id="header" class="referAFriend"')
    || !str_contains($layout, 'layoutButton buttonFramed withIcon round referAFriend green')) {
    failTopBarOutOfGameContract('Header must use referAFriend chrome and the green referAFriend layoutButton.');
}

if (!str_contains($layout, "ajaxReplaceableGoldAmount")
    || !preg_match(
        '/currencyCoinKind = \'gold\'.*?ajaxReplaceableGoldAmount.*?currencyCoinKind = \'silver\'.*?ajaxReplaceableSilverAmount/s',
        $layout
    )) {
    failTopBarOutOfGameContract('Layout currency order must be gold SVG, gold value, silver SVG, silver value.');
}

if (str_contains($layout, 'goldCoin_medium')
    || str_contains($layout, 'silverCoin_medium')
    || str_contains($layout, '<ul id="outOfGame"')
    || str_contains($layout, 'seasonPassEntry')) {
    failTopBarOutOfGameContract('Remove legacy currency icons, ul#outOfGame, and the Season Pass HUD bookmark from Layout.');
}

if (!str_contains($coin, 'hud/top-bar/currency/')
    || !str_contains($coin, 'goldCoin')
    || !str_contains($coin, 'silverCoin')) {
    failTopBarOutOfGameContract('currencyCoin must load Gpack goldCoin/silverCoin SVGs.');
}

if (!str_contains($goldSvg, 'class="goldCoin"')
    || !str_contains($silverSvg, 'class="silverCoin"')
    || str_contains($goldSvg, '<?')
    || str_contains($silverSvg, '<?')) {
    failTopBarOutOfGameContract('Gpack currency SVGs must be pure recovered markup with goldCoin/silverCoin classes.');
}

if (!str_contains($out, '<nav id="outOfGame">')
    || !str_contains($out, 'layoutButton buttonFramed withIcon round profile grey')
    || !str_contains($out, 'layoutButton buttonFramed withIcon round options grey')
    || !str_contains($out, 'layoutButton buttonFramed withIcon round logout grey')
    || !str_contains($out, 'layoutButton buttonFramed withIcon round help grey')
    || str_contains($out, 'openHelpDialog')
    || !str_contains($out, "Travian.Game.Manual.open(); return false;")
    || strpos($out, 'round options grey') > strpos($out, 'round help grey')
    || strpos($out, 'round help grey') > strpos($out, 'round logout grey')
    || str_contains($out, 'class="discord"')) {
    failTopBarOutOfGameContract('outOfGame must contain profile, options, contextual-manual help, and logout round grey layoutButtons in that order.');
}

$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
if ($fixes === false
    || str_contains($fixes, 'buildingsV3')
    || !str_contains($fixes, '.buttonFramed.withIcon svg')
    || !str_contains($fixes, 'position: relative')) {
    failTopBarOutOfGameContract('overrides.css must not carry buildingsV3 rules and must keep relative framed-icon SVGs.');
}

echo "Top-bar outOfGame contract passed.\n";
