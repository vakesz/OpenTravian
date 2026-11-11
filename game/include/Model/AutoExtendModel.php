<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\GoldHelper;

class AutoExtendModel
{
    public function processAutoExtend(): void
    {
        $config = Config::getInstance();
        $db = DB::getInstance();
        $plusRenewalWindow = (int)ceil($config->gold->plusAccountDurationSeconds / 7);
        $boostRenewalWindow = (int)ceil($config->gold->productionBoostDurationSeconds / 7);
        $m = new InfoBoxModel();
        $now = time();
        $time = $now - 3600;
        $village = new VillageModel();
        $result = $db->query(
            "SELECT * FROM autoExtend WHERE finished=0 AND lastChecked < $time"
            . " AND (commence < $now"
            . " OR (type=1 AND commence <= " . ($now + $plusRenewalWindow) . ")"
            . " OR (type BETWEEN 2 AND 5 AND commence <= " . ($now + $boostRenewalWindow) . "))"
            . " AND IF(enabled=0, commence < $now, true) ORDER BY lastChecked ASC LIMIT 100"
        );
        while ($row = $result->fetch_assoc()) {
            $id = (int) $row['id'];
            $uid = (int) $row['uid'];
            $type = (int) $row['type'];
            $commence = (int) $row['commence'];
            if ($commence < $now) {
                $db->query("DELETE FROM autoExtend WHERE id=$id");
                if ($type > 1) {
                    $village->updateUserVillageResources($uid, true);
                }
                continue;
            }
            $db->query('UPDATE autoExtend SET lastChecked=' . time() . " WHERE id=$id");
            $gold = (int) $db->fetchScalar("SELECT (gift_gold+bought_gold) as `gold` FROM users WHERE id=$uid");
            if ($type !== 1 && $type > 5) {
                continue;
            }
            $enough = $type === 1 ? $gold >= $config->gold->plusGold : $gold >= $config->gold->productionBoostGold;
            if (!$enough) {
                if (!$m->hasInfoByType($uid, $type)) {
                    $m->addInfo($uid, 0, $type, '', $commence - 86400, $commence + 86400);
                }
            } elseif ($type === 1) {
                $this->extendPlus($uid, $commence);
            } else {
                $this->extendProductionBoost($uid, $type - 1, $commence);
            }
        }
    }

    private function set(int $uid, int $type, int $commence, bool $enabled): bool
    {
        $enabled = $enabled ? 1 : 0;
        $db = DB::getInstance();
        $finished = $commence < time() ? 1 : 0;
        return $db->query(
            "INSERT INTO autoExtend (uid, type, commence, enabled, finished) VALUES ($uid, $type, $commence, $enabled, $finished)"
            . " ON DUPLICATE KEY UPDATE lastChecked=0, finished=$finished, commence=$commence, enabled=$enabled"
        ) !== false;
    }

    /**
     * Commits the gold debit and renewed feature state together.
     *
     * Auto-extension has no player request to retry: a partial renewal would
     * otherwise spend Gold without granting the corresponding benefit.
     */
    private function renew(int $uid, int $type, int $expectedExpiry, int $showTo, int $coins, string $column): bool
    {
        if (!in_array($column, ['plus', 'b1', 'b2', 'b3', 'b4'], true)) {
            throw new \InvalidArgumentException('Unsupported auto-extension feature column.');
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return false;
        }

        try {
            // The scheduler selects candidates before it enters this
            // transaction.  A manual purchase may have extended the feature
            // in the meantime; never charge based on that stale expiry.
            $userResult = $db->query("SELECT $column FROM users WHERE id=$uid FOR UPDATE");
            $user = $userResult ? $userResult->fetch_assoc() : null;
            if (!$user || (int)$user[$column] !== $expectedExpiry) {
                $db->rollback();
                return false;
            }

            if (!GoldHelper::decreaseGold($uid, $coins, true)
                || $db->query("UPDATE users SET $column=$showTo WHERE id=$uid") === false
                || !$this->set($uid, $type, $showTo, true)
                || $db->query("DELETE FROM infobox WHERE uid=$uid AND type=$type") === false) {
                $db->rollback();
                return false;
            }
            if (!$db->commit()) {
                $db->rollback();
                return false;
            }
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }

        InfoBoxModel::invalidateUserInfoBoxCache($uid);
        return true;
    }

    private function extendPlus(int $uid, int $now, ?int $till = null): bool
    {
        $config = Config::getInstance();
        $till ??= (int) $config->gold->plusAccountDurationSeconds;

        return $this->renew($uid, 1, $now, $now + $till, (int) $config->gold->plusGold, 'plus');
    }

    private function extendProductionBoost(int $uid, int $resourceId, int $now, ?int $till = null): bool
    {
        $config = Config::getInstance();
        $till ??= (int) $config->gold->productionBoostDurationSeconds;

        return $this->renew(
            $uid,
            1 + $resourceId,
            $now,
            $now + $till,
            (int) $config->gold->productionBoostGold,
            'b' . $resourceId
        );
    }

    public function setAutoExtendState(int $uid, int $type, bool|int $result, int $endTime): bool
    {
        $m = new InfoBoxModel();
        $m->deleteInfoByType($uid, $type);
        if (!$result) {
            $m->addInfo($uid, 0, $type, '', $endTime - 86400, $endTime + 86400);
        }

        return $this->set($uid, $type, $endTime, (bool) $result);
    }

    /**
     * Keeps the auto-extension and the expiry notice right after something other than a purchase or a renewal moved a
     * feature's end (a daily-quest reward). The renewal compares users.<column> with autoExtend.commence, so a stale
     * commence would stop it from ever renewing; the "will be finished" notice exists only while the feature will
     * NOT be renewed. Same rule as a purchase (GoldHelper::purchasePremiumFeature).
     */
    public function refreshAfterExtension(int $uid, int $type, int $endTime): bool
    {
        $refreshed = $this->setAutoExtendState($uid, $type, $this->hasAutoExtend($uid, $type), $endTime);
        InfoBoxModel::invalidateUserInfoBoxCache($uid);

        return $refreshed;
    }

    public function hasAutoExtend(int $uid, int $type): bool
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM autoExtend WHERE uid=$uid AND type=$type AND enabled=1") >= 1;
    }
}
