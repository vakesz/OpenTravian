<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Hero\HeroImage;
use Game\Tribes;

/**
 * Beginner-protection avatar respawn: wipe progress, keep identity/gold,
 * place a fresh capital in the chosen sector/tribe.
 */
final class AvatarRespawnModel
{
    public function canRespawn(int $uid): bool
    {
        if ($uid < 1) {
            return false;
        }
        $db = DB::getInstance();
        $protection = (int) $db->fetchScalar('SELECT protection FROM users WHERE id=?', [$uid], 0);

        return $protection >= time();
    }

    /**
     * @return int|false new village kid, or false on failure
     */
    public function respawn(int $uid, int $race, string $sector): int|false
    {
        if (!$this->canRespawn($uid)) {
            return false;
        }
        if (!in_array($race, Tribes::playable(), true)) {
            return false;
        }
        $sector = ['ne' => 'no', 'se' => 'so'][$sector] ?? $sector;
        if (!in_array($sector, ['nw', 'sw', 'no', 'so'], true)) {
            return false;
        }

        $db = DB::getInstance();
        $userStatement = $db->run('SELECT id, name, race, aid, gift_gold, bought_gold FROM users WHERE id=?', [$uid]);
        if ($userStatement === false) {
            return false;
        }
        $userResult = $userStatement->get_result();
        if (!$userResult || $userResult->num_rows < 1) {
            return false;
        }
        $user = $userResult->fetch_assoc();
        if (!is_array($user)) {
            return false;
        }
        $oldRace = (int) $user['race'];
        $register = new RegisterModel();
        $kid = $register->generateBase($sector);
        if (!$kid) {
            return false;
        }

        if ((int) $user['aid'] > 0) {
            (new AllianceModel())->leaveAlliance($uid, (int) $user['aid'], true);
        }

        $deleter = new AccountDeleter();
        // full=false: actually remove villages (full=true converts capital instead).
        $villagesStatement = $db->run('SELECT kid, capital FROM vdata WHERE owner=? ORDER BY capital ASC', [$uid]);
        $villages = $villagesStatement !== false ? $villagesStatement->get_result() : false;
        while ($villages && ($row = $villages->fetch_assoc())) {
            $deleter->deleteVillage((int) $row['kid'], false);
        }

        $this->purgeProgress($uid);

        $cp = Formulas::startingCulturePoints();
        $now = time();
        $db->run(
            "UPDATE users SET
                race=?,
                kid=?,
                total_pop=0,
                total_villages=0,
                cp=?,
                qst_economy='0,0,0,0,0,0,0,0,0,0,0,0',
                qst_world='0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0',
                qst_battle='0,0,0,0,0,0,0,0,0,0,0,0,0,0,0',
                total_attack_points=0,
                total_defense_points=0,
                week_attack_points=0,
                week_defense_points=0,
                week_robber_points=0,
                lastupdate=?,
                last_adventure_time=?,
                aid=0,
                alliance_role=0,
                alliance_role_name='',
                alliance_join_time=0
             WHERE id=?",
            [$race, $kid, $cp, $now, $now, $uid]
        );

        if ($oldRace !== $race) {
            $summary = new SummaryModel();
            if (Tribes::summaryColumn($oldRace) !== null) {
                $summary->deletePlayerFromSummary($oldRace);
            }
            if (Tribes::summaryColumn($race) !== null) {
                $summary->addPlayerToSummary($race);
            }
        }

        $created = $register->createBaseVillage($uid, (string) $user['name'], $race, $kid);
        if (!$created) {
            $db->run('UPDATE available_villages SET occupied=0 WHERE kid=?', [$kid]);
            $db->run('UPDATE wdata SET occupied=0 WHERE id=?', [$kid]);

            return false;
        }

        $this->resetHero($uid, $kid, $register);
        $db->run('DELETE FROM daily_quest WHERE uid=?', [$uid]);
        (new DailyQuestModel())->addDailyQuestRow($uid);
        InfoBoxModel::invalidateUserInfoBoxCache($uid);
        HeroImage::forget($uid);

        return $kid;
    }

    private function purgeProgress(int $uid): void
    {
        $db = DB::getInstance();
        $listsStatement = $db->run('SELECT id FROM farmlist WHERE owner=?', [$uid]);
        $lists = $listsStatement !== false ? $listsStatement->get_result() : false;
        while ($lists && ($list = $lists->fetch_assoc())) {
            $db->run('DELETE FROM raidlist WHERE lid=?', [(int) $list['id']]);
        }

        /** @var list<array{0: string, 1: list<int>}> $statements */
        $statements = [
            ['DELETE FROM adventure WHERE uid=?', [$uid]],
            ['DELETE FROM items WHERE uid=?', [$uid]],
            ['DELETE FROM auction WHERE uid=?', [$uid]],
            ['DELETE FROM bids WHERE uid=?', [$uid]],
            ['DELETE FROM notes WHERE uid=? OR to_uid=?', [$uid, $uid]],
            ['DELETE FROM ndata WHERE uid=?', [$uid]],
            ['DELETE FROM mdata WHERE uid=?', [$uid]],
            ['DELETE FROM links WHERE uid=?', [$uid]],
            ['DELETE FROM farmlist WHERE owner=?', [$uid]],
            ['DELETE FROM ali_invite WHERE uid=?', [$uid]],
            ['DELETE FROM medal WHERE uid=?', [$uid]],
            ['DELETE FROM newproc WHERE uid=?', [$uid]],
            ['DELETE FROM mapflag WHERE uid=?', [$uid]],
            ['DELETE FROM ignoreList WHERE uid=? OR ignore_id=?', [$uid, $uid]],
            ['DELETE FROM friendlist WHERE uid=? OR to_uid=?', [$uid, $uid]],
            ['DELETE FROM village_group_villages WHERE uid=?', [$uid]],
            ['DELETE FROM autoExtend WHERE uid=?', [$uid]],
        ];
        foreach ($statements as [$sql, $params]) {
            $db->run($sql, $params);
        }
    }

    private function resetHero(int $uid, int $kid, RegisterModel $register): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM hero WHERE uid=?', [$uid]);
        $db->run('DELETE FROM face WHERE uid=?', [$uid]);
        $db->run('DELETE FROM inventory WHERE uid=?', [$uid]);
        $register->addHero($uid, $kid);
        $register->addHeroFace($uid);
        $register->addHeroInventory($uid);
        $db->run('UPDATE units SET u11=1 WHERE kid=?', [$kid]);
    }

    public function syncSessionAfterRespawn(int $uid, int $kid, int $race): void
    {
        $session = Session::getInstance();
        if ((int) $session->getPlayerId() !== $uid) {
            return;
        }
        $session->set('kid', $kid);
        $session->set('race', $race);
        $session->set('total_pop', 0);
        $session->set('total_villages', 1);
    }
}
