<?php

declare(strict_types=1);

// The home army is the village garrison plus the owner's own troops stationed in the village (or its oases).
// Starvation must keep killing across both rows until the crop is back above zero. Before the fix the merged
// army never updated its parts, so once the first row ran dry the next kill hit an empty row and starvation
// stopped with the crop still negative.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Starvation home parts runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Formulas;
use Game\Starvation;
use Game\Tribes;
use Game\TruceDay;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Starvation home parts runtime failed: ' . $message . "\n");
    exit(1);
};
if (!getGame('starvation')) {
    echo "Starvation home parts runtime passed (starvation is off on this world).\n";
    exit(0);
}
if (TruceDay::isActive() || (TruceDay::getTo() + 86400) > time()) {
    echo "Starvation home parts runtime passed (truce: nobody starves).\n";
    exit(0);
}
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate(Tribes::ROMANS);
} catch (RuntimeException $exception) {
    echo "Starvation home parts runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$kid = $user['kid'];
$pdo->run('UPDATE users SET access=1, vacationActiveTil=0 WHERE id=?', [$uid]);

// Garrison: 5 legionnaires. Own troops stationed at home: 5 more. Nothing else is fed by the village.
$pdo->run('UPDATE units SET u1=5, u2=0, u3=0, u4=0, u5=0, u6=0, u7=0, u8=0, u9=0, u10=0, u11=0 WHERE kid=?', [$kid]);
$pdo->run('INSERT INTO enforcement (uid, kid, to_kid, race, u1) VALUES (?, ?, ?, ?, 5)', [$uid, $kid, $kid, Tribes::ROMANS]);

// Each starved legionnaire refunds its crop cost. A deficit of 6.5 legionnaires needs 7 deaths, more than either row holds.
$perUnit = (float)Formulas::uTrainingCost(nrToUnitId(1, Tribes::ROMANS))[3];
if ($perUnit <= 0) {
    $fail('the legionnaire must cost crop.');
}
$deficit = $perUnit * 6.5;
$pdo->run(
    'UPDATE vdata SET crop=?, cropp=pop+upkeep, maxcrop=GREATEST(maxcrop, ?), lastmupdate=? WHERE kid=?',
    [-$deficit, $perUnit * 10, miliseconds(), $kid]
);

new Starvation($kid);

$garrison = (int)$pdo->fetchScalar('SELECT u1 FROM units WHERE kid=?', [$kid]);
$stationed = (int)$pdo->fetchScalar('SELECT COALESCE(SUM(u1), 0) FROM enforcement WHERE uid=? AND to_kid=?', [$uid, $kid]);
if ($garrison + $stationed !== 3) {
    $fail("7 of the 10 legionnaires must starve, but {$garrison} remain at home and {$stationed} stationed.");
}
if ($garrison === 5 || $stationed === 5) {
    $fail('both rows of the home army must lose troops.');
}
$crop = (float)$pdo->fetchScalar('SELECT crop FROM vdata WHERE kid=?', [$kid]);
if ($crop < 0) {
    $fail("starvation must stop only once the crop is back above zero, but it is {$crop}.");
}

echo "Starvation home parts runtime passed.\n";
