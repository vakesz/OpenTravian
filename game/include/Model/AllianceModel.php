<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\Formulas;
use Game\Map\Map;
use InvalidArgumentException;
use mysqli_result;

use function getCustom;

class AllianceModel
{
    public const ASSIGN_TO_POSITION = 1;
    public const KICK_PLAYER = 2;
    public const CHANGE_ALLIANCE_DESC = 4;
    public const ALLIANCE_DIPLOMACY = 8;
    public const IGM_MESSAGE = 16;
    public const INVITE_PLAYER = 32;
    public const MANAGE_FORUM = 64;
    public const MANAGE_MARKS = 128;
    public const MANAGE_SPECIALIZATION = 256;

    public const LOG_JOINED = 1;
    public const LOG_LEFT = 2;
    public const LOG_INVITE = 3;
    public const LOG_NEW_VILLAGE = 4;
    public const LOG_DIPLOMACY_CONF = 5;
    public const LOG_DIPLOMACY_CONF_ACCEPTED = 6;
    public const LOG_DIPLOMACY_CONF_REFUSE = 7;
    public const LOG_DIPLOMACY_NAP = 8;
    public const LOG_DIPLOMACY_NAP_ACCEPTED = 9;
    public const LOG_DIPLOMACY_NAP_REFUSE = 10;
    public const LOG_DIPLOMACY_WAR = 11;
    public const LOG_DIPLOMACY_WAR_ACCEPTED = 12;
    public const LOG_DIPLOMACY_WAR_REFUSE = 13;
    public const LOG_KICK = 14;

    /** Every right an alliance founder holds. */
    private const ALL_RIGHTS = self::ASSIGN_TO_POSITION | self::KICK_PLAYER | self::CHANGE_ALLIANCE_DESC
        | self::ALLIANCE_DIPLOMACY | self::IGM_MESSAGE | self::INVITE_PLAYER | self::MANAGE_FORUM | self::MANAGE_MARKS | self::MANAGE_SPECIALIZATION;

    public function setAlliancePointsByUid(int $uid, int|float $attack, int|float $defense, int|float $rob = 0): void
    {
        DB::getInstance()->run(
            'UPDATE alidata a, users u SET
                a.total_attack_points=a.total_attack_points+?,
                a.week_attack_points=a.week_attack_points+?,
                a.total_defense_points=a.total_defense_points+?,
                a.week_defense_points=a.week_defense_points+?,
                a.week_robber_points=a.week_robber_points+?
            WHERE u.id=? AND a.id=u.aid',
            [$attack, $attack, $defense, $defense, $rob, $uid]
        );
    }

    public function setAlliancePoints(int $aid, int|float $attack, int|float $defense, int|float $rob = 0): void
    {
        DB::getInstance()->run(
            'UPDATE alidata SET
                total_attack_points=total_attack_points+?,
                week_attack_points=week_attack_points+?,
                total_defense_points=total_defense_points+?,
                week_defense_points=week_defense_points+?,
                week_robber_points=week_robber_points+?
            WHERE id=?',
            [$attack, $attack, $defense, $defense, $rob, $aid]
        );
    }

