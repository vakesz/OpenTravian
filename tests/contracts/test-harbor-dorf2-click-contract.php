#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: Harbor (gid 49) must be clickable on dorf2 for every playable tribe.
 *
 * The Gpack sprite is 328×232. Generated SVG paths historically only shipped for
 * tribes 8/9; every selectable tribe can build a Harbor, so the click path and
 * oversized SVG must resolve for 1/2/3/6/7/8/9. Visual ship layers live under
 * #background .behindContent .harbour (not inside the buildingSlot).
 */

function assertHarborClick(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$svg = (string)file_get_contents($root . '/game/include/resources/Svg/buildFieldSvg.php');
assertHarborClick(str_contains($svg, "foreach ([1, 2, 3, 6, 7] as \$harborTribe)"), 'buildFieldSvg.php must alias g49 paths onto tribes 1/2/3/6/7.');

require SVG_BUILDING_TEMPLATES;
foreach ([1, 2, 3, 6, 7, 8, 9] as $tribe) {
    assertHarborClick(isset($buildingField[$tribe]['g49']) && strlen((string)$buildingField[$tribe]['g49']) > 100, "Tribe {$tribe} must have a Harbor click path.");
    assertHarborClick(strlen((string)($buildingField[$tribe]['g35'] ?? '')) > 100, "Tribe {$tribe} must have a Brewery click path (empty g35 steals neighbor hits).");
}

$dorf2 = (string)file_get_contents($root . '/game/include/resources/Templates/village-center/main.php');
assertHarborClick(str_contains($dorf2, "buildingShape g49"), 'dorf2 must emit svg.buildingShape.g49 for the Harbor.');
assertHarborClick(!str_contains($dorf2, 'class="ship <?=$shipClass;?>"'), 'Ship overlays must leave the buildingSlot (Gpack paints them under .behindContent .harbour).');
assertHarborClick(str_contains($dorf2, "wallShore = !empty(\$vars['isShore'])")
    || str_contains($dorf2, "wall <?=\$wallShore;?>g"), 'Shore villages must paint wall imgs with the shore class for Gpack shore palisade sprites.');

$dorf2Ctrl = (string)file_get_contents($root . '/game/include/Controller/VillageCenterCtrl.php');
assertHarborClick(str_contains($dorf2Ctrl, "'isShore'") && str_contains($dorf2Ctrl, 'isShoreVillage'), 'VillageCenterCtrl must pass isShore into the village-center template.');
assertHarborClick(str_contains($dorf2Ctrl, 'renderBehindContent') && str_contains($dorf2Ctrl, 'class="harbour'), 'VillageCenterCtrl must render the Gpack .harbour shell into behindContent.');

$layout = (string)file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
assertHarborClick(str_contains($layout, 'class="behindContent"') && str_contains($layout, 'behindContent'), 'Layout must always emit #background .behindContent for village2 harbour layers.');

$fixes = (string)file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
assertHarborClick(!str_contains($fixes, 'behindContent .harbour') && !str_contains($fixes, 'svg.buildingShape.g49') && !str_contains($fixes, 'img.building'), 'overrides.css must not move or hide the Gpack .harbour sprite.');
assertHarborClick(preg_match('/buildingSlot\\.a38\\.g49 \\{\\s*z-index: 43;\\s*\\}/', $fixes) === 1, 'overrides.css must stack the Harbor slot above the wall slot (z-index 43) with one narrow rule.');
assertHarborClick(str_contains($fixes, 'buildingSlot.a38.g49 .level') && str_contains($fixes, 'left: 152px') && str_contains($fixes, 'right: 152px'), 'overrides.css must place the Harbor level badge on the roof for LTR and RTL.');
assertHarborClick(str_contains(file_get_contents($root . '/docs/development.md'), 'Documented local difference (village center, Harbor)'), 'docs/development.md must document the Harbor local differences.');
assertHarborClick(!str_contains($fixes, 'buildingSlot.a35') && !str_contains($fixes, 'buildingSlot.a36'), 'overrides.css must not restack a35/a36.');
assertHarborClick(str_contains($dorf2, 'width="120" height="120" viewBox="0 0 120 120"') && str_contains($dorf2, 'translate(41 108)'), 'Harbor click SVG must stay in the 120x120 slot and place its path with one transform (the Gpack RTL css mirrors the svg).');
assertHarborClick(str_contains($dorf2, "(int)\$map['item_id'] !== 49"), 'The Harbor slot must not render a duplicate img.building sprite.');
assertHarborClick(str_contains($dorf2, "' shore' : ''"), 'Shore villages must add the shore class to the wall slots for the Gpack wall level rules.');

$svgSource = (string)file_get_contents($root . '/game/include/resources/Svg/buildFieldSvg.php');
assertHarborClick(str_contains($svgSource, "\$buildingField[\$breweryTribe]['g35'] = \$buildingField[2]['g35']"), 'Empty Brewery paths must fall back to the Teuton silhouette.');

echo "Harbor dorf2 click contract passed.\n";
