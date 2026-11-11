<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$legend = [
    1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 5 => 'Natars', 6 => 'Egyptians',
    7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings', 0 => 'Multihunter',
];
$dot = MAP_SIZE <= 100 ? 7 : (MAP_SIZE <= 200 ? 5 : 4);
$legendHtml = '<ul class="adminMinimapLegend">';
foreach ($legend as $tribe => $name) {
    $legendHtml .= '<li><i class="mapDot static tribe' . (int) $tribe . '"></i> ' . AdminUi::esc($name) . '</li>';
}
$legendHtml .= '</ul>';

$body = '<div class="adminMinimap">'
    . '<div class="adminMinimapMap" role="group" aria-label="World minimap" style="--dot: ' . $dot . 'px">'
    . '<span class="axis horizontal"></span><span class="axis vertical"></span>'
    . '<span class="edge west">-' . MAP_SIZE . '</span>'
    . '<span class="edge east">' . MAP_SIZE . '</span>'
    . '<span class="edge north">' . MAP_SIZE . '</span>'
    . '<span class="edge south">-' . MAP_SIZE . '</span>'
    . ($params['content'] ?? '')
    . '</div>'
    . $legendHtml
    . '</div>';

echo AdminUi::page(
    'Minimap',
    'Every village in the world. Hover a dot for the player and village; click it to edit the village.',
    $body
);
