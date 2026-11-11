<?php

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Database\RestoreAftercare;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Core\Jobs\EngineHeartbeat;

class BackupsCtrl
{
    private const LOCK_TTL = 21600;

    public function __construct()
    {
        $params['content'] = false;
        $cache = Caching::getInstance();
        ignore_user_abort(true);
        if (!isServerFinished() && WebService::isPost() && $this->isMutationRequest() && Session::validateChecker()) {
            if (isset($_POST['do_backup'])) {
                $lock = $cache->lock("backup_database", self::LOCK_TTL);
                if ($lock !== false) {
                    try {
                        $backedUp = DB::getInstance()->backup_tables(function () use ($cache, $lock) {
                            return $cache->renewLock("backup_database", $lock, self::LOCK_TTL);
                        });
                    } catch (\Throwable $exception) {
                        logError('Backup failed: ' . $exception->getMessage());
                        $backedUp = false;
                    } finally {
                        $cache->unlock("backup_database", $lock);
                    }
                    $this->showError($backedUp ? 1 : 9);
                } else {
                    $this->showError(2);
                }
            } else if (isset($_POST['force_unlock_backup'])) {
                $cache->forceUnlock("backup_database");
                $this->showError(3);
            } else if (isset($_POST['force_unlock_restore'])) {
                $cache->forceUnlock("restore_database");
                $this->showError(6);
            } else if (isset($_POST['restore_backup'])) {
                $lock = $cache->lock("restore_database", self::LOCK_TTL);
                if ($lock === false) {
                    $this->showError(5);

                    return;
                }

                $restoreModeSet = false;
                $accountRestored = false;
                $success = false;
                $error = 10;
                try {
                    $file = $this->backupFile($_POST['restore_backup']);
                    if ($file === null) {
                        $error = 8;
                        throw new \RuntimeException('Backup not found.');
                    }
                    $timestamp = $this->backupTimestamp(basename($file));
                    if ($timestamp === null) {
                        $error = 8;
                        throw new \RuntimeException('Backup has an invalid filename.');
                    }
                    $db = DB::getInstance();
                    // A backup is a world dump and an account dump that belong together, so both are restored.
                    $accountFile = preg_replace('/\.sql(\.gz)?\z/', '-account.sql.gz', $file);
                    $accountFile = is_file($accountFile) ? $accountFile : null;
                    // The safety backup tools/backup.php takes first; the chosen backup is protected from its retention pass.
                    $error = 11;
                    $stamp = substr((string)preg_replace('/\.sql(\.gz)?\z/', '', basename($file)), strlen('backup-'));
                    if (!$db->backup_tables(null, [$stamp])) {
                        throw new \RuntimeException('The safety backup failed; nothing was restored.');
                    }
                    $error = 10;
                    // Pause first and wait for the acknowledgement: restore mode makes workers exit on their own, which
                    // the engine would otherwise treat as a crash instead of a maintenance pause.
                    EngineHeartbeat::requestPause();
                    if (!EngineHeartbeat::awaitPaused(45)) {
                        EngineHeartbeat::clearPause();
                        throw new \RuntimeException('The engine did not acknowledge the maintenance pause.');
                    }
                    if (!$db->query("UPDATE config SET needsRestart=1, isRestore=1")) {
                        EngineHeartbeat::clearPause();
                        throw new \RuntimeException('Unable to enter restore mode.');
                    }
                    $restoreModeSet = true;
                    if (!$cache->renewLock("restore_database", $lock, self::LOCK_TTL)) {
                        throw new \RuntimeException('Restore lock was lost.');
                    }
                    $compressed = file_get_contents($file);
                    if ($compressed === false || ($sql = gzdecode($compressed)) === false) {
                        throw new \RuntimeException('Unable to decompress backup.');
                    }
                    if (!$db->multi_query($sql)) {
                        throw new \RuntimeException('Backup import failed.');
                    }
                    if (!$cache->renewLock("restore_database", $lock, self::LOCK_TTL)) {
                        throw new \RuntimeException('Restore lock was lost.');
                    }
                    if ($accountFile !== null) {
                        if (!GlobalDB::getInstance()->restoreFrom($accountFile)) {
                            throw new \RuntimeException('Account database import failed.');
                        }
                        $accountRestored = true;
                    }
                    RestoreAftercare::apply($timestamp);
                    $success = true;
                } catch (\Throwable $exception) {
                    logError('Restore failed: ' . $exception->getMessage());
                } finally {
                    try {
                        if ($restoreModeSet && $success) {
                            if (!$db->query("UPDATE config SET isRestore=0")) {
                                throw new \RuntimeException('Unable to clear restore mode.');
                            }
                            // Releasing the pause makes the engine exit; Compose restarts it on the restored data.
                            EngineHeartbeat::clearPause();
                        }
                    } catch (\Throwable $exception) {
                        logError('Restore failed: ' . $exception->getMessage());
                        $success = false;
                        $error = 10;
                    }
                    if ($restoreModeSet && !$success && !DB::getInstance()->query("UPDATE config SET needsRestart=1, isRestore=1")) {
                        logError('Restore failed: unable to leave the world in recovery mode.');
                    }
                    try {
                        $cache->unlock("restore_database", $lock);
                    } catch (\Throwable $exception) {
                        logError('Restore lock release failed: ' . $exception->getMessage());
                    }
                }
                if (!$success && $restoreModeSet) {
                    // The next request is intentionally rejected by bootstrap while isRestore=1, so surface the
                    // recovery warning in this response rather than redirecting to an unreachable status page.
                    $params['error'] = 'Restore failed. The world remains stopped for recovery; check the error log.';
                } else {
                    $this->showError($success ? ($accountRestored ? 4 : 12) : $error);
                }
            } else if (isset($_POST['del_backup'])) {
                $file = $this->backupFile($_POST['del_backup']);
                if ($file !== null && is_file($file) && unlink($file)) {
                    @unlink(preg_replace('/\.sql(\.gz)?$/', '-account.sql.gz', $file));
                    $this->showError(7);
                } else {
                    $this->showError(8);
                }
            }
        }
        $backups = array_merge(glob(BACKUP_PATH . "/*.sql.gz") ?: [], glob(BACKUP_PATH . "/*.sql") ?: []);
        $i = 0;
        $total_size = 0;
        foreach ($backups as $filename) {
            $real_file = $filename;
            $filename = preg_replace('/\.sql(\.gz)?$/', '', basename($filename));
            $timestamp = $this->backupTimestamp($filename);
            if ($timestamp === null) {
                continue;
            }
            $checker = Session::getCheckerInput();
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="center"><form method="post" action="?action=backups">' . $checker . \Game\AdminUi::confirmButton('Delete', 'Delete this backup? Its world and account files are removed for good.', 'grey', ['name' => 'del_backup', 'value' => $filename]) . '</form></td>';
            $params['content'] .= '<td class="center">' . ++$i . '</td>';
            $params['content'] .= '<td class="center">' . TimezoneHelper::autoDateString($timestamp, true) . '</td>';
            // Each backup is the world dump plus its -account sibling; show both so neither is invisible.
            $accountFile = preg_replace('/\.sql(\.gz)?$/', '-account.sql.gz', $real_file);
            $sizeText = human_filesize(filesize($real_file)) . ' world';
            if (is_file($accountFile)) {
                $sizeText .= ' + ' . human_filesize(filesize($accountFile)) . ' account';
                $total_size += filesize($accountFile);
            }
            $params['content'] .= '<td class="center">' . $sizeText . '</td>';
            $params['content'] .= '<td class="center"><form method="post" action="?action=backups">' . $checker . \Game\AdminUi::confirmButton('Restore backup', 'Restore this backup? The world and the accounts are replaced by it. A safety backup is taken first and the engine is paused until it is done.', 'green', ['name' => 'restore_backup', 'value' => $filename]) . '</form></td>';
            $params['content'] .= '</tr>';
            $total_size += filesize($real_file);
        }
        if ($i === 0) {
            $params['content'] .= '<tr>';
            $params['content'] .= '<td colspan="5" class="noData center">No backups up to now.</td>';
            $params['content'] .= '</tr>';
        }


        $params['totalSize'] = human_filesize($total_size);
        $errors = [
            1 => 'Backup complete.',
            2 => 'A backup is already in progress.',
            3 => 'Backup lock unlocked.',
            4 => 'Restore complete. You need to restart the game engine to continue. (All processes will be stopped.) The world and account databases were restored.',
            5 => 'A restore is already in progress.',
            6 => 'Restore lock unlocked.',
            7 => 'Backup removed.',
            8 => 'Backup not found.',
            9 => 'Backup failed. Check the error log.',
            10 => 'Restore failed. The world remains stopped for recovery; check the error log.',
            11 => 'The safety backup failed, so nothing was restored. Check the error log.',
            12 => 'Restore complete. You need to restart the game engine to continue. (All processes will be stopped.) This backup has no account database, so only the world database was restored.',
        ];
        if (isset($_GET['e']) && isset($errors[$_GET['e']])) {
            $params['error'] = $errors[$_GET['e']];
        }
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/backups.tpl')->getAsString());
    }

