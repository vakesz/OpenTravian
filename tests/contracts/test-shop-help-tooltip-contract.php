<?php

declare(strict_types=1);

use Game\ShopHelpTooltip;

/**
 * The help icon of every shop advantage card (OpenTravian Plus, Gold club, the four production bonuses) and the
 * button titles. The texts are compared character for character
 * (including the existing "You can active"); the video paragraph of the
 * boost tooltips is absent by policy. The button titles name the duration
 * ("Bonus duration in days: 3").
 */
function failShopHelpTooltipContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$bonus = "\u{202D}+\u{202D}25\u{202C}%\u{202C}";
foreach (['lumber', 'clay', 'iron', 'crop'] as $resource) {
    $html = ShopHelpTooltip::productionBoost($resource, 'Increase ' . $resource . ' production', "\u{202D}72 hours\u{202C}");
    $text = trim((string)preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', preg_replace('/<svg.*?<\/svg>/', '', $html) ?? ''))));
    $expected = 'Increase ' . $resource . ' production Increase the ' . $resource . ' production in all your villages. '
        . 'The bonus applies to the base production and all other additional bonuses too. '
        . $bonus . " for \u{202D}72 hours\u{202C} You can active the bonus with Gold and get a " . $bonus . ' production bonus. '
        . 'You can activate automatic extension for a permanent bonus duration.';
    if ($text !== $expected) {
        failShopHelpTooltipContract("The {$resource} tooltip must read as the reference:\n  got      {$text}\n  expected {$expected}");
    }
    if (!str_contains($html, 'class="tooltipHelpContainer advantage productionBoost ' . $resource . 'ProductionBonus"')
        || substr_count($html, 'class="helpText"') !== 1
        || !str_contains($html, '<span class="bonusIcon"><svg')
        || str_contains($html, 'video')) {
        failShopHelpTooltipContract("The {$resource} tooltip markup must be the reference's, without the video paragraph.");
    }
}

$rows = static function (string $html): array {
    preg_match_all('/<span class="bonusIcon ([A-Za-z0-9]+)"><\/span><strong class="title">(.*?)<\/strong><span class="desc">(.*?)<\/span>/', $html, $m, PREG_SET_ORDER);

    return array_map(static fn (array $r): array => [$r[1], $r[2], $r[3]], $m);
};
$plus = $rows(ShopHelpTooltip::plus());
$expectedPlus = [
    ['attackWarning', 'Attack warning', 'Incoming attacks will be displayed in the village list.'],
    ['buildingQueue', 'Building queue', 'The building queue allows you to queue one further construction order.'],
    ['linkList', 'Link list', 'Allows for freely definable links, which allow you to reach every page in the game with one simple click.'],
    ['villageOverview', 'Village overview', 'Overview of production, stock, culture points and troops for all your villages.'],
    ['largerMap', 'Larger map', 'For a better overview, the map can be extended to cover the whole screen and display a larger area.'],
    ['directLinks', 'Direct links', 'Customizable direct links to marketplaces, barracks, stables, and other important buildings.'],
    ['merchantsRunTwice', 'Merchants can run twice', 'Merchants can automatically repeat resource deliveries a second time.'],
];
if ($plus !== $expectedPlus || !str_contains(ShopHelpTooltip::plus(), '<h3>OpenTravian Plus</h3>')) {
    failShopHelpTooltipContract('The OpenTravian Plus tooltip rows must be the reference ones.');
}
$gold = $rows(ShopHelpTooltip::goldClub());
$expectedGold = [
    ['capitalEvasion', 'Evasion in capital', 'Own troops in your capital can be ordered to automatically flee the village from attacks via the rally point.'],
    ['farmLists', 'Farm list', 'In the rally point, you can use the farm list to manage attacks on lucrative targets.'],
    ['merchantsRun3', 'Merchants can run 3 times', 'Merchants can carry out resource transports automatically up to three times in a row.'],
    ['automaticTrade', 'Automatic trade routes', 'You can set up timed and regular resource transports between your own villages.'],
    ['cropFinder', 'Crop finder (9 and 15) on the map', 'An integrated search function on the map allows you to find slots with increased crop production and oasis bonus.'],
    ['messageArchive', 'Message and report archive', 'Important messages and reports can be archived and easily accessed later on.'],
];
if ($gold !== $expectedGold || !str_contains(ShopHelpTooltip::goldClub(), '<h3>Gold club</h3>')) {
    failShopHelpTooltipContract('The Gold club tooltip rows must be the reference ones.');
}

$icon = ShopHelpTooltip::icon('<b>x</b>');
if (!str_starts_with($icon, '<div class="tooltipHelpIcon bonusExplanation" title="&lt;b&gt;x&lt;/b&gt;"><svg viewBox="0 0 20 20">')) {
    failShopHelpTooltipContract('The icon carries its tooltip as an escaped title, which Travian.Tip turns into a tippy.');
}

$template = (string)file_get_contents($root . '/game/include/resources/Templates/payment/pros.php');
if (substr_count($template, 'ShopHelpTooltip::icon(') !== 3
    || !str_contains($template, 'ShopHelpTooltip::goldClub()') || !str_contains($template, 'ShopHelpTooltip::plus()')
    || !str_contains($template, 'ShopHelpTooltip::productionBoost($boost[\'id\']')) {
    failShopHelpTooltipContract('Every card (Gold club, Plus and the four production bonuses) ends with the help icon.');
}

// Button titles: the reference reads "Activate now<br>Bonus duration in days: <strong>3</strong>".
$title = static fn (string $section, string $key): string => (string)T($section, $key);
foreach ([['plus', 'activate now', 'Activate now'], ['plus', 'extend now', 'Extend now'],
    ['productionBoost', 'activate now', 'Activate now'], ['productionBoost', 'extend now', 'Extend now']] as [$section, $key, $verb]) {
    if (sprintf($title('inGame', $section . '.' . $key), '3') !== $verb . '<br>Bonus duration in days: <strong>3</strong>') {
        failShopHelpTooltipContract("inGame.{$section}.{$key} must read \"{$verb}<br>Bonus duration in days: <strong>%s</strong>\".");
    }
}
if ($title('inGame', 'goldClub.activate now') !== 'Activate now<br>Bonus duration: <strong>whole game round</strong>') {
    failShopHelpTooltipContract('The Gold club button title must read as the reference.');
}

echo "Shop help tooltip contract passed.\n";
