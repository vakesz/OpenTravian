<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Helper\Notification;
use Core\Voucher;

class AutomationModel
{
    /**
     * @param string $columns trusted, hard-coded column list
     * @return array<string, mixed>|false
     */
    public function getUser(int $uid, string $columns): array|false
    {
        return DB::getInstance()->query("SELECT $columns FROM users WHERE id=$uid")->fetch_assoc() ?? false;
    }

    /**
     * @param string $columns trusted, hard-coded column list
     * @return array<string, mixed>|false
     */
    public function getVillage(int $kid, string $columns): array|false
    {
        return DB::getInstance()->query("SELECT $columns FROM vdata WHERE kid=$kid")->fetch_assoc() ?? false;
    }

    /**
     * @param int $resultCode 1 when a player won, 2 when the Natars did
     */
    public function finishTheGame(int $resultCode): void
    {
        $db = DB::getInstance();
        if (DB::scheduledBackupsEnabled()) {
            $db->backup_tables();
        }
        if ($resultCode == 1) {
            $kid = (int) $db->fetchScalar('SELECT kid FROM fdata WHERE f99=100');
            $wData = $db->query("SELECT name, owner FROM vdata WHERE kid=$kid")->fetch_assoc();
            $uData = $db->query("SELECT id, name, aid FROM users WHERE id={$wData['owner']}")->fetch_assoc();
            $text = sprintf("The world round in this server is now finished by %s.", $uData['name']);
            Notification::notify("World round finished", $text);
        } else {
            Notification::notify("World round finished", "The world round in this server is now finished by Natars.");
        }
        $config = Config::getInstance();
        $config->dynamic->serverFinished = $resultCode;
        $db->query("UPDATE config SET serverFinished=$resultCode");
        $messageModel = new PublicMsgModel();
        $messageModel->haveNewMessage($resultCode == 1 ? '[ServerFinishWinner]' : '[ServerFinishNoWinner]');
        foreach (['auction', 'activation', 'ali_invite', 'building_upgrade', 'deleting', 'demolition', 'market', 'send', 'movement', 'odelete', 'research', 'training', 'activation_progress'] as $table) {
            $db->query("DELETE FROM $table");
        }
        $m = new InfoBoxModel();
        foreach ([7, 8, 9, 10, 11, 12] as $type) {
            $m->deleteInfoByTypeInServer($type);
        }
    }

    public function addVoucher(?string $email, int|float $goldNum, string $reason = 'gift', string $player = ''): void
    {
        if ($goldNum <= 0) {
            return;
        }
        Voucher::addVoucher((string) $email, (int) $goldNum, $reason, $player);
    }

    public static function getPlayerName(int $uid, bool $NA = true): ?string
    {
        $name = DB::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);
        if ($name) {
            return (string) $name;
        }

        return $NA ? 'N/A' : null;
    }

    public static function getRandomCountryFlag(): string
    {
        $codes = Config::getInstance()->fakeUsersCountryCodes;
        if (!$codes) {
            $codes = array_keys(Config::getInstance()->countryCodes);
        }

        return strtolower((string) $codes[mt_rand(0, count($codes) - 1)]);
    }

    public static function getOnlineStatusAsImg(int $lastOnline): string
    {
        if ((time() - 600) < $lastOnline) { // 0 Min - 10 Min
            $login_stat = "<img class='online online1' src=img/spacer.gif title='" . T("Alliance", "online now") . "' alt='" . T("Alliance", "online now") . "' />";
        } else if ((time() - 86400) < $lastOnline && (time() - 600) > $lastOnline) { // 10 Min - 1 Days
            $login_stat = "<img class='online online2' src=img/spacer.gif title='" . T("Alliance", "active players") . "' alt='" . T("Alliance", "active players") . "' />";
        } else if ((time() - 259200) < $lastOnline && (time() - 86400) > $lastOnline) { // 1-3 Days
            $login_stat = "<img class='online online3' src=img/spacer.gif title='" . T("Alliance", "active 3days") . "' alt='" . T("Alliance", "active 3days") . "' />";
        } else if ((time() - 604800) < $lastOnline && (time() - 259200) > $lastOnline) { // 3-7 Days
            $login_stat = "<img class='online online4' src=img/spacer.gif title='" . T("Alliance", "active 7days") . "' alt='" . T("Alliance", "active 7days") . "' />";
        } else {
            $login_stat = '<img class="online online5" src="img/spacer.gif" title="' . T("Alliance", "inactive") . '" alt="' . T("Alliance", "inactive") . '" />';
        }
        return $login_stat;
    }
}