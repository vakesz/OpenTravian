<?php

declare(strict_types=1);

$directory = dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/textures/hero';
$jsonFiles = glob($directory . '/*.json') ?: [];
$pngFiles = glob($directory . '/*.png') ?: [];
if (count($jsonFiles) !== 72 || count($pngFiles) !== 72) {
    fwrite(STDERR, "Hero V2 appearance atlas must contain exactly 72 JSON files and 72 PNG files.\n");
    exit(1);
}

foreach ($jsonFiles as $jsonFile) {
    $contents = file_get_contents($jsonFile);
    $decoded = is_string($contents) ? json_decode($contents, true) : null;
    $expectedPng = basename($jsonFile, '.json') . '.png';
    if (!is_array($decoded)
        || ($decoded['meta']['image'] ?? null) !== $expectedPng
        || !is_array($decoded['frames'] ?? null)
        || $decoded['frames'] === []) {
        fwrite(STDERR, 'Invalid Hero V2 appearance atlas: ' . basename($jsonFile) . "\n");
        exit(1);
    }
}

foreach ($pngFiles as $pngFile) {
    $contents = file_get_contents($pngFile);
    if (!is_string($contents) || !str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
        fwrite(STDERR, 'Invalid Hero V2 appearance texture: ' . basename($pngFile) . "\n");
        exit(1);
    }
}

$renderer = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/hero-v2-appearance.js');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/hero/heroFace.php');
$face = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Hero/HeroFace.php');
$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/heroEditor.php');
if (!is_string($renderer)
    || !is_string($template)
    || !is_string($face)
    || !is_string($controller)
    || !str_contains($template, 'id="heroAppearancePreview"')
    || !str_contains($template, 'hero-v2-appearance.js')
    || !str_contains($renderer, 'textures/hero/')
    || !str_contains($renderer, 'drawComposite')
    || !str_contains($renderer, 'spriteSourceSize')
    || !str_contains($renderer, "state.beard + \"-\" + state.hairColor,\n          true,")
    || !str_contains($face, 'class="optionPreview ')
    || str_contains($face, 'img/hero/')
    || str_contains($face, 'getHeroImageAsHTML')
    || str_contains($controller, 'getHeroImageAsHTML')
    || str_contains($controller, "\$this->response['html']")) {
    fwrite(STDERR, "Hero appearance must use the self-hosted V2 canvas compositor as its single rendering path.\n");
    exit(1);
}

// The hero stage geometry belongs to the verbatim reference CSS; the local
// stylesheet must not re-implement it behind legacy ids.
$inventoryCss = file_get_contents(dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/lang/overrides.css');
// The stored stylesheet is formatted; compare whitespace-free with the trailing `;` before `}` dropped.
$referenceCss = str_replace(';}', '}', (string)preg_replace('/\s+/', '', (string)file_get_contents(
    dirname(__DIR__, 2) . '/assets/gpack/travian/mainPage/css_ltr/imports.css'
)));
if (!is_string($inventoryCss)
    || str_contains($inventoryCss, '#bodyOptions')
    || str_contains($inventoryCss, '#hero_inventory')
    || str_contains($inventoryCss, '#itemsToSale')
    || !str_contains($referenceCss, '#content.heroV2Inventory.currentHeroEquipment{position:relative;height:500px;display:grid;')
    || !str_contains($referenceCss, '#content.heroV2Inventory.currentHeroEquipment.equipmentSlots{margin-left:3px}')
    || !str_contains($referenceCss, '#content.heroV2Inventory.inventoryWrapper.heroItems{')) {
    fwrite(STDERR, "Hero V2 inventory preview must use the reference-sized stage from the bundled Gpack.\n");
    exit(1);
}

echo "Hero V2 appearance asset contract passed.\n";
