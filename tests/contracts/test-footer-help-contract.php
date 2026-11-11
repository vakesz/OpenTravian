<?php

declare(strict_types=1);

function failFooterHelpContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failFooterHelpContract("Unable to read {$path}.");
    }

    return $contents;
};

$layout = $read('game/include/resources/Templates/layout/Layout.php');
$guestMobileNav = $read('game/include/resources/Templates/layout/guestMobileNav.php');
$outOfGame = $read('game/include/resources/Templates/layout/outOfGame.php');
$mainInclude = $read('game/include/mainInclude.php');
$buildingManual = $read('game/include/resources/Templates/manual/building.php');
$unitManual = $read('game/include/resources/Templates/manual/unit.php');
$persianTranslations = $read('game/include/resources/Translation/fa-IR.php');

$footerLinks = $read('game/include/Game/FooterLinks.php');
if (!str_contains($layout, 'id="footer"')
    || !str_contains($layout, 'FooterLinks::build(')
    || !str_contains($footerLinks, 'getHomepageUrl()')
    || !str_contains($footerLinks, "getDiscordUrl() !== ''")
    || !str_contains($footerLinks, 'getSupportUrl()')
    || !str_contains($footerLinks, "'/homepage/news'")
    || !str_contains($footerLinks, 'LegalPages::href(')) {
    failFooterHelpContract('The footer must render Home, configured Discord, News, Support and the operator legal pages.');
}

foreach (['getNewsUrl()', 'getGameRulesUrl()', 'getTermsUrl()', 'getImprintUrl()', 'class="copyright"'] as $forbidden) {
    if (str_contains($layout, $forbidden)) {
        failFooterHelpContract("The authenticated footer must not render {$forbidden}.");
    }
}

if (!str_contains($guestMobileNav, "getDiscordUrl() !== ''")
    || !str_contains($guestMobileNav, 'getSupportTicketUrl()')) {
    failFooterHelpContract('Guest mobile navigation must retain configured Discord and Support destinations.');
}
foreach (['getTermsUrl()', 'getImprintUrl()', 'Footer.Privacy settings', 'class="copyright"'] as $forbidden) {
    if (str_contains($guestMobileNav, $forbidden)) {
        failFooterHelpContract("Guest mobile navigation must not render {$forbidden}.");
    }
}

foreach (['Travian.Game.openHelpDialog', 'Travian.React.openHelpDialog', "PHPBatchView::render('help/dialog')", 'knowledgeBaseButton', 'contextualHelpButton'] as $forbidden) {
    if (str_contains($outOfGame . $layout, $forbidden)) {
        failFooterHelpContract("Shared navigation must not expose {$forbidden}.");
    }
}

foreach (['knowledgeBaseUrl(', 'getNewsUrl(', 'getGameRulesUrl(', 'TravianAnswers'] as $forbidden) {
    if (str_contains($buildingManual . $unitManual, $forbidden)) {
        failFooterHelpContract("The contextual manual views must not render {$forbidden}.");
    }
}

if (str_contains($mainInclude, "'help.php' => 'HelpCtrl'")
    || is_file($root . '/game/include/Controller/HelpCtrl.php')
    || is_file($root . '/game/include/resources/Templates/help/dialog.php')) {
    failFooterHelpContract('The obsolete generic Help route must not remain beside contextual manual controls.');
}

foreach ([
    "\$Definition['Global']['Footer']['Discord'] = 'Discord';",
    "\$Definition['Global']['Footer']['Support'] = 'Support';",
] as $translation) {
    if (!str_contains($persianTranslations, $translation)) {
        failFooterHelpContract('The Persian footer must use the observed reference labels instead of a raw translation key.');
    }
}

echo "Footer/help contract passed.\n";
