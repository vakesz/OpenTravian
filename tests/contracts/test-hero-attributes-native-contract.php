<?php

declare(strict_types=1);

/**
 * Contract: /hero/attributes renders the markup (captured 2026-10-31):
 * attributeBox siblings, decoration-svg progress bars, framed icon toggles for
 * the production focus, and a Save button that starts disabled.
 */

function failHeroAttributesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/hero/hero_inventory.php');
$script = file_get_contents($root . '/game/deployment/public/hero-attributes.js');
$questModel = file_get_contents($root . '/game/include/Model/Quest.php');
$heroCss = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/hero.css');
$english = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');

foreach ([$template, $script, $questModel, $heroCss, $english, $crypt] as $source) {
    if ($source === false) {
        failHeroAttributesContract('Could not read the hero attribute sources.');
    }
}

foreach ([
    'attributeHealth_medium', 'attributeExperience_medium', 'attributeSpeed_medium',
    '<div class="stats">', 'filling primary green', 'filling primary blue', 'class="value speedValue"',
    '<svg viewBox="0 0 10 7.75" class="arrowDouble">', '<strong><?=number_format($vars[\'hero\'][\'speed\']);?></strong>',
    "daily_quest_svg('decoration_start')", "daily_quest_svg('decoration_end')",
] as $needle) {
    if (!str_contains($template, $needle)) {
        failHeroAttributesContract("The stats box lost reference markup: $needle");
    }
}

foreach ([
    '<div class="productionItem"><span class="value">+ <?=number_format_x($heroCropProduction);?></span><i class="crop_tiny"></i></div>',
    'resources_small', 'lumber_small', 'clay_small', 'iron_small', 'crop_small',
    'textButtonV2 buttonFramed pickProductionType rectangle withIcon toggle activeNotClickable',
    "'active orange' : 'grey'",
    '$heroCropProduction = 6 + ',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failHeroAttributesContract("The production box lost reference markup: $needle");
    }
}

foreach ([
    '<div class="attributes formV2"', '<div class="points_descr">', '<div class="pointsAvailable">',
    "T('HeroInventory', 'availablePoints')",
    'class="filling secondary blue" style="width: 0%; transition-duration: 250ms;"',
    'textButtonV2 buttonFramed plus rectangle withIcon green', 'textButtonV2 buttonFramed minus rectangle withIcon green',
    '<div class="slider"><div class="handle blue"', 'class="inputRatio pointsRatio isLtr"',
    "if (\$heroPoints > 0 && \$attribute['value'] < 100):",
    "inputmode=\"numeric\"<?=\$heroPoints > 0 && \$attribute['value'] < 100 ? '' : ' disabled=\"\"';?> autocomplete=\"off\" type=\"text\"",
    'withText green" type="button" disabled="" id="savePoints"', '<div class="formV2 heroHideSwitch">', '<label class="switch">',
    '<div class="switch"></div>', '/hero-attributes.js',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failHeroAttributesContract("The attribute editor lost reference markup: $needle");
    }
}

if (str_contains($template, "T('HeroInventory', 'pointsAvailable'")) {
    failHeroAttributesContract('The attributes page must use the canonical availablePoints translation key.');
}

foreach ([
    'heroAttributeSummary', 'attributesOfHero', 'data-hero-adjust', 'setResource', 'class="setted"', 'filling secondary blue setted',
    'heroHealthProgress', 'role="progressbar"', 'class="radio"', 'heroAttackBehaviourValue', 'heroHideDuringHomeAttack',
    'openCloseSwitchBar', 'flagAttributesBoxOpen', 'class="heroStatusMessage"',
] as $legacy) {
    if (str_contains($template, $legacy)) {
        failHeroAttributesContract("Legacy attribute-page markup or script survived: $legacy");
    }
}

foreach (['Travian.Game.Hero.PropertySetter', 'Travian.Game.Hero.Properties'] as $legacyClass) {
    if (str_contains($crypt, $legacyClass)) {
        failHeroAttributesContract("The retired $legacyClass client must stay deleted.");
    }
}

foreach (['heroAttributeSummary', 'noPointsToSet', '#setResource', 'heroHideSwitch'] as $localOverride) {
    if (str_contains($heroCss, $localOverride)) {
        failHeroAttributesContract("hero.css must not override the Gpack attribute page ($localOverride).");
    }
}

foreach ([
    "Travian.api(\"hero/attributes\"", 'attributes[row.key] = row.value;', 'saveButton.disabled = !(allocated() > 0',
    'row.input.disabled = available === 0 || row.initial >= MAX_POINTS;',
    'if (row.plus) {', 'row.plus.disabled = remaining === 0', 'if (row.minus) {', 'row.minus.disabled = row.value <= row.initial',
    'if (row.handle) {', 'if (!slider) {',
    'row.handle.style.marginInlineStart', 'toLocaleString("en-US"',
] as $needle) {
    if (!str_contains($script, $needle)) {
        failHeroAttributesContract("hero-attributes.js is missing: $needle");
    }
}
if (str_contains($script, 'jQuery.fn.') || str_contains($script, '.bind(') || str_contains($script, '.size()')) {
    failHeroAttributesContract('hero-attributes.js must not use APIs removed in jQuery 4.');
}

if (str_contains($questModel, 'heroAttributeSummary') || str_contains($questModel, 'Travian.Game.Hero.PropertySetter')) {
    failHeroAttributesContract('The retired tutorial must not restore legacy hero-attribute selectors or client code.');
}
if (!str_contains($english, '"fieldPerHour" => "fields/hour"')) {
    failHeroAttributesContract('The Hero speed row must use the reference fields/hour label.');
}

echo "Hero attributes native contract passed.\n";
