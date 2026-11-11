<?php

declare(strict_types=1);

namespace Game\Round;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Jobs\EngineHeartbeat;
use Game\Installer\WorldInstaller;
use Model\InfoBoxModel;
use Model\RegisterModel;

/**
 * Ends a finished round and starts the next one in the same world, without a reinstall: the finished round
 * is archived (RoundArchive), the world is backed up, every round table is emptied, the map is generated
 * again by the installer's own seeding, and the accounts are kept (identity and settings only, each with a
 * fresh start village) or removed. This class owns the one list of tables a round reset clears.
 */
final class RoundReset
{
    public const MODE_KEEP = 'keep';
    public const MODE_REMOVE = 'remove';

    private const LOCK = 'round_reset';
    private const LOCK_TTL = 3600;
    private const PAUSE_SECONDS = 45;

    /**
     * Tables a round reset empties: everything that belongs to the round (villages, troops, reports,
     * messages, alliances, forums, auctions, hero, quests, statistics, medals, the map).
     */
    public const WIPED_TABLES = [
        'a2b',
        'daily_quest',
        'task_claims',
        'log_ip',
        'casualties',
        'statistics_daily',
        'activation',
        'general_log',
        'adventure',
        'ali_invite',
        'ali_log',
        'alidata',
        'alistats',
        'allimedal',
        'artefacts',
        'artlog',
        'auction',
        'bids',
        'accounting',
        'blocks',
        'multiaccount_log',
        'map_block',
        'map_mark',
        'building_upgrade',
        'deleting',
        'demolition',
        'diplomacy',
        'enforcement',
        'face',
        'hero_appearance_unlocks',
        'farmlist',
        'farmlist_deactivated_targets',
        'fdata',
        'forum_forums',
        'forum_edit',
        'forum_options',
        'forum_post',
        'forum_vote',
        'forum_open_players',
        'forum_open_alliances',
        'forum_topic',
        'hero',
        'hero_resources',
        'village_group_villages',
        'village_groups',
        'items',
        'inventory',
        'links',
        'mapflag',
        'market',
        'traderoutes',
        'marks',
        'mdata',
        'autoExtend',
        'buyGoldMessages',
        'medal',
        'movement',
        'alliance_notification',
        'messages_report',
        'player_accusation',
        'ndata',
        'surrounding',
        'newproc',
        'odata',
        'odelete',
        'raidlist',
        'research',
        'send',
        'smithy',
        'tdata',
        'training',
        'training_repeat',
        'wounded',
        'trapped',
        'units',
        'ignoreList',
        'friendlist',
        'changeEmail',
        'player_references',
        'vdata',
        'infobox_read',
        'infobox_delete',
        'infobox',
        'wdata',
        'available_villages',
        'multiaccount_users',
        'notificationQueue',
        'activation_progress',
        'login_handshake',
        'remember_tokens',
        'email_reminder_sent',
        'notes',
        'alliance_bonus_upgrade_queue',
        'farmlist_last_reports',
        'scheduled_task_failures',
        'season_pass_progress',
        'season_pass_missions',
        'season_pass_claims',
        'season_pass_shards',
        'season_pass_governor_progress'
    ];

    /**
     * Tables a reset leaves alone: the world configuration (reset by value), the archive, the audit
     * trail, operator-edited legal pages, season definitions, payment bookkeeping that keeps a replayed
     * payment from crediting twice, and the queue of webhook events not yet delivered.
     * users and summary are rebuilt by the reset itself.
     */
    public const KEPT_TABLES = [
        'config', 'rounds', 'round_standings', 'admin_log', 'admin_accounts', 'legal_pages', 'season_pass', 'season_pass_rewards',
        'paymentCredits', 'webhook_queue', 'users', 'summary',
    ];

    /**
     * Tables that belong to an account rather than to the round. Rows survive for accounts that are kept
     * and are deleted for every other player.
     */
    public const ACCOUNT_TABLES = [
        'account_oauth_identity' => 'uid',
        'email_preference' => 'uid',
        'banHistory' => 'uid',
        'banQueue' => 'uid',
    ];

    /**
     * What a kept account keeps besides its id, name, password, email and tribe: its settings.
     * Everything else (gold, hero, medals, villages, statistics, profile text, Plus and boosts) starts over.
     */
    public const KEPT_SETTINGS = [
        'uuid', 'email_verified', 'newsletter', 'access', 'hidden', 'countryFlag', 'additionalLanguages',
        'showMedals', 'showCountryFlag', 'favorTabs', 'reportFilters', 'allianceNotificationEnabled',
        'autoComplete', 'display', 'timezone', 'mapMarkSettings', 'gender', 'birthday', 'location', 'language',
        'sit1Uid', 'sit1Permissions', 'sit2Uid', 'sit2Permissions',
    ];