    /**
     * Maps a listed backup name to its file. New backups are .sql.gz; older
     * ones were gzip data saved as .sql.
     *
     * @param mixed $name
     * @return string|null
     */
    private function backupFile($name)
    {
        if (!is_string($name)) {
            return null;
        }
        $baseName = basename($name);
        if ($this->backupTimestamp($baseName) === null) {
            return null;
        }
        $base = BACKUP_PATH . "/" . $baseName;
        if (is_file($base . '.sql.gz')) {
            return $base . '.sql.gz';
        }
        if (is_file($base . '.sql')) {
            return $base . '.sql';
        }

        return null;
    }

    /**
     * @param string|null $name
     * @return int|null
     */
    private function backupTimestamp($name)
    {
        if (!is_string($name) || !preg_match('/\Abackup-([0-9]+)(?:-[a-f0-9]+)?\z/', $name, $matches)) {
            return null;
        }

        return (int)floor(((int)$matches[1]) / 1000);
    }

    private function isMutationRequest(): bool
    {
        return isset($_POST['do_backup']) || isset($_POST['force_unlock_backup'])
            || isset($_POST['force_unlock_restore']) || isset($_POST['restore_backup']) || isset($_POST['del_backup']);
    }

    /**
     * @param int $num
     */
    private function showError($num): void
    {
        WebService::redirect("?action=backups&e=$num");
    }
}
