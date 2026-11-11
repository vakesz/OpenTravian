<?php

declare(strict_types=1);

/**
 * Contract: every tribe a world may offer has a hero revival cost row, so reviving a hero (and the crop a starving
 * hero releases) never costs nothing. Spartans and Vikings use kirilloid's [150, 150, 150, 75] base row.
 */

use Game\Formulas;
use Game\Tribes;

function failHeroRevivalCostContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require_once dirname(__DIR__, 2) . '/game/include/bootstrap.php';

set_error_handler(static function (int $severity, string $message): bool {
    failHeroRevivalCostContract("Hero revival cost raised a PHP warning: $message");

    return true;
});
foreach (Tribes::SELECTABLE as $tribe) {
    foreach ([0, 10, 100] as $level) {
        $cost = Formulas::heroRegenerateCost($level, $tribe);
        if (count($cost) !== 4 || min($cost) <= 0) {
            failHeroRevivalCostContract("Tribe $tribe level $level revival cost is " . implode(',', $cost) . '; every resource must cost something.');
        }
    }
}
restore_error_handler();

foreach ([8, 9] as $tribe) {
    if (array_map('intval', Formulas::heroRegenerateCost(10, $tribe)) !== [2300, 2300, 2300, 1200]) {
        failHeroRevivalCostContract("Tribe $tribe level 10 revival cost must be 2300/2300/2300/1200.");
    }
}

echo "Hero revival cost contract passed.\n";
