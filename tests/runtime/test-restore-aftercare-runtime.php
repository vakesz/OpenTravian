<?php

declare(strict_types=1);

// The follow-up both restores apply (delay time and timed announcements) runs inside a transaction
// that is rolled back, so the live world is left as it was. Skipped loudly when no world is
// installed or when its schema patch version is behind (the follow-up would then run DDL, which
// cannot be rolled back).

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Restore aftercare runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
if (!is_file(SETUP_LOCK_FILE)) {
    fwrite(STDERR, "\n*** SKIPPED: restore aftercare: no world is installed.\n\n");
    echo "Restore aftercare runtime passed (SKIPPED).\n";
    exit(0);
}
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;
use Core\Database\RestoreAftercare;
use Game\WorldSchemaVersion;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$db = DB::getInstance();
if ((int)$db->fetchScalar('SELECT patchVersion FROM config') < WorldSchemaVersion::CURRENT) {
    fwrite(STDERR, "\n*** SKIPPED: restore aftercare: the world's patchVersion is behind " . WorldSchemaVersion::CURRENT . ", so the follow-up would run schema patches that cannot be rolled back.\n\n");
    echo "Restore aftercare runtime passed (SKIPPED).\n";
    exit(0);
}

$before = (int)$db->fetchScalar('SELECT delayTime FROM config');
$db->begin_transaction();
try {
    RestoreAftercare::apply(time() - 120);
    $delay = (int)$db->fetchScalar('SELECT delayTime FROM config');
    if ($delay < 120 || $delay > 125) {
        $fail("The follow-up must record the downtime since the backup (about 120 s), not $delay.");
    }
    foreach ([8, 9] as $type) {
        if ((int)$db->fetchScalar("SELECT COUNT(*) FROM infobox WHERE type = $type") !== 1) {
            $fail("The follow-up must leave exactly one announcement of type $type.");
        }
    }
} finally {
    $db->rollback();
}
if ((int)$db->fetchScalar('SELECT delayTime FROM config') !== $before) {
    $fail('The test must leave the world as it found it.');
}

echo "Restore aftercare runtime passed.\n";