    /** Cached values that describe the world of the finished round. */
    public const CACHE_PATTERNS = [
        'Statistics:*', 'TroopsSummary*', 'preferences:*', 'ActiveVillageBox:*', 'hero_sidebar_*', 'dorf*',
        'newReports*', 'newMessages*', 'reportFilters:*', 'ignoreList*', 'attacks*', 'heroImageHash*', 'getNearDistanceOasisIds*',
    ];

    /** The config columns that mark a round as started over. */
    private const CONFIG_RESET = [
        'serverFinished', 'serverFinishTime', 'finishStatusSet', 'WWAlertSent', 'ArtifactsReleased', 'WWPlansReleased',
        'startEmailsSent', 'startConfigurationDone', 'lastSystemCleanup', 'lastFakeAuction', 'lastNatarsExpand',
        'lastDailyGold', 'lastDailyQuestReset', 'lastMedalsGiven', 'lastAllianceContributeReset',
        'truceFrom', 'truceTo', 'truceReasonId', 'delayTime',
    ];

    private function __construct()
    {
    }

    /** True once the round has ended and its end-of-round bonuses were settled by checkGameFinish(). */
    public static function finished(): bool
    {
        $row = DB::getInstance()->query('SELECT serverFinished, finishStatusSet FROM config LIMIT 1');
        $config = $row && $row->num_rows ? $row->fetch_assoc() : null;

        return $config !== null && (int) $config['serverFinished'] > 0 && (int) $config['finishStatusSet'] === 1;
    }

    /**
     * Archives the finished round and starts the next one. Throws RoundResetRefused while nothing has been
     * changed, and RoundResetFailed once the world was touched (it is then held in recovery mode).
     *
     * @return int the id of the archived round
     */
    public static function run(string $mode, string $adminName): int
    {
        if (!in_array($mode, [self::MODE_KEEP, self::MODE_REMOVE], true)) {
            throw new RoundResetRefused('Choose whether to keep or remove the accounts.');
        }
        if (!self::finished()) {
            throw new RoundResetRefused('The round has not finished yet. A round can be reset only after it has ended.');
        }
        $cache = Caching::getInstance();
        $lock = $cache->lock(self::LOCK, self::LOCK_TTL);
        if ($lock === false) {
            throw new RoundResetRefused('Another round reset is already running.');
        }
        $releasePause = false;
        try {
            EngineHeartbeat::requestPause();
            $releasePause = true;
            if (!EngineHeartbeat::awaitPaused(self::PAUSE_SECONDS)) {
                throw new RoundResetRefused('The engine did not acknowledge the maintenance pause.');
            }
            // The restore point of this reset: exempt from retention so it is still there when someone looks.
            if (!DB::getInstance()->backup_tables(null, [], true)) {
                throw new RoundResetRefused('The pre-reset backup failed, so nothing was changed. Check the error log.');
            }

            try {
                return self::execute($mode, $adminName);
            } catch (RoundResetFailed $failure) {
                // The engine stays paused with the world in recovery mode, as after a failed restore.
                $releasePause = false;
                throw $failure;
            }
        } finally {
            if ($releasePause) {
                EngineHeartbeat::clearPause();
            }
            $cache->unlock(self::LOCK, $lock);
        }
    }

    private static function execute(string $mode, string $adminName): int
    {
        $db = DB::getInstance();
        $kept = $mode === self::MODE_KEEP ? self::keptAccounts() : [];
        $admins = self::adminRows();
        $multihunterHash = (string) $db->fetchScalar('SELECT password FROM users WHERE id=2');
        try {
            $roundId = RoundArchive::write($mode, $adminName);
        } catch (\Throwable $exception) {
            logError('Round archive failed: ' . $exception->getMessage());
            throw new RoundResetRefused('The round archive could not be written, so nothing was changed. Check the error log.');
        }
        // From here the world is half-built until the end: players are held out like during a restore, and a
        // failure leaves it there for the operator to restore the backup taken above.
        try {
            $db->query('UPDATE config SET needsRestart=1, isRestore=1');
            self::clearRound();
            self::startRound();
            WorldInstaller::seedMap(bin2hex(random_bytes(16)));
            $db->run('UPDATE users SET password=? WHERE id=2', [$multihunterHash]);
            self::placeAccounts($kept);
            self::restoreAdmins($admins);
            self::dropOrphanedAccountRows();
            self::flushCaches();
            $db->query('UPDATE config SET isRestore=0');
        } catch (\Throwable $exception) {
            logError('Round reset failed: ' . $exception->getMessage());
            throw new RoundResetFailed('The round reset failed. The world remains paused for recovery; check the error log.', 0, $exception);
        }

        return $roundId;
    }

    /**
     * @return list<array<string, mixed>> the accounts that start the next round: every player but the bots
     */
    private static function keptAccounts(): array
    {
        $statement = DB::getInstance()->run(
            'SELECT * FROM users WHERE id >= ? AND access <> 3 AND id NOT IN (SELECT uid FROM admin_accounts) ORDER BY id',
            [RoundArchive::FIRST_PLAYER_ID]
        );
        $accounts = [];
        $result = $statement === false ? false : $statement->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $accounts[] = $row;
        }

