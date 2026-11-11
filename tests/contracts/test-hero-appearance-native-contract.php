<?php

declare(strict_types=1);

/*
 * The Hero appearance editor must render the reference `.appearanceWrapper`
 * DOM (rendered /hero/appearance of) natively, styled only by the
 * bundled reference CSS, and driven by the vanilla hero-v2-appearance.js.
 */

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fail('Could not read ' . $path);
    }

    return $contents;
};

$controller = $read('game/include/Controller/HeroFaceCtrl.php');
$template = $read('game/include/resources/Templates/hero/heroFace.php');
$heroFace = $read('game/include/Game/Hero/HeroFace.php');
$script = $read('game/deployment/public/hero-v2-appearance.js');
$heroCss = $read('assets/gpack/travian/mainPage/lang/hero.css');
$legacyCss = $read('assets/gpack/travian/mainPage/lang/overrides.css');
// The bundled Gpack CSS is the verbatim minified CDN file: compare without whitespace.
$compactGpackCss = preg_replace('/\s+/', '', $read('assets/gpack/travian/mainPage/css_ltr/imports.css'));
$compactGpackCssRtl = preg_replace('/\s+/', '', $read('assets/gpack/travian/mainPage/css_rtl/imports.css'));

foreach (['hero_editor', 'heroV2 heroV2Appearance'] as $contract) {
    if (!str_contains($controller, $contract)) {
        fail('Hero appearance route must keep the reference body/content classes.');
    }
}

foreach (['heroAppearance', 'heroEditor ', '#heroEditor', 'hero_editor #heroEditor', 'heroAppearanceEditor'] as $legacy) {
    if (str_contains($heroCss, $legacy) || str_contains($legacyCss, $legacy)) {
        fail("Local CSS must not style the removed appearance editor ({$legacy}); the reference CSS owns it.");
    }
}

foreach (['Travian.Game.Hero.Editor', 'heroEditorActivate', 'attributesContainer', 'heroEditorInstance'] as $legacy) {
    foreach ([$template, $script, $heroFace, $read('game/deployment/public/game-client.js')] as $source) {
        if (str_contains($source, $legacy)) {
            fail("The legacy appearance editor ({$legacy}) must stay removed.");
        }
    }
}

foreach ([
    '<div class="appearanceWrapper">',
    'class="selectionWrapper "',
    'class="previewWrapper"',
    'class="preview <?=$gender;?> zoomedIn "',
    '<canvas id="heroAppearancePreview" width="875" height="1574">',
    'class="colorsWrapper"',
    'class="colorPalette"',
    'class="colors skinColor"',
    'class="buttonWrapper"',
    'textButtonV2 buttonFramed buyOptions disabled rectangle withText gold',
    'textButtonV2 buttonFramed saveChanges disabled rectangle withText green',
    'new HeroAppearanceEditor(',
] as $contract) {
    if (!str_contains($template, $contract)) {
        fail("Hero appearance template is missing the reference markup: {$contract}");
    }
}

foreach ([
    '#content.heroV2Appearance .appearanceWrapper',
    '#content.heroV2Appearance .categories .category',
    '#content.heroV2Appearance .selectionWrapper',
    '#content.heroV2Appearance .selectionWrapper > .page.active',
    '#content.heroV2Appearance .selectionWrapper .previewWrapper',
    '#content.heroV2Appearance .selectionWrapper .optionsWrapper .option',
    '#content.heroV2Appearance .selectionWrapper .colorsWrapper .colors .color',
] as $selector) {
    $needle = preg_replace('/\s+/', '', $selector);
    if (!str_contains($compactGpackCss, $needle) || !str_contains($compactGpackCssRtl, $needle)) {
        fail("Bundled Gpack no longer provides the Hero V2 appearance selector: {$selector}");
    }
}

foreach ([
    'function HeroAppearanceEditor(',
    'CATEGORY_ATTRIBUTES',
    'Travian.api(this.options.command',
    'HeroAppearanceEditor.prototype.switchGender',
    'HeroAppearanceEditor.prototype.mountActivePage',
    'HeroAppearanceEditor.prototype.activePage',
    'HeroAppearanceEditor.prototype.setBuyDisabled',
    'wrapper.querySelectorAll(".page").forEach',
    'this.setBuyDisabled(!(dirty && lockedSelection))',
] as $contract) {
    if (!str_contains($script, $contract)) {
        fail("hero-v2-appearance.js is missing: {$contract}");
    }
}

function T(string $scope, string $key, $default = false): string
{
    return $default !== false
        ? (string)$default
        : ['skinColor' => 'Skin color', 'save' => 'Save changes'][$key] ?? $key;
}

function get_gpack_version(): string
{
    return 'travian';
}

function get_gpack_folder(string $version): string
{
    return $version;
}

if (!defined('PUBLIC_INTERNAL_PATH')) {
    define('PUBLIC_INTERNAL_PATH', $root . '/game/deployment/public/');
}

require_once $root . '/game/include/Game/Hero/HeroFace.php';

foreach ([0 => 'black', 1 => 'brown', 2 => 'brightbrown', 3 => 'blonde', 4 => 'red', 99 => 'brown'] as $index => $expectedColor) {
    if (\Game\Hero\HeroFace::hairColorName($index) !== $expectedColor) {
        fail('Hero face assets must use one canonical hair-colour mapping.');
    }
}

$attributes = [
    'gender' => 'male',
    'headProfile' => 52,
    'hairColor' => 1500,
    'hairStyle' => 1000,
    'ears' => 2000,
    'eyebrow' => 7000,
    'eyes' => 4000,
    'nose' => 3000,
    'mouth' => 6000,
    'beard' => 5000,
    'scar' => 8000,
    'tattoo' => 9000,
    'skinColor' => 'skin1',
    'eyeColor' => 'blue',
];

