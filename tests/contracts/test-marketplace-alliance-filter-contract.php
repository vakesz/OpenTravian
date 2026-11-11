<?php

declare(strict_types=1);

/**
 * Contract: Marketplace Buy (gid17 t=1) "Only alliance" filter. The filter group
 * (label + button.toggle > i.allianceFilter) narrows the offer list to offers made from
 * villages of the viewer's alliance members and survives paging, sorting filters and
 * accepting an offer.
 */

function failAllianceFilterContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failAllianceFilterContract("Unable to read $path.");
    }

    return $source;
};

$template = $read('game/include/resources/Templates/build/building/MarketplaceBuy.php');
foreach (['"Only alliance"', 'class="allianceFilter"', "'&amp;al=1'", 'filterGroup'] as $needle) {
    if (!str_contains($template, $needle)) {
        failAllianceFilterContract("Buy template must render the Only alliance filter: $needle");
    }
}

$controller = $read('game/include/Controller/Build/MarketPlaceCtrl.php');
foreach ([
    "\$view->vars['al'] = !empty(\$_GET['al']) ? 1 : 0;",
    "(bool)\$view->vars['al']);",
    "\$prefix['al'] = \$view->vars['al'];",
    '&al={$al}',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failAllianceFilterContract("Marketplace controller must carry the filter through offers, paging and accept links: $needle");
    }
}
if (substr_count($controller, "(bool)\$view->vars['al']);") < 2) {
    failAllianceFilterContract('Both the offer list and the offer count must apply the Only alliance filter.');
}

$model = $read('game/include/Model/MarketModel.php');
// The count and the list share one filter (offerFilter), so each clause appears once.
if (substr_count($model, 'INNER JOIN users ON users.id=vdata.owner WHERE users.aid=') !== 1
    || substr_count($model, "'1=0'") !== 1
    || substr_count($model, 'offerFilter(') < 3) {
    failAllianceFilterContract('The model must restrict to alliance members (and show nothing without an alliance) in both queries.');
}

$translation = $read('game/include/resources/Translation/en-US.php');
if (!str_contains($translation, '["Only alliance"] = \'Only alliance\'')) {
    failAllianceFilterContract('The Only alliance label must be translated.');
}

echo "Marketplace alliance filter contract passed.\n";