        return $accounts;
    }

    /**
     * The control panel administrators' user rows (admin_accounts itself survives the reset). They are neither
     * players nor bots: they come back as they were, without a village, in both modes.
     *
     * @return list<array<string, mixed>>
     */
    private static function adminRows(): array
    {
        $statement = DB::getInstance()->run('SELECT u.* FROM users u JOIN admin_accounts a ON a.uid=u.id ORDER BY u.id');
        $result = $statement === false ? false : $statement->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function restoreAdmins(array $rows): void
    {
        $register = new RegisterModel();
        $db = DB::getInstance();
        foreach ($rows as $row) {
            $uid = (int) $row['id'];
            $created = $register->addUser('r' . $uid, (string) $row['password'], (string) $row['email'], 0, 0, 1, false, true, false, $uid);
            if ($created === false) {
                throw new \RuntimeException('Could not restore the administrator account ' . $uid . '.');
            }
            $db->run('UPDATE users SET name=?, hidden=1, email_verified=1 WHERE id=?', [(string) $row['name'], $uid]);
        }
    }

    private static function clearRound(): void
    {
        $db = DB::getInstance();
        foreach ([...self::WIPED_TABLES, 'users', 'summary'] as $table) {
            if (!$db->query('TRUNCATE TABLE `' . $table . '`')) {
                throw new \RuntimeException('Could not empty ' . $table . '.');
            }
        }
        $db->query('INSERT INTO summary (id) VALUES (1)');
    }

    /** The new round starts now: the world configuration, the account database entry and this request's view of it. */
    private static function startRound(): void
    {
        $db = DB::getInstance();
        $now = time();
        $columns = implode(', ', array_map(static fn (string $column): string => $column . '=0', self::CONFIG_RESET));
        $db->run('UPDATE config SET startTime=?, installationTime=?, ' . $columns, [$now, $now]);
        $config = Config::getInstance();
        $config->game->start_time = $now;
        foreach (self::CONFIG_RESET as $column) {
            $config->dynamic->$column = 0;
        }
        $config->dynamic->startTime = $now;
        GlobalDB::getInstance()->run(
            'UPDATE gameServers SET finished=0, registerClosed=0, startTime=? WHERE id=?',
            [$now, (int) $config->settings->worldUniqueId]
        );
    }

    /**
     * Gives every kept account back its id and its settings with a start village of its tribe at a random
     * free place of the new map.
     *
     * @param list<array<string, mixed>> $accounts
     */
    private static function placeAccounts(array $accounts): void
    {
        $register = new RegisterModel();
        $ids = array_flip(array_map(static fn (array $account): int => (int) $account['id'], $accounts));
        $db = DB::getInstance();
        foreach ($accounts as $account) {
            $uid = (int) $account['id'];
            $race = (int) $account['race'];
            $kid = $register->generateBase('any');
            if (!$kid) {
                throw new \RuntimeException('The new map has no free start village for player ' . $uid . '.');
            }
            // A placeholder name cannot collide with the system accounts; the real one is set below.
            $created = $register->addUser('r' . $uid, (string) $account['password'], (string) $account['email'], $race, $kid, (int) $account['access'], true, false, (bool) $account['newsletter'], $uid);
            if ($created === false || !$register->createBaseVillage($uid, (string) $account['name'], $race, $kid)) {
                throw new \RuntimeException('Could not create the start village of player ' . $uid . '.');
            }
            $settings = ['name' => $account['name']];
            foreach (self::KEPT_SETTINGS as $column) {
                $settings[$column] = $account[$column];
            }
            foreach (['sit1Uid', 'sit2Uid'] as $sitter) {
                if (!isset($ids[(int) $settings[$sitter]])) {
                    $settings[$sitter] = 0;
                }
            }
            $assignments = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`=?', array_keys($settings)));
            $db->run('UPDATE users SET ' . $assignments . ' WHERE id=?', [...array_values($settings), $uid]);
        }
    }

    /** Account rows of players that are not part of the new round (all of them when the accounts are removed). */
    private static function dropOrphanedAccountRows(): void
    {
        $db = DB::getInstance();
        foreach (self::ACCOUNT_TABLES as $table => $column) {
            $db->query('DELETE t FROM `' . $table . '` t LEFT JOIN users u ON u.id=t.`' . $column . '` WHERE u.id IS NULL');
        }
    }

    private static function flushCaches(): void
    {
        InfoBoxModel::invalidatePublicInfoBox();
        InfoBoxModel::invalidateAllUsersPrivateInfoBox();
        try {
            $cache = Caching::getInstance();
            foreach (self::CACHE_PATTERNS as $pattern) {
                $cache->deleteByPattern($pattern);
            }
        } catch (\Throwable $exception) {
            // Stale entries expire on their own when the cache backend is unavailable.
        }
    }
}
