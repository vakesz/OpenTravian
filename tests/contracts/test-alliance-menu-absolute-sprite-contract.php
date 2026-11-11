<?php

declare(strict_types=1);

/**
 * Contract: alliance menu has no favorIcon imgs; alliance menu + donate form
 * sprites use absolute /img/spacer.gif (not relative img/spacer.gif).
 */

function failAllianceMenuAbsoluteSpriteContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$menu = file_get_contents($root . '/game/include/resources/Templates/alliance/menu.php');
$donate = file_get_contents($root . '/game/include/resources/Templates/alliance/bonuses/donateForm.php');
if ($menu === false || $donate === false) {
    failAllianceMenuAbsoluteSpriteContract('Unable to read alliance menu/donate sources.');
}

if (str_contains($menu, 'favorIcon') || preg_match('/<img[^>]*class="favorIcon"/', $menu)) {
    failAllianceMenuAbsoluteSpriteContract('alliance/menu.php must not emit favorIcon images.');
}

if (str_contains($menu, 'src="img/spacer.gif"')) {
    failAllianceMenuAbsoluteSpriteContract('alliance/menu.php must not use relative src="img/spacer.gif".');
}

if (!str_contains($menu, 'src="/img/spacer.gif"')) {
    failAllianceMenuAbsoluteSpriteContract('alliance/menu.php tabFavorButton must keep absolute /img/spacer.gif.');
}

if (str_contains($donate, 'src="img/spacer.gif"')) {
    failAllianceMenuAbsoluteSpriteContract('donateForm.php must not use relative src="img/spacer.gif".');
}

if (substr_count($donate, 'src="/img/spacer.gif"') < 2) {
    failAllianceMenuAbsoluteSpriteContract('donateForm.php must use absolute /img/spacer.gif for bonus + sum icons.');
}

fwrite(STDOUT, "Alliance menu absolute sprite contract passed.\n");
