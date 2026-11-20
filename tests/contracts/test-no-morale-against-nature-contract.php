#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: morale (the population bonus) applies only between players. Battles and the
 * Rally Point simulator leave it out against free oases (nature), Natars and Wonders.
 * A runtime check cannot show it on worlds that ignore morale altogether, so this pins the source.
 */

$root = dirname(__DIR__, 2);

function failNoMoraleAgainstNature(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$battle = (string)file_get_contents($root . '/game/include/Model/BattleModel.php');
if (!str_contains($battle, "\$no_morale = \$this->defender['isWW'] || \$this->defender['uid'] == 1 || (\$this->defender['isOasis'] && !\$this->defender['isOccupied']);")) {
    failNoMoraleAgainstNature('Battles must not apply morale against a free oasis, Natars or a Wonder.');
}
$simulator = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/Simulator.php');
if (!preg_match('/\$pop_ratio = \$off_pop \/ \$def_pop;\s*if \(\$def_nature_only\) \{[^}]*\$pop_ratio = 1;/s', $simulator)) {
    failNoMoraleAgainstNature('The simulator must not apply morale when only nature defends.');
}

echo "No morale against nature contract passed.\n";
