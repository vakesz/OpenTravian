<?php

declare(strict_types=1);

function failGuestLayoutRuntimeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'outOfGameView' => $root . '/game/include/resources/View/OutOfGameView.php',
    'homepage' => $root . '/game/include/Controller/HomepageCtrl.php',
    'layout' => $root . '/game/include/resources/Templates/layout/Layout.php',
    'guestMobileNav' => $root . '/game/include/resources/Templates/layout/guestMobileNav.php',
    'head' => $root . '/game/include/resources/Templates/layout/head.php',
    'quest' => $root . '/game/include/Model/Quest.php',
];

foreach ($sources as $name => $path) {
    $sources[$name] = file_get_contents($path);
    if ($sources[$name] === false) {
        failGuestLayoutRuntimeContract("Could not read the $name source.");
    }
}

if (!str_contains($sources['outOfGameView'], "\$this->vars['heroBox'] = '';")) {
    failGuestLayoutRuntimeContract('Guest pages must initialize the shared hero-box slot.');
}
if (!str_contains($sources['outOfGameView'], "\$this->vars['showGuestNavBar'] = false;")
    || !str_contains($sources['layout'], 'showGuestNavBar')
    || !str_contains($sources['layout'], "require __DIR__ . '/guestMobileNav.php';")) {
    failGuestLayoutRuntimeContract('Guest pages must opt into their own mobile header branch instead of the player navigation.');
}
if (!str_contains($sources['homepage'], "\$this->view->vars['showGuestNavBar'] = false;")
    || !str_contains($sources['homepage'], "'localHomepage publicHomepageShell'")
    || !str_contains($sources['homepage'], "\$this->view->renderLoginBox = false;")) {
    failGuestLayoutRuntimeContract('The local public homepage must use its own start-page header rather than a guest drawer or sidebar.');
}
if (!str_contains($sources['layout'], '$disableGuestSwipeNavigation')
    || !str_contains($sources['layout'], 'preventMobileSwipeNavigation')) {
    failGuestLayoutRuntimeContract('A non-login guest drawer must suppress the generic mobile sidebars.');
}
if (!str_contains($sources['layout'], '<a id="logo" href="<?= htmlspecialchars(')
    || str_contains($sources['layout'], 'class="light"')
    || str_contains($sources['layout'], 'fill="#231f20"')) {
    failGuestLayoutRuntimeContract('The shared Gpack must control the guest Travian wordmark colour without local logo overrides.');
}
foreach (['goldCount', 'silverCount', 'stockBar', 'newReportsCount', 'newMessagesCount'] as $playerOnlyValue) {
    if (str_contains($sources['guestMobileNav'], $playerOnlyValue)) {
        failGuestLayoutRuntimeContract("Guest mobile navigation must not read {$playerOnlyValue}.");
    }
}
if (!str_contains($sources['layout'], '$layoutSession->isValid()')
    || !str_contains($sources['head'], '$headSession->isValid()')
    || !str_contains($sources['head'], '$headDisplay[7] ?? 0')) {
    failGuestLayoutRuntimeContract('Guest pages must not read player-only display preferences.');
}
if (!str_contains($sources['quest'], '$session->isValid() ? $session->getQuestBattle()')
    || !str_contains($sources['quest'], '$session->isValid() && $session->getSuccessAdventuresCount() >= 10')) {
    failGuestLayoutRuntimeContract('Quest translation bootstrapping must allow guests without player progress.');
}

echo "Guest layout runtime contract passed.\n";
