<?php

declare(strict_types=1);

/**
 * Contract: Alliance tab favourite button posts a named key (not a number).
 */

function failAllianceFavorKeyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$menu = file_get_contents($root . '/game/include/resources/Templates/alliance/menu.php');
$fav = file_get_contents($root . '/game/include/Controller/Ajax/favouriteTab.php');
if ($menu === false || $fav === false) {
    failAllianceFavorKeyContract('Unable to read alliance favour sources.');
}

if (!str_contains($menu, "name: 'alliance',key:")) {
    failAllianceFavorKeyContract('alliance/menu.php must post favourite-tab with key, not number.');
}
if (str_contains($menu, "name: 'alliance',number:")) {
    failAllianceFavorKeyContract('alliance/menu.php must not keep number-based favourite-tab payload.');
}
if (!str_contains($menu, "jQuery('.subNavi .favor').removeClass('favorActive')")) {
    failAllianceFavorKeyContract('favourite success handler must scope favorActive clear to .subNavi .favor.');
}

if (!str_contains($fav, "'alliance' => [") || !str_contains($fav, "'forum' => 2")) {
    failAllianceFavorKeyContract('favouriteTab KEYED_TABS must map alliance keys to tab ids.');
}

fwrite(STDOUT, "Alliance favor key contract passed.\n");
