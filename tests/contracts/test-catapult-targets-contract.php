<?php

declare(strict_types=1);

// One definition of the catapult targets feeds both the Rally Point form and the send request, and the aim
// rules for the Brewery celebration and the random-aim Cranny artefact.

require_once dirname(__DIR__, 2) . '/game/include/Game/CatapultTargets.php';

use Game\CatapultTargets;

function expectTargets(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

foreach ([1, 2] as $level) {
    expectTargets(CatapultTargets::allowed($level, true) === [99], "Rally Point $level allows only random.");
}
expectTargets(CatapultTargets::groups(3, true)['Infrastructure'] === [10, 11], 'Level 3 aims only at gids 10 and 11.');
expectTargets(CatapultTargets::groups(4, true)['Resources'] === [], 'Level 4 has no resource fields.');
expectTargets(CatapultTargets::groups(5, true)['Resources'] === range(1, 9), 'Level 5 adds the resource fields.');
expectTargets(CatapultTargets::groups(9, true)['Military'] === [], 'Level 9 has no military buildings.');

$top = CatapultTargets::groups(10, true);
expectTargets(in_array(29, $top['Military'], true) && in_array(30, $top['Military'], true), 'Level 10 must offer Great Barracks and Great Stable.');
expectTargets(array_slice($top['Military'], 0, 9) === [13, 14, 16, 19, 20, 21, 22, 29, 30], 'Great Barracks and Great Stable follow gid 22.');
expectTargets(in_array(40, $top['Infrastructure'], true), 'World Wonder is a target once plans are released.');
$noPlans = CatapultTargets::groups(20, false);
expectTargets(!in_array(40, $noPlans['Infrastructure'], true), 'World Wonder is hidden before plans are released.');
expectTargets(!in_array(40, CatapultTargets::allowed(20, false), true), 'The request must reject World Wonder before plans are released.');
expectTargets(in_array(29, CatapultTargets::allowed(10, false), true), 'The request accepts what the form lists.');

expectTargets(CatapultTargets::aim(15, false, 0) === 15, 'No artefact: the requested building.');
expectTargets(CatapultTargets::aim(15, true, 0) === 99, 'Brewery celebration: random.');
expectTargets(CatapultTargets::aim(40, true, 3) === 99, 'Brewery celebration beats the World Wonder exemption.');
expectTargets(CatapultTargets::aim(15, false, 1) === 99, 'Small artefact: other buildings are random.');
expectTargets(CatapultTargets::aim(27, false, 1) === 27, 'Small artefact: Treasury can be targeted.');
expectTargets(CatapultTargets::aim(27, false, 2) === 27, 'Large artefact: Treasury can be targeted.');
expectTargets(CatapultTargets::aim(27, false, 3) === 99, 'Unique artefact: Treasury is random.');
expectTargets(CatapultTargets::aim(40, false, 3) === 40, 'World Wonder can always be targeted.');

$sources = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/RallyPoint/SendTroops.php')
    . file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/RallyPoint/RallyPointHTML.php');
expectTargets(!str_contains($sources, '15, 17, 18, 24'), 'The target list must live only in CatapultTargets.');

echo "Catapult targets contract passed.\n";
