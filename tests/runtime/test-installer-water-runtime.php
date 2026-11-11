<?php

declare(strict_types=1);

// The installer's map generation places the water first and keeps it solid: 6 to 8 percent of the map is deep water,
// no water tile carries a village site, oasis, landscape or sprite, and almost no land tile is left standing inside a
// sea (the crop-seed sites used to leave one-tile islands everywhere).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Installer water runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Formulas;
use Model\InstallerModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
mt_srand(2468);
$installer = new InstallerModel();
$installer->mapToArray();
$installer->createRestOfTheMap();
$reflection = new ReflectionClass($installer);
$tiles = $reflection->getProperty('map')->getValue($installer);
$shore = $reflection->getProperty('shoreKids')->getValue($installer);

$water = [];
foreach ($tiles as $id => $tile) {
    if (!empty($tile['deepwater'])) {
        $water[$id] = true;
        if (!empty($tile['landscape']) || !empty($tile['oasistype']) || !empty($tile['fieldtype']) || ($tile['map']['parent']['img'] ?? '') !== '' || ($tile['map']['child']['img'] ?? '') !== '') {
            $fail("Water tile $id carries a village site, oasis, landscape or sprite.");
        }
    }
}
$share = count($water) / count($tiles);
($share >= 0.06 && $share <= 0.08) || $fail(sprintf('Deep water covers %.1f%% instead of 6 to 8%%.', $share * 100));
$shore !== [] || $fail('The map needs abandoned shores.');

$size = (int)MAP_SIZE;
$span = 2 * $size + 1;
$islands = 0;
foreach ($tiles as $id => $tile) {
    if (isset($water[$id])) {
        continue;
    }
    $xy = array_map('intval', Formulas::kid2xy((int)$id));
    $around = 0;
    foreach ([[-1, -1], [0, -1], [1, -1], [-1, 0], [1, 0], [-1, 1], [0, 1], [1, 1]] as [$dx, $dy]) {
        $x = (($xy['x'] + $dx + $size) % $span + $span) % $span - $size;
        $y = (($xy['y'] + $dy + $size) % $span + $span) % $span - $size;
        $around += isset($water[Formulas::xy2kid($x, $y)]) ? 1 : 0;
    }
    $islands += $around >= 6 ? 1 : 0;
}
$islands <= 0.01 * count($water) || $fail("$islands land tiles stand inside the seas (more than 1% of the water).");

echo "Installer water runtime passed.\n";