function renderEditor(string $gender, array $attributes): DOMXPath
{
    $face = new \Game\Hero\HeroFace();
    $vars = [
        'heroFace' => ['gender' => $gender],
        'heroFaceDataHtml' => $face->getAllHeroFaceAttributesHtml($gender, $attributes),
        'HeroFaceJson' => ['element' => 'heroEditor', 'command' => 'heroEditor', 'attributes' => $attributes],
        'heroRace' => 1,
    ];
    ob_start();
    include dirname(__DIR__, 2) . '/game/include/resources/Templates/hero/heroFace.php';
    $html = (string)ob_get_clean();
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<!doctype html><html><body>' . $html . '</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

function hasClass(string $class): string
{
    return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
}

$male = renderEditor('male', $attributes);
$wrapper = '//div[' . hasClass('appearanceWrapper') . ']';

$expect = static function (DOMXPath $xpath, string $query, int $count, string $message): void {
    $found = $xpath->query($query)->length;
    if ($found !== $count) {
        fail("{$message} (expected {$count}, found {$found} for {$query})");
    }
};

$expect($male, $wrapper . '/div[' . hasClass('categories') . ']/div[' . hasClass('category') . ' and @data-category and i]', 10, 'Male editors expose ten reference categories');
$expect($male, $wrapper . '/div[' . hasClass('categories') . ']/div[' . hasClass('category') . ' and ' . hasClass('selected') . ']', 1, 'Exactly one category is selected');
$expect($male, $wrapper . '/div[' . hasClass('selectionWrapper') . ']/div[' . hasClass('page') . ']', 10, 'One page per category');
$expect($male, $wrapper . '/div[' . hasClass('selectionWrapper') . ']/div[' . hasClass('page') . ' and ' . hasClass('active') . ' and ' . hasClass('page0') . ']/div[' . hasClass('optionsWrapper') . ']/div[' . hasClass('option') . ']', 15, 'Fifteen options per page');
$expect($male, $wrapper . '//div[' . hasClass('option') . ' and @data-option]/div[' . hasClass('optionPreview') . ']/canvas[@width="875" and @height="1574"]', 150, 'Every option draws a canvas preview');
$expect($male, $wrapper . '//div[' . hasClass('page0') . ']//div[' . hasClass('option') . ' and ' . hasClass('selected') . ']/div[' . hasClass('selectedIndicator') . ']', 1, 'The selected option carries its indicator');
$expect($male, $wrapper . '//div[' . hasClass('page0') . ']//div[' . hasClass('option') . ']/div[' . hasClass('lockedIndicator') . ']', 9, 'Nine premium jaw options are locked');
$expect($male, $wrapper . '//div[' . hasClass('page0') . ']//div[' . hasClass('goldAmount') . ']/span[text()="40"]', 3, 'The last row costs 40 gold');
$expect($male, $wrapper . '//div[' . hasClass('previewWrapper') . ']/div[' . hasClass('preview') . ' and ' . hasClass('male') . ' and ' . hasClass('zoomedIn') . ']/canvas[@id="heroAppearancePreview"]', 1, 'Preview canvas');
$expect($male, $wrapper . '//div[' . hasClass('previewWrapper') . ']/div[' . hasClass('actions') . ']/button[' . hasClass('toggleGender') . ' or ' . hasClass('shuffleConfig') . ' or ' . hasClass('toggleZoom') . ']', 3, 'Gender, shuffle and zoom actions');
$expect($male, $wrapper . '//div[' . hasClass('colorsWrapper') . ']/div[' . hasClass('colorPalette') . ' and text()="Skin color"]', 1, 'Skin palette label');
$expect($male, $wrapper . '//div[' . hasClass('colors') . ' and ' . hasClass('skinColor') . ']/div[' . hasClass('color') . ']/div[' . hasClass('colorPreview') . ']', 4, 'Four skin colours');
$expect($male, $wrapper . '/div[' . hasClass('buttonWrapper') . ']/button[' . hasClass('buyOptions') . ' and ' . hasClass('disabled') . ']', 1, 'Buy button');
$expect($male, $wrapper . '/div[' . hasClass('buttonWrapper') . ']/button[' . hasClass('resetChanges') . ' and ' . hasClass('disabled') . ']', 1, 'Reset button');
$expect($male, $wrapper . '/div[' . hasClass('buttonWrapper') . ']/button[' . hasClass('saveChanges') . ' and ' . hasClass('disabled') . ' and not(@disabled)]/div[text()="Save changes"]', 1, 'Save button');
$expect($male, '//*[@id="heroEditor" or @id="attributesContainer" or @id="save" or @id="random"]', 0, 'Legacy ids stay removed');

$female = renderEditor('female', array_merge($attributes, ['gender' => 'female', 'headProfile' => 51]));
$expect($female, $wrapper . '//div[' . hasClass('category') . ']', 9, 'Female editors have no beard category');
$expect($female, $wrapper . '//div[' . hasClass('category') . ' and ' . hasClass('beard') . ']', 0, 'Female editors have no beard tab');
$expect($female, $wrapper . '//div[' . hasClass('selectionWrapper') . ']/div[' . hasClass('page') . ']', 9, 'Female pages match the categories');
$expect($female, $wrapper . '//div[' . hasClass('optionPreview') . ' and ' . hasClass('female') . ']', 135, 'Female previews use the female atlases');

echo "Hero V2 appearance contract passed.\n";
