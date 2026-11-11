<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Map\Map;

class InactiveModel
{
    /**
     * Rows that go with an account; every placeholder is the deleted player's id.
     */
    private const PLAYER_ROWS = [
        'DELETE FROM player_references WHERE ref_uid=?',
        'UPDATE auction SET maxSilver=silver, activeId=0, activeUid=0 WHERE activeUid=?',
        'DELETE FROM auction WHERE uid=?',
        'DELETE FROM bids WHERE uid=?',
        'DELETE FROM notes WHERE (uid=? OR to_uid=?)',
        'DELETE FROM accounting WHERE uid=?',
        'DELETE FROM ndata WHERE uid=?',
        'DELETE FROM mdata WHERE uid=?',
        'DELETE FROM users WHERE id=?',
        'DELETE FROM account_oauth_identity WHERE uid=?',
        'DELETE FROM hero WHERE uid=?',
        'DELETE FROM inventory WHERE uid=?',
        'DELETE FROM autoExtend WHERE uid=?',
        'DELETE FROM face WHERE uid=?',
        'DELETE FROM items WHERE uid=?',
        'DELETE FROM adventure WHERE uid=?',
        'DELETE FROM ali_invite WHERE uid=?',
        'DELETE FROM banQueue WHERE uid=?',
        'DELETE FROM banHistory WHERE uid=?',
        'DELETE FROM forum_open_players WHERE uid=?',
        'DELETE FROM activation_progress WHERE uid=?',
        'DELETE FROM links WHERE uid=?',
        'DELETE FROM ignoreList WHERE uid=? OR ignore_id=?',
        'DELETE FROM friendlist WHERE uid=? OR to_uid=?',
    ];

    /** Cleanup that runs once the map flags are gone; every placeholder is the deleted player's id. */
    private const PLAYER_ROWS_AFTER_FLAGS = [
        'DELETE FROM mapflag WHERE uid=?',
        'DELETE FROM medal WHERE uid=?',
        'DELETE FROM newproc WHERE uid=?',
        'UPDATE users SET sit1Uid=0, sit1Permissions=55 WHERE sit1Uid=?',
        'UPDATE users SET sit2Uid=0, sit2Permissions=55 WHERE sit2Uid=?',
    ];

    private function clearDeletion(): void
    {
        $db = DB::getInstance();
        $due = $db->run('SELECT * FROM deleting WHERE uid > 2 AND time <= ? ORDER BY time ASC LIMIT 100', [time()]);
        if ($due === false) {
            return;
        }
        $find = $due->get_result();
        $del = new AccountDeleter();
        $summary = new SummaryModel();
        $alliance = new AllianceModel();
        $mm = new AutomationModel();
        while ($row = $find->fetch_assoc()) {
            $uid = (int) $row['uid'];
            $db->run('DELETE FROM deleting WHERE uid=?', [$uid]);
            $userResult = $db->run(
                'SELECT id, name, email, email_verified, race, aid, gift_gold, bought_gold FROM users WHERE id=?',
                [$uid]
            );
            $user = $userResult === false ? null : $userResult->get_result()->fetch_assoc();
            if (!is_array($user)) {
                continue;
            }
            $aid = (int) $user['aid'];
            if ((int) $user['email_verified'] === 1) {
                $gold = floor((int) $user['bought_gold'] * (100 - Config::getProperty('gold', 'voucherTaxPercent')) / 100);
                if ($gold > 0) {
                    $mm->addVoucher((string) $user['email'], (int) $gold, 'remaining', sprintf('%s-%s', $user['id'], $user['name']));
                }
            }
            $villages = $db->run('SELECT kid FROM vdata WHERE owner=?', [$uid]);
            $villages = $villages === false ? false : $villages->get_result();
            while ($villages && ($village = $villages->fetch_assoc())) {
                $del->deleteVillage((int) $village['kid'], true);
            }
            $this->runForPlayer(self::PLAYER_ROWS, $uid);
            $this->removeMapFlags($uid, $aid);
            $this->runForPlayer(self::PLAYER_ROWS_AFTER_FLAGS, $uid);
            if ($aid) {
                $alliance->leaveAlliance($uid, $aid, true);
            }
            $summary->deletePlayerFromSummary((int) $user["race"]);
        }
        $find->free();
    }

    /**
     * @param list<string> $queries
     */
    private function runForPlayer(array $queries, int $uid): void
    {
        $db = DB::getInstance();
        foreach ($queries as $query) {
            $db->run($query, array_fill(0, substr_count($query, '?'), $uid));
        }
    }

    /** Drops the cached map overlays of the player's (and their alliance's) flags. */
    private function removeMapFlags(int $uid, int $aid): void
    {
        $statement = $aid
            ? DB::getInstance()->run('SELECT * FROM mapflag WHERE (targetId=? AND type=1) OR (targetId=? AND type=0)', [$aid, $uid])
            : DB::getInstance()->run('SELECT * FROM mapflag WHERE (targetId=? AND type=0)', [$uid]);
        $flags = $statement === false ? false : $statement->get_result();
        while ($flags && ($flag = $flags->fetch_assoc())) {
            $kind = (int) $flag['type'] === 0 ? 'player' : 'alliance';
            if ($flag['aid']) {
                Map::removeMapCacheForAlliance($flag['aid'], $kind, $flag['targetId']);
            } else {
                Map::removeMapCacheForPlayer($flag['uid'], $kind, $flag['targetId']);
            }
        }
    }

    public function startWorker(): void
    {
        $this->clearDeletion();
    }
}
