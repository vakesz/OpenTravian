<?php

namespace Core;

use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\Mailer;
use Core\Helper\Notification;
use Core\Jobs\TransactionalTask;
use Exception;
use Game\AllianceBonus\AllianceBonus;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Game\NoticeHelper;
use Game\WorldSpeedProfile;
use Model\AccountDeleter;
use Model\AllianceBonusModel;
use Model\ArtefactsModel;
use Model\AutomationModel;
use Model\BattleModel;
use Model\ClubApi;
use Model\InfoBoxModel;
use Model\MarketModel;
use Model\MarketPlaceProcessor;
use Model\MasterBuilder;
use Model\MessageModel;
use Model\Movements\AdventureProcessor;
use Model\Movements\EvasionProcessor;
use Model\Movements\ReinforcementProcessor;
use Model\Movements\ReturnProcessor;
use Model\Movements\SettlersProcessor;
use Model\MovementsModel;
use Model\MultiAccount;
use Model\NatarsModel;
use Model\OasesModel;
use Model\PublicMsgModel;
use Model\RegisterModel;
use Model\TrainingModel;
use Model\VillageModel;
use resources\View\PHPBatchView;

use function logError;
use function microtime;
use function getCustom;
use function getGameElapsedSeconds;
use function getGameSpeed;
use function getWorldId;
use function getWorldUniqueId;
use function miliseconds;
use function nanoseconds;
use function strtolower;

use const INCLUDE_PATH;
use const MAP_SIZE;

class Automation
{
    private const FAKE_ADJECTIVES = [
        'Swift', 'Brave', 'Calm', 'Bold', 'Wise', 'Keen', 'Grim', 'Lucky', 'Quiet', 'Proud', 'Rusty', 'Sunny',
        'Misty', 'Stout', 'Fierce', 'Gentle', 'Noble', 'Sly', 'Merry', 'Iron', 'Silver', 'Golden', 'Wild', 'Lone',
    ];
    private const FAKE_NOUNS = [
        'Fox', 'Wolf', 'Bear', 'Hawk', 'Raven', 'Stag', 'Boar', 'Lynx', 'Otter', 'Eagle', 'Viking', 'Knight',
        'Archer', 'Smith', 'Miller', 'Baker', 'Rider', 'Ranger', 'Scout', 'Brewer', 'Falcon', 'Badger', 'Pike', 'Oak',
    ];

    /** @var \Core\Automation|null */
    private static $_self;
    /** @var int */
    private static $zeroPopCursor = 0;

