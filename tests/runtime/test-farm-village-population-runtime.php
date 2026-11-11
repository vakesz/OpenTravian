<?php

declare(strict_types=1);

// A farm village's resource level follows the population it is meant to have (50 to 300), from the
// real building tables. No world data is touched.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Farm village population runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Formulas;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
use Model\RegisterModel;

$types = Formulas::getFieldTypeResourceMap(3);
$population = static function (int $level) use ($types): int {
    $pop = 0;
    foreach ($types as $gid) {
        for ($step = 1; $step <= $level; ++$step) {
            $pop += Formulas::buildingCropConsumption($gid, $step);
        }
    }

    return (int) round($pop);
};
$previous = 0;
foreach ([50, 90, 150, 220, 250, 300] as $target) {
    $level = RegisterModel::farmLevelForPopulation($target, $types);
    $pop = $population($level);
    if ($level < $previous || $level > 20 || $pop < $target || ($level > 1 && $population($level - 1) >= $target)) {
        $fail("Population $target gave level $level with $pop inhabitants.");
    }
    $previous = $level;
}
if (RegisterModel::farmLevelForPopulation(300, $types) <= 10 || RegisterModel::farmLevelForPopulation(50, $types) > 10) {
    $fail('Only the largest farms need a level over 10, the ceiling of a village that is not a capital.');
}
echo "Farm village population runtime passed.\n";
