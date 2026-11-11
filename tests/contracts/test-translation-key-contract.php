<?php

declare(strict_types=1);

function failTranslationKeyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'locale' => $root . '/game/include/Core/Locale.php',
    'layout' => $root . '/game/include/resources/Templates/layout/Layout.php',
    'reports' => $root . '/game/include/Controller/ReportsCtrl.php',
    'adventures' => $root . '/game/include/Controller/HeroAdventureCtrl.php',
    'auctionBuy' => $root . '/game/include/resources/Templates/hero/AuctionBuy.php',
    'auctionBids' => $root . '/game/include/resources/Templates/hero/AuctionBids.php',
    'auctionSell' => $root . '/game/include/resources/Templates/hero/AuctionSell.php',
    'reportMenu' => $root . '/game/include/resources/Templates/reports/menu.php',
    'profile' => $root . '/game/include/resources/Templates/profile/Profile.php',
    'messageCompose' => $root . '/game/include/resources/Templates/messages/write.php',
];

foreach ($sources as $name => $path) {
    $sources[$name] = file_get_contents($path);
    if ($sources[$name] === false) {
        failTranslationKeyContract("Could not read the $name source.");
    }
}

if (!str_contains($sources['locale'], '$usesImplicitDefault = $Default === false;')
    || !str_contains($sources['locale'], 'if ($usesImplicitDefault) {')) {
    failTranslationKeyContract('Explicit translation fallbacks must not be reported as missing translations.');
}

if (str_contains($sources['layout'], 'T("inGame", "Reports")')
    || substr_count($sources['layout'], 'T("DailyQuest", "Daily Quests")') !== 3
    || !str_contains($sources['reports'], "T('inGame', 'Navigation.Reports')")) {
    failTranslationKeyContract('Navigation labels must use their existing localized keys.');
}

if (!str_contains($sources['adventures'], 'AdventurePlace::fromWorldData($worldData, $villageRace)')
    || !str_contains($sources['adventures'], "default => ['grassland', 'Plains']")
    || !str_contains($sources['adventures'], "T('HeroAdventure', \$placeNameKey, \$placeNameFallback)")) {
    failTranslationKeyContract('Unknown adventure terrain must have a readable Plains fallback.');
}

if (str_contains($sources['profile'], 'Population Rank')
    || str_contains($sources['profile'], 'Attacker Rank')
    || str_contains($sources['profile'], 'Defender Rank')
    || str_contains($sources['profile'], 'Hero Level')
    || !str_contains($sources['profile'], 'T("HeroInventory", "heroLevel")')) {
    failTranslationKeyContract('Profile rank labels must use existing localized terms.');
}

// the compose form labels the field "Languages:" (plural; B4b capture). The Messages key
// has no catalogue entry, so it must carry an explicit default instead of the singular Profile key.
if (!str_contains($sources['messageCompose'], 'T("Messages", "Languages", "Languages")')
    || str_contains($sources['messageCompose'], 'T("Profile", "Language")')) {
    failTranslationKeyContract('Message compose must use the reference "Languages" label with an explicit default.');
}

if (!str_contains($sources['auctionBids'], "T('Auction', 'Your bids')")
    || !str_contains($sources['auctionBids'], "T('Auction', 'Sold items')")
    || !str_contains($sources['auctionBids'], "T('Auction', 'Payout')")) {
    failTranslationKeyContract('Auction overview must use localized Your bids / Sold items headers.');
}

foreach (['All', 'Attacks', 'Defense', 'Spy', 'Other', 'Surrounding'] as $tab) {
    if (!str_contains($sources['reportMenu'], "T('Reports', 'Tabs.$tab')")) {
        failTranslationKeyContract("Report tab $tab must use its localized key.");
    }
}

echo "Translation-key contract passed.\n";
