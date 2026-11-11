<?php

declare(strict_types=1);

/*
 * The hero tooltip links the village name at home (reference): the page and the hero/dataForHUD refresh must build
 * the text with the same function, so a refresh never drops the link.
 */

$root = dirname(__DIR__, 2);
$hud = (string)file_get_contents($root . '/game/include/Game/Hero/HeroHud.php');
$view = (string)file_get_contents($root . '/game/include/resources/View/GameView.php');

$ok = str_contains($hud, 'public static function longStatus(SessionHero $hero')
    && str_contains($hud, '$longStatus = self::longStatus($hero, $status);')
    && str_contains($view, 'HeroHud::longStatus(')
    && !str_contains($view, 'function heroLongStatus')
    && str_contains($hud, "'heroGenderImage' => self::genderImageClass(\$uid)")
    && str_contains((string)file_get_contents($root . '/game/deployment/public/tasks-board.js'), 'addClass(hud.heroGenderImage)');

if (!$ok) {
    fwrite(STDERR, 'The hero tooltip text must come from HeroHud::longStatus for the page and the refresh.' . PHP_EOL);
    exit(1);
}
echo "Hero HUD tooltip contract passed.\n";
