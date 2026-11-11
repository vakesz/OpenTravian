<?php
/*
 * Hero appearance editor, laid out as the reference `.appearanceWrapper`.
 * Pages, option previews and palettes are driven by hero-v2-appearance.js.
 */
$icons = \Game\Hero\HeroFace::icons();
$gender = $vars['heroFace']['gender'];
$attributes = $vars['HeroFaceJson']['attributes'];
$palettes = [
    'skinColor' => \Game\Hero\HeroFace::SKIN_COLORS,
    'eyeColor' => \Game\Hero\HeroFace::EYE_COLORS,
    'hairColor' => \Game\Hero\HeroFace::HAIR_COLORS,
];
$paletteConfig = [];
foreach ($palettes as $attribute => $colors) {
    $paletteConfig[$attribute] = [
        'label' => T('HeroFace', $attribute),
        'colors' => array_map(
            static fn (int $index, string $color): array => [
                'name' => $color,
                'value' => $attribute === 'hairColor' ? 1500 + $index : $color,
            ],
            array_keys($colors),
            $colors
        ),
    ];
}
$skinPalette = $paletteConfig['skinColor'];
$heroV2TextureBase = '/gpack/' . get_gpack_folder(get_gpack_version()) . '/mainPage/';
?>
<div class="appearanceWrapper">
    <?=(new \Game\Hero\HeroFace())->getCategoriesHtml($gender);?>
    <div class="selectionWrapper ">
        <?=$vars['heroFaceDataHtml'];?>
        <div class="previewWrapper"><div class="preview <?=$gender;?> zoomedIn "><canvas id="heroAppearancePreview" width="875" height="1574"></canvas></div><?=str_replace(['%GENDER%', '%ZOOM%'], [$gender, 'zoomOut'], $icons['actions']);?></div>
        <div class="colorsWrapper">
            <div class="colorPalette"><?=htmlspecialchars($skinPalette['label'], ENT_QUOTES, 'UTF-8');?></div>
            <div class="colors skinColor"><?php foreach ($skinPalette['colors'] as $color):
                $selected = (string)$attributes['skinColor'] === (string)$color['value'];
            ?><div class="color <?=$color['name'];?><?=$selected ? ' selected' : '';?>"><div class="colorPreview <?=$color['name'];?>"></div><?=$selected ? '<div class="selectedIndicator"></div>' : '';?></div><?php endforeach; ?></div>
        </div>
    </div>
    <div class="buttonWrapper"><button class="textButtonV2 buttonFramed buyOptions disabled rectangle withText gold" type="button"><div><?=htmlspecialchars(T('HeroFace', 'buySelectedOptions', 'Buy selected options'), ENT_QUOTES, 'UTF-8');?></div></button><?=$icons['resetButton'];?><button class="textButtonV2 buttonFramed saveChanges disabled rectangle withText green" type="button" title="<?=htmlspecialchars((string)T('HeroFace', 'Save changes'), ENT_QUOTES, 'UTF-8');?>"><div><?=htmlspecialchars(T('HeroInventory', 'save'), ENT_QUOTES, 'UTF-8');?></div></button></div>
</div>
<script type="text/javascript" src="/hero-v2-appearance.js?v=<?=filemtime(PUBLIC_INTERNAL_PATH . 'hero-v2-appearance.js');?>"></script>
<script type="text/javascript">
    new HeroAppearanceEditor(document.querySelector('#content .appearanceWrapper'), <?=json_encode([
        'command' => $vars['HeroFaceJson']['command'],
        'attributes' => $attributes,
        'race' => (int)($vars['heroRace'] ?? 0),
        'assetBase' => $heroV2TextureBase,
        'palettes' => $paletteConfig,
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);?>);
</script>
