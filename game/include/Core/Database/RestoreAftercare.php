<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Config;
use Model\InfoBoxModel;

/**
 * What a restored world needs once its databases are back, shared by the control panel restore and
 * tools/backup.php so the two cannot drift apart: the downtime since the backup is recorded as the
 * world's delay and the timed announcements (artifact release, World Wonder plans, catapults) are
 * moved by it, the cached infoboxes are dropped, and the world schema patches run so a backup made
 * by an older release is brought up to date.
 */
final class RestoreAftercare
{
    private function __construct()
    {
    }

    /**
     * @param int $backupTime when the restored backup was taken (unix seconds)
     */
    public static function apply(int $backupTime): void
    {
        $db = DB::getInstance();
        $conf = Config::getInstance();
        $delayTime = max(0, time() - $backupTime);
        if (!$db->query('UPDATE config SET delayTime=' . $delayTime)) {
            throw new \RuntimeException('Unable to update the restored delay time.');
        }

        $infoBoxModel = new InfoBoxModel();
        $infoBoxModel->deleteInfoByTypeInServer(8);
        $infoBoxModel->deleteInfoByTypeInServer(9);
        $infoBoxModel->deleteInfoByTypeInServer(15);

        $infoBoxModel->addInfo(0, 1, 8, '', (int)($conf->timers->ArtifactsReleaseTime + $delayTime - 3 * 86400), (int)($conf->timers->ArtifactsReleaseTime + $delayTime));
        $infoBoxModel->addInfo(0, 1, 9, '', (int)($conf->timers->wwPlansReleaseTime + $delayTime - 3 * 86400), (int)($conf->timers->wwPlansReleaseTime + $delayTime));

        if ($conf->game->catapultAvailableTime > $conf->game->start_time) {
            $infoBoxModel->addInfo(0, 1, 15, '', (int)$conf->game->start_time, (int)$conf->game->catapultAvailableTime);
        }

        InfoBoxModel::invalidatePublicInfoBox();
        InfoBoxModel::invalidateAllUsersPrivateInfoBox();
        sleep(1);
        require INCLUDE_PATH . 'update.php';
    }
}