    /**
     * @return \Core\Automation
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    /**
     * Runs one row of a batch so a single bad row is logged instead of aborting the rest of the batch.
     * Lost connections are rethrown because every following row would fail the same way.
     */
    public function guardRow(string $what, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            if (in_array((int)$exception->getCode(), [2006, 2013], true)) {
                throw $exception;
            }
            logError("$what failed: " . $exception->getMessage());
        }
    }

    public function buildComplete(): void
    {
        $db = DB::getInstance();
        $m = new MasterBuilder();
        $result = $db->query("SELECT id, isMaster FROM building_upgrade WHERE commence<=" . (time()) . " ORDER BY commence ASC, id ASC LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Building task {$row['id']}", function () use ($row, $m): void {
                $this->processBuildingRow((int)$row['id'], (bool)$row['isMaster'], $m);
            });
        }
        $result = $db->query("SELECT id FROM demolition WHERE end_time <= " . (time()) . " ORDER BY end_time ASC, id ASC LIMIT 50");
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Demolition task {$row['id']}", function () use ($row): void {
                $this->processDemolitionTask((int)$row['id']);
            });
        }
    }

    /** A due construction row: the master builder's own step, or the plain upgrade. */
    public function processBuildingRow(int $taskId, bool $isMaster, ?MasterBuilder $masterBuilder = null): bool
    {
        if (!$isMaster) {
            return $this->processBuildingTask($taskId);
        }
        $masterBuilder ??= new MasterBuilder();

        return TransactionalTask::process('building_upgrade', $taskId, function (array $task) use ($masterBuilder): void {
            $masterBuilder->process($task);
        });
    }

    public function processBuildingTask(int $taskId): bool
    {
        return TransactionalTask::consume('building_upgrade', $taskId, function (array $row): void {
            BuildingAction::upgrade((int)$row['kid'], (int)$row['building_field']);
            // The next level of a multi-level order takes over the worker this level held.
            DB::getInstance()->run(
                'UPDATE building_upgrade SET chained=0 WHERE kid=? AND building_field=? AND isMaster=0 AND chained=1 ORDER BY commence ASC, id ASC LIMIT 1',
                [(int)$row['kid'], (int)$row['building_field']]
            );
        });
    }

    public function processDemolitionTask(int $taskId): bool
    {
        return TransactionalTask::consume('demolition', $taskId, function (array $row): void {
            BuildingAction::downgrade(
                (int)$row['kid'],
                (int)$row['building_field'],
                1,
                (bool)$row['complete']
            );
        });
    }


    public function attackMovementComplete(): void
    {
        $db = DB::getInstance();
        $movements = $db->query("SELECT id FROM movement WHERE mode=0 AND end_time <= " . miliseconds() . " ORDER BY end_time ASC, id ASC LIMIT 250");
        $this->processMovementComplete($movements);
    }

    public function otherMovementComplete(): void
    {
        $db = DB::getInstance();
        $movements = $db->query("SELECT id FROM movement WHERE mode=1 AND end_time <= " . miliseconds() . " ORDER BY end_time ASC, id ASC LIMIT 250");
        $this->processMovementComplete($movements);
    }

    public function processMovementComplete(\mysqli_result $movements): void
    {
        mt_srand(make_seed());
        while ($row = $movements->fetch_assoc()) {
            $this->guardRow("Movement task {$row['id']}", function () use ($row): void {
                $this->processMovementTask((int)$row['id']);
            });
        }
    }

    public function processMovementTask(int $taskId): bool
    {
        return TransactionalTask::consume('movement', $taskId, function (array $row): void {
            $row = MovementsModel::typedRow($row);
            \Game\Ships::dockOnArrival($row);
            if ($row['mode'] == 1) {
                new ReturnProcessor($row);

                return;
            }
            switch ($row['attack_type']) {
                case MovementsModel::ATTACKTYPE_EVASION:
                    new EvasionProcessor($row);
                    break;
                case MovementsModel::ATTACKTYPE_REINFORCEMENT:
                    new ReinforcementProcessor($row);
                    break;
                case MovementsModel::ATTACKTYPE_NORMAL:
                case MovementsModel::ATTACKTYPE_RAID:
                case MovementsModel::ATTACKTYPE_SPY:
                    new BattleModel($row);
                    break;
                case MovementsModel::ATTACKTYPE_ADVENTURE:
                    new AdventureProcessor($row);
                    break;
                case MovementsModel::ATTACKTYPE_SETTLERS:
                    new SettlersProcessor($row);
                    break;
            }
        });
    }

    public function handleAllianceBonusTasks(): void
    {
        $db = DB::getInstance();
        $config = Config::getInstance();
        $donate_reset_interval = $config->allianceBonus->donate_reset_interval;
        $lastDonateReset = $db->fetchScalar("SELECT lastAllianceContributeReset FROM config");
        if ($lastDonateReset < $config->game->start_time) {
            $lastDonateReset = $config->game->start_time;
            $db->query("UPDATE config SET lastAllianceContributeReset=$lastDonateReset");
        }
        if (!(($lastDonateReset + $donate_reset_interval) > time())) {
            $lastDonateReset = $lastDonateReset + $donate_reset_interval;
            $db->query("UPDATE config SET lastAllianceContributeReset=" . $lastDonateReset);
            $db->query("UPDATE users SET alliance_contributions=0");
        }
        $m = new AllianceBonusModel();
        $stmt = $db->query("SELECT * FROM alliance_bonus_upgrade_queue WHERE time < " . time() . " LIMIT 100");
        while ($row = $stmt->fetch_assoc()) {
            $db->query("DELETE FROM alliance_bonus_upgrade_queue WHERE id={$row['id']}");
            $m->levelUpBonus($row['aid'], $row['type']);
        }
    }

    public function marketComplete(): void
    {
        $db = DB::getInstance();
        $time = (time());
        $result = $db->query("SELECT id FROM send WHERE end_time < $time ORDER BY end_time ASC, id ASC LIMIT 200");
        $processor = new MarketPlaceProcessor();
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Market task {$row['id']}", function () use ($row, $processor): void {
                $this->processMarketTask((int)$row['id'], $processor);
            });
        }
    }

    public function processMarketTask(int $taskId, ?MarketPlaceProcessor $processor = null): bool
    {
        $processor ??= new MarketPlaceProcessor();

        return TransactionalTask::consume('send', $taskId, function (array $send) use ($processor): void {
            $processor->processRow($send);
        });
    }

    public function researchComplete(): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT id FROM research WHERE end_time <= " . (time()) . " ORDER BY end_time ASC, id ASC LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Research task {$row['id']}", function () use ($row): void {
                $this->processResearchTask((int)$row['id']);
            });
        }
    }

    public function processResearchTask(int $taskId): bool
    {
        return TransactionalTask::consume('research', $taskId, function (array $row) use ($taskId): void {
            $db = DB::getInstance();
            $kid = (int)$row['kid'];
            $nr = (int)$row['nr'];
            $mode = (int)$row['mode'];
            if ($mode === 1 && $nr >= 2 && $nr <= 9) {
                $db->query("UPDATE tdata SET u$nr=1 WHERE kid=$kid");

                return;
            }
            if ($mode === 0 && $nr >= 1 && $nr <= 8) {
                $db->query("UPDATE smithy SET u$nr=IF(u$nr+1>20, 20, u$nr+1) WHERE kid=$kid");

                return;
            }

            throw new \Core\Jobs\QuarantineTaskException("Malformed research task $taskId.");
        });
    }

    /**
     * Processes due training queues. The optional row ID is a narrow runtime
     * test seam; normal automation always processes the complete due set.
     */
    public function trainingComplete(?int $onlyTrainingId = null): void
    {
        $db = DB::getInstance();
        $training = new TrainingModel();
        $time = getGame("useNanoseconds") ? nanoseconds() : (getGame("useMilSeconds") ? miliseconds() : time());
        $immediate_train = implode(",", [9, 10, 11]);
        $idFilter = $onlyTrainingId === null ? '' : ' AND id=' . (int)$onlyTrainingId;
        $result = $db->query("SELECT * FROM training WHERE nr IN($immediate_train)$idFilter AND commence < $time LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Training task {$row['id']}", function () use ($training, $row): void {
                $training->handleTrainingCompleteResult($row);
            });
        }
        $time = getGame("useNanoseconds") ? nanoseconds() : (getGame("useMilSeconds") ? miliseconds() : time());
        $result = $db->query("SELECT * FROM training WHERE nr NOT IN($immediate_train)$idFilter AND commence < $time LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Training task {$row['id']}", function () use ($training, $row): void {
                $training->handleTrainingCompleteResult($row);
            });
        }
    }

    public function zeroPopVillages(): void
    {
        $db = DB::getInstance();
        // Villages that cannot be destroyed stay in the table, so page through by kid instead of re-reading them.
        $result = $db->query("SELECT kid, owner FROM vdata WHERE pop<=0 AND isWW=0 AND kid>" . self::$zeroPopCursor . " ORDER BY kid ASC LIMIT 50");
        $accountDeleter = new AccountDeleter();
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            ++$count;
            self::$zeroPopCursor = (int)$row['kid'];
            $this->guardRow("Zero population village {$row['kid']}", function () use ($accountDeleter, $row): void {
                if (true === $accountDeleter->isVillageDestroyAble(0, $row['kid'], $row['owner'])) {
                    $accountDeleter->deleteVillage($row['kid']);
                }
            });
        }
        if ($count < 50) {
            self::$zeroPopCursor = 0;
        }
    }

    public function checkForArtifactActivation(): void
    {
        $db = DB::getInstance();
        $interval = ArtefactsModel::getArtifactActivationTime();
        $time = time() - $interval;
        $result = $db->query("SELECT * FROM artefacts WHERE active=0 AND IF(uid=1, FALSE, conquered < $time) ORDER BY conquered ASC, id ASC");
        $art = new ArtefactsModel();
        while ($row = $result->fetch_assoc()) {
            $art->activateArtifact($row);
        }
    }

    public function updateFoolArtifact(): void
    {
        $artifact = new ArtefactsModel();
        $artifact->updateFoolArtes();
    }

    public function boughtGoldMessage(): void
    {
        $db = DB::getInstance();
        $message = new MessageModel();
        $result = $db->query("SELECT * FROM buyGoldMessages LIMIT 50");
        while ($row = $result->fetch_assoc()) {
            $db->query("DELETE FROM buyGoldMessages WHERE id={$row['id']}");
            if ($row['type'] == 1) {
                $title = T("Global", "BuyGoldSubject");
                $msg = sprintf(T("Global", "BuyGoldText"), $row['gold'], $row['trackingCode']);
            } else {
                $title = T("Global", "voucherTitle");
                $msg = sprintf(T("Global", "voucherText"), $row['gold'], $row['trackingCode']);
            }
            $message->sendMessage(0, $row['uid'], $title, $msg);
        }
    }

    public function banProgress(): void
    {
        (new MultiAccount())->runProgress();
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM banQueue WHERE end>0 AND end < " . time() . " LIMIT 10");
        $infoBox = new InfoBoxModel();
        while ($row = $result->fetch_assoc()) {
            $db->query("DELETE FROM banQueue WHERE id={$row['id']}");
            $db->query("UPDATE users SET access=1 WHERE id={$row['uid']}");
            $infoBox->deleteInfoByType($row['uid'], 14);
        }
    }

    public function referenceCheck(): void
    {
        $inviteGold = Config::getProperty("gold", "invitePlayerGold");
        $refLimit = Config::getAdvancedProperty("refLimit");

        $db = DB::getInstance();
        // References whose invited player no longer exists (or never settled) are dropped.
        $db->query("DELETE pr FROM player_references pr LEFT JOIN users u ON u.id=pr.uid WHERE pr.rewardGiven=0 AND (u.id IS NULL OR u.total_villages=0)");
        // Only actionable rows (the invited player has a second village) are selected so the rest cannot starve them.
        $result = $db->query("SELECT pr.id, pr.ref_uid FROM player_references pr INNER JOIN users u ON u.id=pr.uid WHERE pr.rewardGiven=0 AND u.total_villages>=2 ORDER BY pr.id ASC LIMIT 100");
        $rewarded = [];
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Reference {$row['id']}", function () use ($db, $row, $refLimit, $inviteGold, &$rewarded): void {
                $refUid = (int)$row['ref_uid'];
                if (!isset($rewarded[$refUid])) {
                    $rewarded[$refUid] = (int)$db->fetchScalar("SELECT COUNT(id) FROM player_references WHERE ref_uid=$refUid AND rewardGiven=1");
                }
                if ($rewarded[$refUid] >= $refLimit) {
                    $db->query("UPDATE player_references SET rewardGiven=2 WHERE id={$row['id']} AND rewardGiven=0");

                    return;
                }
                $db->query("UPDATE player_references SET rewardGiven=1 WHERE id={$row['id']} AND rewardGiven=0");
                if ($db->affectedRows() === 1) {
                    $db->query("UPDATE users SET gift_gold=gift_gold+$inviteGold WHERE id=$refUid");
                    ++$rewarded[$refUid];
                }
            });
        }
    }

    public function deleteOasisComplete(): void
    {
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM odelete WHERE end_time <= " . (time()) . " ORDER BY end_time ASC, id ASC LIMIT 10");
        $m = new AccountDeleter();
        while ($row = $result->fetch_assoc()) {
            $db->query("DELETE FROM odelete WHERE id={$row['id']}");
            OasesModel::releaseOasis($row['oid'], $row['kid']);
            $enforces = $db->query("SELECT * FROM enforcement WHERE to_kid={$row['oid']}");
            while ($enforce = $enforces->fetch_assoc()) {
                $m->returnTrappedOrEnforcementRow($enforce, true);
            }
            $find = $db->query("SELECT * FROM movement WHERE to_kid={$row['oid']} AND mode=0");
            while ($row = $find->fetch_assoc()) {
                $m->cancelMovement($row['id'], $row['to_kid'], $row['kid']);
            }
        }
    }

    public function tradeRoutes(): void
    {
        $usePeriodicTradeRoutes = getGame("usePeriodicTradeRoutes");
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM traderoutes WHERE enabled=1 AND time <= " . (time()) . " LIMIT 100");
        $marketModel = new MarketModel();
        while ($row = $result->fetch_assoc()) {
            $this->guardRow("Trade route {$row['id']}", function () use ($row, $db, $marketModel, $usePeriodicTradeRoutes): void {
                $this->processTradeRoute($row, $db, $marketModel, $usePeriodicTradeRoutes);
            });
        }
    }

    /**
     * One trade-route run in one transaction: the route is claimed under a row lock, the
     * merchants' goods are debited with a guarded UPDATE, and only then are they sent.
     * Any failure rolls the whole run back, so goods are never sent without being paid for.
     *
     * @param array<mixed> $row
     * @param mixed $db
     * @param mixed $usePeriodicTradeRoutes
     */
    private function processTradeRoute(array $row, $db, MarketModel $marketModel, $usePeriodicTradeRoutes): void
    {
        $db->begin_transaction();
        try {
            $this->runTradeRoute($row, $db, $marketModel, $usePeriodicTradeRoutes);
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
        $db->commit();
    }

    /**
     * @param array<mixed> $row
     * @param mixed $db
     * @param mixed $usePeriodicTradeRoutes
     */
    private function runTradeRoute(array $row, $db, MarketModel $marketModel, $usePeriodicTradeRoutes): void
    {
        // Re-read the route under a lock: a concurrent worker may already have run it.
        $locked = $db->fetchScalar('SELECT time FROM traderoutes WHERE id=? AND enabled=1 AND time<=? FOR UPDATE', [(int)$row['id'], time()]);
        if ($locked === false) {
            return;
        }
        $row['time'] = (int)$locked;
        // Next run: one period after the planned time, but never in the past, so a route that was
        // overdue (engine stopped) does not replay every missed day.
        $period = $usePeriodicTradeRoutes ? (int)$row['start_hour'] : 86400;
        $next = $row['time'] + $period;
        if ($next <= time()) {
            $next = time() + max($period, 1);
        }
        $db->run('UPDATE traderoutes SET time=? WHERE id=?', [$next, (int)$row['id']]);
        $uid = $marketModel->getVillageOwner($row['kid']);
        if ($uid === 0) {
            $db->query("DELETE FROM traderoutes WHERE kid={$row['kid']} OR to_kid={$row['kid']}");

            return;
        }
        $race = $marketModel->getPlayerRace($uid);
        $market = $marketModel->getMarketAndTradeOfficeLevel($row['kid']);
        if (!$market[17]) {
            return;
        }
        $cur_resources = array_map("floor", $marketModel->getVillageResources($row['kid']));
        $resources_to_send = [
            1 => $row['r1'],
            2 => $row['r2'],
            3 => $row['r3'],
            4 => $row['r4'],
        ];
        $zeroCount = 0;
        $resources_to_send = array_map("floor", $resources_to_send);
        foreach ($resources_to_send as $k => $v) {
            if ($v > $cur_resources[$k]) {
                $resources_to_send[$k] = $cur_resources[$k];
            }
            if ($v <= 0) {
                $zeroCount++;
            }
        }
        if (!array_sum($resources_to_send)) {
            return;
        }
        $alliance_bonus = 1;
        $alliance = $db->query("SELECT aid, alliance_join_time FROM users WHERE id=$uid")->fetch_assoc();
        if ($alliance['aid'] > 0) {
            $alliance_bonus = AllianceBonus::getTradersBonus($alliance['aid'], $alliance['alliance_join_time']);
        }
        $merchant_cap = Formulas::merchantCAP($race, $market[28], $alliance_bonus);
        $total_resources = array_sum($resources_to_send);
        $total_available_merchants = $market[17] - $marketModel->getOfferingMerchantsCount($row['kid'], $merchant_cap) - $marketModel->getOnTheWayMerchantsCount($row['kid'], $merchant_cap);
        if (!$total_available_merchants) {
            return;
        }
        $total_need_merchants = ceil($total_resources / $merchant_cap);
        if ($total_need_merchants > $total_available_merchants) {
            $resourcesTogo = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $max = $total_available_merchants * $merchant_cap;
            for ($i = 1; $i <= 4; $i++) {
                $resourcesTogo[$i] = max(min($max, $resources_to_send[$i]), 0);
                $max -= $resourcesTogo[$i];
            }
            $resources_to_send = $resourcesTogo;
        }
        // Debit first, guarded against stock that changed since it was read; skip this run when it did.
        if (!Village::debit((int)$row['kid'], [$resources_to_send[1], $resources_to_send[2], $resources_to_send[3], $resources_to_send[4]])) {
            return;
        }
        $marketModel->sendResources($row['kid'], $row['to_kid'], $race, $resources_to_send[1], $resources_to_send[2], $resources_to_send[3], $resources_to_send[4], $row['times'], $row['time']);
    }

    /**
     * Runs a DELETE (without LIMIT) in small batches so the serial gameProgress worker never holds long locks.
     */
    private function deleteBatched(string $deleteSql, int $batchSize = 2000, int $maxBatches = 50): int
    {
        $db = DB::getInstance();
        $total = 0;
        for ($batch = 0; $batch < $maxBatches; ++$batch) {
            $db->query("$deleteSql LIMIT $batchSize");
            $deleted = $db->affectedRows();
            $total += $deleted;
            if ($deleted < $batchSize) {
                break;
            }
            usleep(50000);
        }

        return $total;
    }

    public function cleanupServer(): void
    {
        $halfDay = time() - 86400 / 2;
        $oneHour = time() - 3600;
        $tenMin = time() - 600;
        $db = DB::getInstance();
        $this->deleteBatched("DELETE FROM login_handshake WHERE time < $tenMin");
        $this->deleteBatched("DELETE FROM newproc WHERE time < $halfDay");
        $this->deleteBatched("DELETE FROM adventure WHERE end=1");
        $this->deleteBatched("DELETE FROM auction WHERE finish=1 AND uid=0 AND time < " . (time() - 2 * 86400));
        $this->deleteBatched("DELETE FROM a2b WHERE timestamp < $oneHour");
        $this->deleteBatched("DELETE FROM infobox WHERE showTo<>0 AND showTo < $tenMin AND (type < 7 OR type > 10)");
        $this->deleteBatched("DELETE FROM casualties WHERE time < " . (time() - 5 * 86400));
        $this->deleteBatched("DELETE FROM surrounding WHERE time < " . (time() - 3 * 86400));
        $this->deleteBatched("DELETE FROM log_ip WHERE time < " . (time() - 15 * 86400));
        $this->deleteBatched("DELETE FROM multiaccount_log WHERE time < " . (time() - 30 * 86400));
        $this->deleteBatched("DELETE FROM ali_log WHERE time < " . (time() - 90 * 86400));
        $this->deleteBatched("DELETE FROM ndata WHERE non_deletable=0 AND (uid=1 OR (deleted=1 AND time < ($halfDay)))");
        $this->cleanupReports();
        $this->deleteBatched("DELETE FROM mdata WHERE viewed=1 AND time < " . (time() - 2 * 86400));
        //if(filesize(INCLUDE_PATH . "error_log.log")) {
        //    Notification::notify("Error log", "There are some errors in error_log.");
        //}
    }

    public function cleanupReports(): void
    {
        $cutoff = time() - 7 * 86400;
        $this->deleteBatched(
            "DELETE FROM ndata WHERE non_deletable=0 AND archive=0 AND deleted=0 AND time < $cutoff"
        );
    }

    private function addFakeUsers(): void
    {
        $fakeUsersCount = Config::getProperty("fakeUsersCount");
        if ($fakeUsersCount <= 0) {
            return;
        }
        $users = [];
        for ($tries = $fakeUsersCount * 5; count($users) < $fakeUsersCount && $tries > 0; $tries--) {
            $users[self::FAKE_ADJECTIVES[array_rand(self::FAKE_ADJECTIVES)]
                . self::FAKE_NOUNS[array_rand(self::FAKE_NOUNS)]
                . mt_rand(10, 99)] = true;
        }
        $users = array_keys($users);
        $register = new RegisterModel();
        $count = $register->addFakeUser($users);
        Notification::notify("StartEmail", "$count fake users were added.");
    }

    public function setUpNewServer(): void
    {
        $db = DB::getInstance();
        $installationTime = (int)$db->fetchScalar("SELECT installationTime FROM config");
        if ($installationTime == 0 || (time() - $installationTime) < 120) {
            return;
        }
        $conf = Config::getInstance();
        $startEmailsSent = $db->fetchScalar("SELECT startEmailsSent FROM config");
        if (!$startEmailsSent && time() >= ($conf->game->start_time - 3600)) {
            $db = DB::getInstance();
            $db->query("UPDATE config SET startEmailsSent=1");
            $conf->dynamic->startEmailsSent = 1;
            $totalCount = 0;
            //server emails
            $order = ['[PLAYERNAME]', '[SERVER_NAME]', '[SERVER_URL]', '[SERVER_START_TIME]'];
            $result = $db->query("SELECT * FROM activation");
            while ($row = $result->fetch_assoc()) {
                $replace = [
                    $row['name'],
                    $conf->settings->serverName,
                    $conf->settings->gameWorldUrl,
                    date("Y-m-d H:i", $conf->game->start_time) . ' (GMT ' . getTimeZone() . ')',
                ];
                $emailHTML = str_replace($order, $replace, T("Email", "serverStartEmail"));
                Mailer::sendEmail($row['email'], T("Email", "serverStartEmailSubject"), $emailHTML);
            }
            $totalCount += $result->num_rows;
            (new NatarsModel())->createFarmVillages();
            $this->addFakeUsers();
            Notification::notify("StartEmail", "Start emails were sent to $totalCount emails.");
        }
        $startConfigurationDone = $db->fetchScalar("SELECT startConfigurationDone FROM config");
        if (!$startConfigurationDone && time() > $conf->game->start_time) {
            $db->query("UPDATE config SET startConfigurationDone=1");
            $conf->dynamic->startConfigurationDone = 1;
            $m = new InfoBoxModel();
            $m->addInfo(0, 1, 8, '', 0, 0);
            $m->addInfo(0, 1, 9, '', 0, 0);
            if ($conf->game->catapultAvailableTime > $conf->game->start_time) {
                $m->addInfo(0, 1, 15, '', 0, 0);
            }
            if ($conf->timers->AutoFinishTime > 0) {
                //$m->addInfo(0, 1, 10, '', $conf->timers->AutoFinishTime - 3 * 86400, $conf->timers->AutoFinishTime);
            }
            //(new NatarsModel())->createLotsOfNatarsVillages(max(min(2*$conf->game->speed, 600), 300));
            Notification::notify(
                "System notification",
                sprintf("Game server %s started.", Config::getProperty("settings", "serverName"))
            );
            Log::addLog(0, "Server started", "Game server is now started.");
            \Game\Webhook\WorldEvents::record('worldStart');
        }
    }

    public function refreshCountryFlag(): void
    {
        $db = DB::getInstance();
        $time = time() - 7200;
        $result = $db->query("SELECT id, access, countryFlag FROM users WHERE (access!=3 OR lastCountryFlagCheck=0) AND (lastCountryFlagCheck <= $time) LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            if ($row['access'] == 3) {
                $countryFlag = AutomationModel::getRandomCountryFlag();
            } else {
                $countryFlag = $this->getCountryFlag($row['id']);
                if (getDisplay("suppressIranFlag") && $countryFlag == 'ir') {
                    if (!in_array($row['countryFlag'], ['', ' ', '-'])) {
                        $countryFlag = $row['countryFlag'];
                    } else {
                        $countryFlag = AutomationModel::getRandomCountryFlag();
                    }
                }
            }
            $db->query("UPDATE users SET countryFlag='$countryFlag', lastCountryFlagCheck=" . time() . " WHERE id={$row['id']}");
        }
    }

    /**
     * @param mixed $uid
     */
    private function getCountryFlag($uid): string
    {
        $db = DB::getInstance();
        $ip = $db->query("SELECT ip, COUNT(*) AS `repeatCount` FROM log_ip WHERE uid=$uid GROUP BY ip ORDER BY repeatCount DESC LIMIT 1");
        $countryFlag = '-';
        if ($ip->num_rows) {
            if (function_exists("geoip_country_code_by_name")) {
                try {
                    $countryFlag = strtolower(geoip_country_code_by_name($ip->fetch_assoc()['ip']));
                } catch (Exception $e) {

                }
            }
        }
        return $countryFlag;
    }

    public function checkAutoFinish(): void
    {
        if (Config::getProperty("timers", "WWConstructStartTime") < 10 || Config::getProperty("timers", "AutoFinishTime") <= 10) {
            return;
        }
        $db = DB::getInstance();
        $stmt = $db->query("SELECT serverFinished, WWAlertSent FROM config");
        if (!$stmt->num_rows) {
            return;
        }
        $stmt = $stmt->fetch_assoc();
        if (!$stmt['WWAlertSent'] && time() > Config::getProperty("timers", "WWConstructStartTime")) {
            $db->query("UPDATE config SET WWAlertSent=1");
            $m = new PublicMsgModel();
            $m->haveNewMessage('[NatarsAreBuildingWW]');
        }
        $config = Config::getInstance();
        if (!$stmt['serverFinished']) {
            $wwLevel = floor(max(time() - $config->timers->WWConstructStartTime, 0) / $config->timers->WWUpLvlInterval);
            if ($wwLevel > 0) {
                $db->query("UPDATE fdata SET f99=IF($wwLevel>100, 100, $wwLevel) WHERE kid=" . Formulas::xy2kid(0, 0));
            }
            if ($wwLevel >= 100) {
                (new AutomationModel())->finishTheGame(2);
            } elseif (time() >= Config::getProperty("timers", "AutoFinishTime")) {
                (new AutomationModel())->finishTheGame(2);
            }
        }
    }

    public function resetDailyGold(): void
    {
        $db = DB::getInstance();
        $dailyGold = Config::getProperty("gold", "dailyGold");
        if ($dailyGold <= 0) {
            return;
        }
        $lastDailyGold = $db->fetchScalar("SELECT lastDailyGold FROM config");
        if ($lastDailyGold == 0) {
            $lastDailyGold = Config::getProperty("game", "start_time");
            $db->query("UPDATE config SET lastDailyGold=" . ($lastDailyGold));
        }
        if ($dailyGold && (time() - $lastDailyGold) >= 86400) {
            $count = floor((time() - $lastDailyGold) / 86400);
            for ($i = 1; $i <= $count; ++$i) {
                $db->query("UPDATE config SET lastDailyGold=" . time());
                $m = new MessageModel();
                $db->query("UPDATE users SET gift_gold=gift_gold+$dailyGold WHERE id>2 AND access<>2");
                $users = $db->query("SELECT id FROM users WHERE id > 2 AND access<>2");
                while ($row = $users->fetch_assoc()) {
                    $m->sendMessage(0, $row['id'], null, $dailyGold, 4);
                }
            }
            Log::addLog(0, "DailyGold Reset", "Daily gold has been gifted to all players.");
        }
    }

    public function checkGameFinish(): void
    {
        $db = DB::getInstance();
        $configDB = $db->query("SELECT serverFinished, finishStatusSet FROM config LIMIT 1");
        if (!$configDB->num_rows) {
            return;
        }
        $configDB = $configDB->fetch_assoc();
        if (!$configDB['serverFinished'] || $configDB['finishStatusSet']) {
            return;
        }
        $db->query("UPDATE config SET finishStatusSet=1");
        $resultCode = $configDB['serverFinished'];
        \Game\Webhook\WorldEvents::finished((int) $resultCode);
        $config = Config::getInstance();
        $worldId = Config::getProperty("settings", "worldUniqueId");
        $globalDB = GlobalDB::getInstance();
        $globalDB->query("DELETE FROM preregistration_keys WHERE worldId='{$worldId}'");
        $globalDB->query("UPDATE gameServers SET finished=1, registerClosed=1 WHERE id='{$worldId}'");
        $automationModel = new AutomationModel();
        $gameWorldDetails = $globalDB->query("SELECT * FROM gameServers WHERE id=$worldId")->fetch_assoc();
        if (Config::getAdvancedProperty("voucherEnabled")) {
            $users = $db->query("SELECT id, name, email, bought_gold FROM users WHERE email_verified=1 AND id>2 AND bought_gold>0 AND access=1 AND hidden=0");
            while ($row = $users->fetch_assoc()) {
                if ($row['bought_gold'] <= 0) {
                    continue;
                }
                $automationModel->addVoucher($row['email'], floor($row['bought_gold'] * (100 - Config::getProperty("gold", "voucherTaxPercent")) / 100), "remaining", sprintf('%s-%s', $row['id'], $row['name']));
            }
            $db->query("UPDATE users SET bought_gold=0");
        }
        if (($config->bonus->bonusGoldWinner || $config->bonus->bonusGoldTopAlliance) && $resultCode == 1) {
            //has winner
            $kid = $db->fetchScalar("SELECT kid FROM fdata WHERE f99=100");
            $wData = $db->query("SELECT name, owner FROM vdata WHERE kid=$kid")->fetch_assoc();
            $uData = $db->query("SELECT id, name, access, email, aid, email_verified FROM users WHERE id={$wData['owner']}")->fetch_assoc();
            if ($config->bonus->bonusGoldWinner && $uData['access'] == 1 && $uData['email_verified'] == 1) {
                $automationModel->addVoucher($uData['email'], $config->bonus->bonusGoldWinner, 'winner', sprintf('[%s] %s', $uData['id'], $uData['name']));
            }
            if ($uData['aid'] && $config->bonus->bonusGoldTopAlliance) {
                $count = $config->bonus->bonusGoldTopAllianceCount;
                $minPop = $config->bonus->bonusGoldTopAllianceMinPop;
                $result = $db->query("SELECT id, name, email, email_verified FROM users WHERE aid={$uData['aid']} AND id!={$uData['id']} AND total_pop >= $minPop AND access=1 ORDER BY total_pop DESC LIMIT $count");
                while ($row = $result->fetch_assoc()) {
                    if ($row['email_verified'] == 1) {
                        $automationModel->addVoucher(
                            $row['email'],
                            $config->bonus->bonusGoldTopAlliance,
                            'winnerAlliance',
                            sprintf('%s-%s', $row['id'], $row['name'])
                        );
                    }
                }
            }
        }
        if ($config->bonus->bonusGoldSecondWinner || $config->bonus->bonusGoldThirdWinner) {
            $stmt = $db->query("SELECT kid FROM fdata WHERE f99 > 50 && f99 < 100 ORDER BY f99 DESC, lastWWUpgrade ASC");
            $rank = 2;
            while ($a = $stmt->fetch_assoc()) {
                $wData = $db->query("SELECT name, owner FROM vdata WHERE kid={$a['kid']}");
                if ($wData->num_rows) {
                    $wData = $wData->fetch_assoc();
                    $uData = $db->query("SELECT id, name, email, aid, email_verified FROM users WHERE id={$wData['owner']} AND access=1");
                    if ($uData->num_rows) {
                        $uData = $uData->fetch_assoc();
                        if ($uData['email_verified'] == 1) {
                            if ($rank == 2 && $config->bonus->bonusGoldSecondWinner) {
                                $automationModel->addVoucher(
                                    $uData['email'],
                                    $config->bonus->bonusGoldSecondWinner,
                                    '2ndWinner',
                                    sprintf('[%s] %s', $uData['id'], $uData['name'])
                                );
                            } elseif ($rank == 3 && $config->bonus->bonusGoldThirdWinner) {
                                $automationModel->addVoucher(
                                    $uData['email'],
                                    $config->bonus->bonusGoldThirdWinner,
                                    '3rdWinner',
                                    sprintf('[%s] %s', $uData['id'], $uData['name'])
                                );
                            }
                        }
                    }
                }
                $rank++;
            }
        }
        if ($config->bonus->bonusGoldTopOff) {
            $topAttacker = $db->query("SELECT id, name, email, email_verified FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_attack_points DESC LIMIT 1");
            if ($topAttacker->num_rows) {
                $topAttacker = $topAttacker->fetch_assoc();
                if ($topAttacker['email_verified'] == 1) {
                    $automationModel->addVoucher(
                        $topAttacker['email'],
                        $config->bonus->bonusGoldTopOff,
                        'topOff',
                        sprintf('[%s] %s', $topAttacker['id'], $topAttacker['name'])
                    );
                }
            }
        }
        if ($config->bonus->bonusGoldTopDef) {
            $topDefender = $db->query("SELECT id, name, email, email_verified FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_defense_points DESC LIMIT 1");
            if ($topDefender->num_rows) {
                $topDefender = $topDefender->fetch_assoc();
                if ($topDefender['email_verified'] == 1) {
                    $automationModel->addVoucher(
                        $topDefender['email'],
                        $config->bonus->bonusGoldTopDef,
                        'topDef',
                        sprintf('[%s] %s', $topDefender['id'], $topDefender['name'])
                    );
                }
            }
        }
        if ($config->bonus->bonusGoldTopClimber) {
            $topClimber = $db->query("SELECT id, name, email, email_verified FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_pop DESC LIMIT 1");
            if ($topClimber->num_rows) {
                $topClimber = $topClimber->fetch_assoc();
                if ($topClimber['email_verified'] == 1) {
                    $automationModel->addVoucher(
                        $topClimber['email'],
                        $config->bonus->bonusGoldTopClimber,
                        'topClimber',
                        sprintf('[%s] %s', $topClimber['id'], $topClimber['name'])
                    );
                }
            }
        }
        if ($config->bonus->bonusGoldTopOffHammer) {
            $topClimber = $db->query("SELECT id, name, email, email_verified FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY max_off_point DESC LIMIT 1");
            if ($topClimber->num_rows) {
                $topClimber = $topClimber->fetch_assoc();
                if ($topClimber['email_verified'] == 1) {
                    $automationModel->addVoucher(
                        $topClimber['email'],
                        $config->bonus->bonusGoldTopOffHammer,
                        'topOffHammer',
                        sprintf('[%s] %s', $topClimber['id'], $topClimber['name'])
                    );
                }
            }
        }
        if ($config->bonus->bonusGoldTopDefHammer) {
            $topClimber = $db->query("SELECT id, name, email, email_verified FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY max_def_point DESC LIMIT 1");
            if ($topClimber->num_rows) {
                $topClimber = $topClimber->fetch_assoc();
                if ($topClimber['email_verified'] == 1) {
                    $automationModel->addVoucher(
                        $topClimber['email'],
                        $config->bonus->bonusGoldTopDefHammer,
                        'topDefHammer',
                        sprintf('[%s] %s', $topClimber['id'], $topClimber['name'])
                    );
                }

            }
        }
        $hidden = !$gameWorldDetails['promoted'];
        $x = 1;
        if ($resultCode == 1) {
            $result = $db->query("SELECT kid, f99 FROM fdata WHERE f99>0 ORDER BY f99 DESC, lastWWUpgrade ASC LIMIT 3");
            while ($row = $result->fetch_assoc()) {
                $owner = $db->fetchScalar("SELECT owner FROM vdata WHERE kid={$row['kid']}");
                $info = $db->query("SELECT id, aid, name, email, email_verified, race, countryFlag FROM users WHERE id={$owner}")->fetch_assoc();
                if ($info['aid']) {
                    $alliance = $db->query("SELECT name, tag FROM alidata WHERE id={$info['aid']}");
                    if ($alliance->num_rows) {
                        $alliance = $alliance->fetch_assoc();
                        $alliance['name'] = $db->real_escape_string($alliance['name']);
                        $alliance['tag'] = $db->real_escape_string($alliance['tag']);
                    } else {
                        $alliance['tag'] = $alliance['name'] = '';
                    }
                } else {
                    $alliance['tag'] = $alliance['name'] = '';
                }
                if ($info['email_verified'] == 1) {
                    ClubApi::addMedal(
                        $info['name'],
                        $info['race'],
                        $info['email'],
                        [$info['countryFlag'], $row['f99'], $alliance['name'], $alliance['tag']],
                        $x,
                        $hidden
                    );
                }
                ++$x;
            }
        }
        $x = 4 - 1;
        $result = $db->query("SELECT id, name, email, email_verified, race, total_attack_points, countryFlag FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_attack_points DESC LIMIT 3");
        while ($row = $result->fetch_assoc()) {
            if ($row['email_verified'] == 1) {
                ClubApi::addMedal(
                    $row['name'],
                    $row['race'],
                    $row['email'],
                    [$row['countryFlag'], $row['total_attack_points']],
                    ++$x,
                    $hidden
                );
            }
        }
        $x = 8 - 1;
        $result = $db->query("SELECT id, name, email, email_verified, race, total_defense_points, countryFlag FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_defense_points DESC LIMIT 3");
        while ($row = $result->fetch_assoc()) {
            if ($row['email_verified'] == 1) {

                ClubApi::addMedal(
                    $row['name'],
                    $row['race'],
                    $row['email'],
                    [$row['countryFlag'], $row['total_defense_points']],
                    ++$x,
                    $hidden
                );
            }
        }
        $x = 12 - 1;
        $result = $db->query("SELECT id, name, email, email_verified, race, total_pop, countryFlag FROM users WHERE id>2 AND hidden=0 AND access=1 ORDER BY total_pop DESC LIMIT 3");
        while ($row = $result->fetch_assoc()) {
            if ($row['email_verified'] == 1) {
                ClubApi::addMedal(
                    $row['name'],
                    $row['race'],
                    $row['email'],
                    [$row['countryFlag'], $row['total_pop']],
                    ++$x,
                    $hidden
                );
            }
        }
        //big off hammer 16
        //big def hammer 17
        //winner alliance 18
        $result = $db->query("SELECT id, name, email, email_verified, race, max_off_point, countryFlag FROM users WHERE id>2 AND hidden=0 AND access=1 AND max_off_point > 0 ORDER BY max_off_point DESC LIMIT 1");
        while ($row = $result->fetch_assoc()) {
            if ($row['email_verified'] == 1) {
                ClubApi::addMedal(
                    $row['name'],
                    $row['race'],
                    $row['email'],
                    [$row['countryFlag'], $row['max_off_point']],
                    16,
                    $hidden
                );
            }
        }
        $result = $db->query("SELECT id, name, email, email_verified, race, max_def_point, countryFlag FROM users WHERE id>2 AND hidden=0 AND access=1 AND max_def_point > 0 ORDER BY max_def_point DESC LIMIT 1");
        while ($row = $result->fetch_assoc()) {
            if ($row['email_verified'] == 1) {
                ClubApi::addMedal(
                    $row['name'],
                    $row['race'],
                    $row['email'],
                    [$row['countryFlag'], $row['max_def_point']],
                    17,
                    $hidden
                );
            }
        }
        if ($resultCode == 1) {
            $kid = $db->fetchScalar("SELECT kid FROM fdata WHERE f99=100");
            $wData = $db->query("SELECT name, owner FROM vdata WHERE kid=$kid")->fetch_assoc();
            $uData = $db->query("SELECT id, name, access, email, email_verified, aid FROM users WHERE id={$wData['owner']}");
            if ($uData->num_rows) {
                $uData = $uData->fetch_assoc();
                if ($uData['aid']) {
                    $alliance = $db->query("SELECT name, tag FROM alidata WHERE id={$uData['aid']}");
                    if ($alliance->num_rows) {
                        $alliance = $alliance->fetch_assoc();
                        $alliance['name'] = $db->real_escape_string($alliance['name']);
                        $alliance['tag'] = $db->real_escape_string($alliance['tag']);
                        $result = $db->query("SELECT id, name, email, email_verified, race, total_pop, countryFlag FROM users WHERE aid={$uData['aid']} AND id!={$uData['id']}");
                        while ($row = $result->fetch_assoc()) {
                            if ($row['email_verified'] == 1) {
                                ClubApi::addMedal(
                                    $row['name'],
                                    $row['race'],
                                    $row['email'],
                                    [$row['countryFlag'], $alliance['name'], $alliance['tag']],
                                    18,
                                    $hidden
                                );
                            }
                        }
                    }
                }
            }
        }
        $db->query("UPDATE config SET serverFinishTime=" . time());
    }

    public function cleanupIndex(): void
    {
        $config = Config::getInstance();
        $timeline = WorldSpeedProfile::officialRoundTimeline((int)$config->game->speed);
        if ($timeline !== null) {
            $registrationCloseTime = $config->game->start_time + $timeline['registrationCloseAfter'];
        } else {
            $registrationCloseTime = $config->game->start_time + (round($config->game->round_length * 0.75, 1) * 86400);
            $registrationCloseTime = min($registrationCloseTime, $config->timers->wwPlansReleaseTime);
        }
        if (getWorldId() == 'dev') {
            $registrationCloseTime = 0;
        }
        $globalDB = GlobalDB::getInstance();
        // Checkouts nobody completed; paid, refunded and disputed orders are the payment record.
        $globalDB->query("DELETE FROM paymentOrder WHERE status='pending' AND created < " . (time() - 2.5 * 86400));
        $globalDB->query("DELETE FROM infobox WHERE showTo < " . time());
        $globalDB->query("DELETE FROM banIP WHERE blockTill > 0 AND blockTill < " . time());
        $time = time() - Config::getProperty("gold", "voucherExpireDays") * 86400;
        $globalDB->query("UPDATE paymentVoucher SET used=1, usedWorldId='system', usedPlayer='system', usedEmail='system', usedTime=" . time() . " WHERE used=0 AND time < $time");
        if ($registrationCloseTime > 0 && time() > $registrationCloseTime && Config::getAdvancedProperty("registerAutoClose")) {
            $globalDB->query("UPDATE gameServers SET registerClosed=1 WHERE id=" . getWorldUniqueId());
        }
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM notificationQueue LIMIT 100");
        while ($row = $result->fetch_assoc()) {
            $db->query("DELETE FROM notificationQueue WHERE id={$row['id']}");
            Notification::notifyReal($row['message']);
        }
        if (time() > $config->game->start_time) {
            $startTime = $config->game->start_time;
            $interval = getCustom("activationProgressReminderInterval");
            if ($interval > 0) {
                $result = $db->query("SELECT * FROM activation WHERE time>0 AND reminded=0 AND (" . time() . "-IF(time <= $startTime, $startTime, time) >= $interval) LIMIT 20");
                $view = new PHPBatchView("mail/activationProgressReminder");
                while ($row = $result->fetch_assoc()) {
                    $db->query("UPDATE activation SET reminded=1 WHERE id={$row['id']}");
                    $view->vars['name'] = $row['name'];
                    $view->vars['token'] = $row['token'];
                    Mailer::sendEmail($row['email'], T("Mail", "Activation progress reminder"), $view->output());
                }
            }
        }
    }

    public function backup(): void
    {
        if (getGameElapsedSeconds() <= 1800 || !DB::scheduledBackupsEnabled()) {
            return;
        }
        $db = DB::getInstance();
        $lastBackup = $db->fetchScalar("SELECT lastBackup FROM config");
        if ((time() - $lastBackup) > 6 * 3600) {
            $db->backup_tables();
        }
    }

    public function pruneAuditLog(): void
    {
        $removed = \Game\Admin\AuditLog::prune(\Game\OperationsSettings::get('auditRetentionDays'));
        if ($removed > 0) {
            logError("Audit log retention removed $removed old entries.");
        }
    }
}
