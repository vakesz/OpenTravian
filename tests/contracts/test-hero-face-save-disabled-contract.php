<?php

declare(strict_types=1);

/**
 * Contract: hero appearance Save uses disabled class (not HTML disabled attr).
 */

function failHeroFaceSaveDisabledContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$face = file_get_contents($root . '/game/include/resources/Templates/hero/heroFace.php');
$icons = file_get_contents($root . '/game/include/resources/Templates/hero/appearanceIcons.php');
$js = file_get_contents($root . '/game/deployment/public/hero-v2-appearance.js');
if ($face === false || $icons === false || $js === false) {
    failHeroFaceSaveDisabledContract('Unable to read hero appearance sources.');
}

if (!str_contains($face, 'textButtonV2 buttonFramed saveChanges disabled rectangle withText green')) {
    failHeroFaceSaveDisabledContract('heroFace Save must include disabled class in order.');
}

if (preg_match('/saveChanges[^>]*type="button"\s+disabled/', $face)
    || preg_match('/saveChanges[^>]*\sdisabled(\s*=|\s*>)/', $face)) {
    failHeroFaceSaveDisabledContract('heroFace Save must not use the HTML disabled attribute.');
}

if (preg_match('/resetChanges[^>]*type="button"\s+disabled/', $icons)
    || preg_match('/resetChanges[^>]*\sdisabled(\s*=|\s*>)/', $icons)) {
    failHeroFaceSaveDisabledContract('heroFace Reset must use the disabled class, not an HTML disabled attribute.');
}

if (!str_contains($js, 'setSaveDisabled')
    || !str_contains($js, 'control.classList.contains("saveChanges")')
    || !str_contains($js, 'this.setSaveDisabled(!dirty || lockedSelection)')) {
    failHeroFaceSaveDisabledContract('hero-v2-appearance.js must toggle Save via disabled class only.');
}

if (!str_contains($js, 'setResetDisabled')
    || !str_contains($js, 'button.classList.toggle("disabled", disabled);')
    || !str_contains($js, 'control.classList.contains("resetChanges")')
    || str_contains($js, 'HeroAppearanceEditor.prototype.setDisabled')) {
    failHeroFaceSaveDisabledContract('hero-v2-appearance.js must retain Reset as a class-disabled control without a native disabled attribute.');
}

echo "Hero face save disabled contract passed.\n";
