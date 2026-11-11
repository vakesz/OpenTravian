<?php

declare(strict_types=1);

function assertHeroV2AttributesContract(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/HeroInventoryCtrl.php');
$sessionHero = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Hero/SessionHero.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/hero/hero_inventory.php');
$layout = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/Layout.php');
$functions = file_get_contents(dirname(__DIR__, 2) . '/game/include/functions.general.php');
$propertySetter = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');
$attributesScript = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/hero-attributes.js');
$heroCss = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/lang/hero.css');
$gpackCss = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/css_ltr/imports.css');

// The bundled Gpack CSS is the verbatim minified CDN file: compare without whitespace.
$compactGpackCss = preg_replace('/\s+/', '', (string) $gpackCss);

assertHeroV2AttributesContract(
    $controller !== false && $sessionHero !== false && $template !== false && $layout !== false && $functions !== false && $propertySetter !== false && $attributesScript !== false && $heroCss !== false && $gpackCss !== false,
    'Unable to read the Hero V2 attributes sources.'
);

assertHeroV2AttributesContract(
    str_contains($controller, 'heroV2Attributes'),
    'The attributes view must retain the Hero V2 content selector.'
);
assertHeroV2AttributesContract(
    str_contains($controller, '<div id="heroV2" class="contentV2">')
        && str_contains($controller, '. $navigation->output()')
        && str_contains($controller, '. $view->output()')
        && !str_contains($template, '<div class="contentV2">')
        && !str_contains($layout, 'usesHeroPageLayout'),
    'The Hero V2 shell must wrap the navigation and selected view together without a legacy layout wrapper.'
);
assertHeroV2AttributesContract(
    str_contains($template, 'class="heroAttributes"')
        && str_contains($template, 'class="attributes formV2"')
        && !str_contains($template, 'id="attributesOfHero"'),
    'Hero attributes must use the native .heroAttributes .attributes structure.'
);
assertHeroV2AttributesContract(
    str_contains($controller, '"heroStatusIcon" => match ($hero->getHeroStatus())')
        && str_contains($controller, "SessionHero::STATUS_HOME => 'statusHome_medium'")
        && str_contains($controller, "SessionHero::STATUS_DEAD => 'statusDead_medium'")
        && str_contains($template, '<div class="heroState">')
        && str_contains($template, 'heroStatusIcon')
        && str_contains($template, '<span class="benefit">')
        && !str_contains($template, 'class="heroStatusMessage <?= $vars[\'hero\'][\'status\']')
        && !str_contains($template, 'class="heroStatus<?= $vars[\'hero\'][\'status\']; ?>"'),
    'Hero attributes must use the native status icon row and equipment-benefit markup.'
);
assertHeroV2AttributesContract(
    str_contains($template, 'method="post" action="/hero/inventory"')
        && str_contains($template, '\\Core\\Session::getCheckerInput()'),
    'The V2 dead-Hero revive form must keep its tokenized POST contract.'
);
assertHeroV2AttributesContract(
    str_contains($controller, '"reviveEndTime" => $hero->getReviveEndTime()')
        && str_contains($controller, "\$view->vars['heroRevive']")
        && !str_contains($controller, "\$view->vars['regenerate']")
        && str_contains($sessionHero, 'public function getReviveEndTime(): ?int')
        && str_contains($template, 'class="attributeBox heroRevive"')
        && str_contains($template, 'class="statusRevive_medium"')
        && str_contains($template, 'class="timerReact"')
        && str_contains($template, 'data-revive-end-time')
        && str_contains($template, 'class="attributeBox heroDead"')
        && !str_contains($template, 'id="attributes" class="hero-dead"')
        && !str_contains($template, 'roundedCornersBox lightRed')
        && !str_contains($template, "\$vars['regenerate']")
        && str_contains($attributesScript, 'initializeReviveTimer')
        && str_contains($attributesScript, 'data-revive-end-time'),
    'Dead and reviving heroes must use the maintained V2 cards, including the live revive countdown.'
);
assertHeroV2AttributesContract(
    str_contains($template, 'id="savePoints"')
        && str_contains($template, 'type="button" disabled="" id="savePoints"')
        && !str_contains($template, 'green disabled" type="button" disabled="" id="savePoints"')
        && !str_contains($attributesScript, 'saveButton.classList.toggle("disabled", saveButton.disabled)'),
    'The disabled attributes Save control must use only the native disabled state.'
);
assertHeroV2AttributesContract(
    str_contains($compactGpackCss, '#content.heroV2Attributes.attributeBox>div.heroAttributes.attributes{display:grid;grid-template-columns:1frautoauto;grid-template-rows:repeat(4,25px);'),
    'Hero attributes must use the maintained Gpack desktop grid.'
);

echo "Hero V2 attributes contract passed.\n";