    /** One column of the alliance row (callers pass literal column names), false when it does not exist. */
    public function getAllianceField(int|string $id, string $column): mixed
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid alliance column.');
        }

        return DB::getInstance()->fetchScalar("SELECT $column FROM alidata WHERE id=" . (int) $id . ' LIMIT 1');
    }

    public function getInvitesCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM ali_invite WHERE uid=$uid");
    }

    public function getInvitesForUser(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM ali_invite WHERE uid=$uid");
    }

    /**
     * Highest-level embassy for the player, including village kid and field slot.
     *
     * @return array{kid:int,embassy:int,fieldId:int}|null
     */
    public function getMaxPlayerEmbassyLvl(int $uid): ?array
    {
        $find = DB::getInstance()->query(
            "SELECT fdata.* FROM fdata, vdata WHERE fdata.kid=vdata.kid AND vdata.owner={$uid} ORDER BY fdata.embassy DESC LIMIT 1"
        );
        $row = $find instanceof mysqli_result ? $find->fetch_assoc() : null;
        if (!is_array($row)) {
            return null;
        }
        $fieldId = 0;
        for ($i = 19; $i <= 40; $i++) {
            if ((int) ($row['f' . $i . 't'] ?? 0) === 18) {
                $fieldId = $i;
                break;
            }
        }

        return [
            'kid' => (int) $row['kid'],
            'embassy' => (int) $row['embassy'],
            'fieldId' => $fieldId,
        ];
    }

    /**
     * quit link: /build.php?newdid=&tt=leave&id=
     */
    public function getQuitAllianceHref(int $uid): ?string
    {
        $embassy = $this->getMaxPlayerEmbassyLvl($uid);
        if ($embassy === null || $embassy['embassy'] < 1 || $embassy['fieldId'] < 1) {
            return null;
        }

        return '/build.php?newdid=' . $embassy['kid'] . '&tt=leave&id=' . $embassy['fieldId'];
    }

    /**
     * @return int 1: already invited, -1: unknown player, 22222: invitation sent
     */
    public function sendInvite(int $invite_uid, int $aid, int $uid): int
    {
        $db = DB::getInstance();
        if ((int) $db->fetchScalar("SELECT COUNT(id) FROM ali_invite WHERE aid=$aid AND uid=$uid")) {
            return 1;
        }
        if (!(int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE id=$uid")) {
            return -1;
        }
        $db->run('INSERT INTO ali_invite (from_uid, aid, uid) VALUES (?, ?, ?)', [$invite_uid, $aid, $uid]);
        $FromPlayerName = $db->fetchScalar("SELECT name FROM users WHERE id=$invite_uid");
        $ToPlayerName = $db->fetchScalar("SELECT name FROM users WHERE id=$uid");
        $ToAllianceName = $db->fetchScalar("SELECT name FROM alidata WHERE id=$aid");
        $now = time();
        if ($db->fetchScalar("SELECT allianceNotificationEnabled FROM users WHERE id=$uid")) {
            $sentBefore = $db->fetchScalar("SELECT COUNT(id) FROM alliance_notification WHERE aid=$aid AND to_uid=$uid AND type=1 AND $now-time <= 86400*2");
            if (!$sentBefore) {
                $data = [$ToPlayerName, $FromPlayerName, $ToAllianceName];
                (new MessageModel())->sendMessage(1, $uid, '', serialize($data), 1);
                $db->run('INSERT INTO alliance_notification (aid, to_uid, type, time) VALUES (?, ?, 1, ?)', [$aid, $uid, $now]);
            }
        }
        $this->addLog(
            $aid,
            [self::LOG_INVITE, $invite_uid, $FromPlayerName, $uid, $ToPlayerName],
            time()
        );

        return 22222;
    }

    /**
     * @param array<int, mixed>|string $params log fields (type first), or the already joined "type:field:..." string
     */
    public function addLog(int $aid, array|string $params, int $time): void
    {
        if (is_array($params)) {
            $type = (string) $params[0];
            $params = implode(':', $params);
        } else {
            $type = explode(':', $params)[0];
        }
        DB::getInstance()->run('INSERT INTO ali_log (aid, type, data, time) VALUES (?, ?, ?, ?)', [$aid, $type, $params, $time]);
    }

    public function kickPlayer(int $kicker_uid, int $uid, int $aid): void
    {
        $db = DB::getInstance();
        $this->nullifyPlayerAllianceInfo($aid, $uid);
        if ($db->affectedRows()) {
            $this->addLog(
                $aid,
                [
                    self::LOG_KICK,
                    $kicker_uid,
                    $db->fetchScalar("SELECT name FROM users WHERE id=$kicker_uid"),
                    $uid,
                    $db->fetchScalar("SELECT name FROM users WHERE id=$uid"),
                ],
                time()
            );
            Map::allianceJoinOrLeaveCacheUpdate($uid, $aid);
            $this->recalculateMaxUsers($aid);
        }
    }

    public function recalculateMaxUsers(int $aid): void
    {
        $db = DB::getInstance();
        $max = (int) $db->fetchScalar(
            "SELECT max(fdata.embassy) FROM fdata, vdata, users WHERE users.aid=$aid AND users.id=vdata.owner AND fdata.kid=vdata.kid"
        );
        $max = (int) Formulas::getEmbassyMembersCount($max);
        $db->run('UPDATE alidata SET max=? WHERE id=?', [$max, $aid]);
    }

    private function nullifyPlayerAllianceInfo(int $aid, int $uid): void
    {
        $columns = array_merge(
            AllianceBonusModel::USER_TOTAL_CONTRIBUTION_PARAMS,
            AllianceBonusModel::USER_WEEK_CONTRIBUTION_PARAMS,
            AllianceBonusModel::USER_UNLOCK_PENDING_ANIMATION
        );
        $reset = '';
        foreach ($columns as $column) {
            $reset .= "$column=0,";
        }
        DB::getInstance()->run("UPDATE users SET $reset aid=0, alliance_role=0, alliance_role_name='' WHERE aid=? AND id=?", [$aid, $uid]);
    }

    public function leaveAlliance(int $uid, int $aid, bool $clearDeletion = false): void
    {
        $db = DB::getInstance();
        $now_members = (int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE aid=$aid") + ($clearDeletion ? 1 : 0);
        if ($now_members <= 1) {
            //it's me alone.
            $db->query("UPDATE users SET aid=0, alliance_role=0, alliance_role_name='' WHERE aid=$aid");
            $db->query("DELETE FROM allimedal WHERE id=$aid");
            $db->query("DELETE FROM alidata WHERE id=$aid");
            $db->query("DELETE FROM alistats WHERE aid=$aid");
            $db->query("DELETE FROM mapflag WHERE aid=$aid");
            $db->query("DELETE FROM ali_invite WHERE aid=$aid");
            Map::allianceDeleteDiplomacyUpdate($aid);
            $db->query("DELETE FROM diplomacy WHERE aid1=$aid OR aid2=$aid");
            (new MarketModel())->cancelAllOffersForAlliance($uid);

            return;
        }
        $this->nullifyPlayerAllianceInfo($aid, $uid);

        (new MarketModel())->cancelAllOffersForAlliance($uid);
        $this->recalculateMaxUsers($aid);
        // The player no longer belongs to the alliance here, so the name is looked up by id alone.
        $this->addLog($aid, [self::LOG_LEFT, $uid, $db->fetchScalar("SELECT name FROM users WHERE id=$uid")], time());
        Map::allianceJoinOrLeaveCacheUpdate($uid, $aid);
        $this->ensureAllianceHasLeader($aid);
    }

    /** Hands every right to one member when nobody in the alliance holds them all. */
    private function ensureAllianceHasLeader(int $aid): void
    {
        $db = DB::getInstance();
        $rights = self::ALL_RIGHTS;
        if ((int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE alliance_role=$rights AND aid=$aid") >= 1) {
            return;
        }
        $uid = (int) $db->fetchScalar("SELECT id FROM users WHERE aid=$aid LIMIT 1");
        if ($uid) {
            $db->run('UPDATE users SET alliance_role=? WHERE id=?', [$rights, $uid]);
        }
    }

    public function deleteInviteForPlayer(int $uid, int $id): void
    {
        DB::getInstance()->run('DELETE FROM ali_invite WHERE uid=? AND id=?', [$uid, $id]);
    }

    /**
     * @return int|false the new alliance id, false when the tag is already taken
     */
    public function createAlliance(int $uid, string $name, string $tag): int|false
    {
        $db = DB::getInstance();
        // alidata.tag is UNIQUE: a concurrent founder with the same tag loses here.
        try {
            $db->run('INSERT INTO alidata (name, tag) VALUES (?, ?)', [$name, $tag]);
        } catch (\mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                return false;
            }
            throw $exception;
        }
        $aid = (int) $db->lastInsertId();
        $info1 = "[stats]strength[/stats]\n\n[stats]fighting points[/stats]";
        $info2 = '[news]10[/news]';
        $db->run('UPDATE alidata SET info1=?, info2=? WHERE id=?', [$info1, $info2, $aid]);
        $db->run(
            'UPDATE users SET aid=?, alliance_role=?, alliance_join_time=?, alliance_role_name=? WHERE id=?',
            [$aid, self::ALL_RIGHTS, time(), T('Buildings', 'Alliance Founder'), $uid]
        );
        $db->run('UPDATE alidata SET oldPop=? WHERE id=?', [(int) $db->fetchScalar("SELECT SUM(pop) FROM vdata WHERE owner=$uid"), $aid]);
        $this->recalculateMaxUsers($aid);
        \Game\Webhook\WorldEvents::record('allianceFounded', (string) $aid, ['alliance' => ['name' => $name, 'tag' => $tag]]);
        $this->addLog($aid, [self::LOG_JOINED, $uid, $db->fetchScalar("SELECT name FROM users WHERE id=$uid")], time());

        return $aid;
    }

    /**
     * @return int|false the alliance joined, -1 when it has no free slot, false when the invitation is not the player's
     */
    public function acceptInvite(int $uid, int $id): int|false
    {
        $db = DB::getInstance();
        $aid = (int) $db->fetchScalar("SELECT aid FROM ali_invite WHERE id=$id AND uid=$uid");
        if (!$aid) {
            return false;
        }
        $max = min((int) getCustom('allianceMembersLimit'), (int) $db->fetchScalar("SELECT max FROM alidata WHERE id=$aid"));
        if ((int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE aid=$aid") >= $max) {
            return -1;//no empty slots.
        }
        $db->run('UPDATE users SET aid=?, alliance_join_time=? WHERE id=?', [$aid, time(), $uid]);
        $this->recalculateMaxUsers($aid);
        $db->run('DELETE FROM ali_invite WHERE id=?', [$id]);
        $this->addLog($aid, [self::LOG_JOINED, $uid, $db->fetchScalar("SELECT name FROM users WHERE id=$uid")], time());
        $this->ensureAllianceHasLeader($aid);
        Map::allianceJoinOrLeaveCacheUpdate($uid, $aid);

        return $aid;
    }
}
