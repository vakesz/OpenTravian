<?php

declare(strict_types=1);

/**
 * Contract: the map tooltips (`map/position`) use the reference text: coordinates with isolates, the
 * distance placeholder the client fills in (or drops without an active village), `<br />` separators,
 * the animals of free oases and landscape titles; the page's tooltip translations use the reference
 * oasis tokens (`k.bt` occupied, `k.fo` free).
 */
function failMapTooltipContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$position = file_get_contents($root . '/game/include/Controller/Ajax/map/position.php');
$page = file_get_contents($root . '/game/include/resources/Templates/map/normal.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');
if ($position === false || $page === false || $client === false) {
    failMapTooltipContract('The map tooltip sources must be readable.');
}

foreach ([
    '<span class="tileDistance">{k.distance} [[DISTANCE]]</span>',
    "'<br />{k.animals}<br />'",
    "'{k.bt}' : '{k.fo}'",
    "T('HeroAdventure', ['wald', 'clay', 'hill', 'lake', 'vulcano']",
] as $needle) {
    if (!str_contains($position, $needle)) {
        failMapTooltipContract("map/position must contain {$needle}.");
    }
}
if (str_contains($position, '<br>')) {
    failMapTooltipContract('map/position must separate tooltip lines with <br />.');
}
foreach ([
    "'k.bt': '<?=T(\"map\", \"occupiedOasis\");?>'",
    "'k.fo': '<?=T(\"map\", \"freeOasis\");?>'",
    "'k.vt': '<?=T(\"Global\", \"Abandoned valley\");?>'",
    "'a.v8'",
    "'k.f13'",
    "'k.animals'",
    "'k.distance'",
] as $needle) {
    if (!str_contains($page, $needle)) {
        failMapTooltipContract("The map page translations must contain {$needle}.");
    }
}
foreach (['fillTileDistance: function', 'distanceBetween: function', 'Travian.Game.Map.fillTileDistance(e(t, o, i))'] as $needle) {
    if (!str_contains($client, $needle)) {
        failMapTooltipContract("The map client must contain {$needle}.");
    }
}

echo "Map tooltip contract passed.\n";
