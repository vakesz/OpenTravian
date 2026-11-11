<?php

declare(strict_types=1);

namespace Game\FarmList;

use Core\Database\DB;

/**
 * The notice about farm list targets that changed hands: every entry that points at the village or oasis is
 * deactivated (it stays on its list) and each list's owner is told once, until they close the notice.
 */
final class FarmListInbox
{
    /** Call when the village or oasis at $kid gets a new owner. */
    public static function targetChangedOwner(int $kid): void
    {
        $db = DB::getInstance();
        $db->run(
            'INSERT IGNORE INTO farmlist_deactivated_targets (uid, kid, created) SELECT DISTINCT f.owner, r.kid, ? FROM raidlist r INNER JOIN farmlist f ON f.id = r.lid WHERE r.kid = ? AND r.isActive = 1',
            [time(), $kid]
        );
        $db->run('UPDATE raidlist SET isActive = 0 WHERE kid = ?', [$kid]);
    }

    public static function close(int $uid): void
    {
        DB::getInstance()->run('DELETE FROM farmlist_deactivated_targets WHERE uid = ?', [$uid]);
    }
}
