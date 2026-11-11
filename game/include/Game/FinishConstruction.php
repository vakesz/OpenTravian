<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Database\DB;
use Core\Village;

/**
 * World-level policy for gold completion of Residence and Palace upgrades.
 *
 * Ordinary construction remains eligible for Finish now. These two building
 * types are deliberately opt-in on local speed worlds, and World Wonder
 * villages are never eligible regardless of the persisted setting.
 */
final class FinishConstruction
{
    private const RESIDENCE_GID = 25;
    private const PALACE_GID = 26;

    private function __construct()
    {
    }

    /** A speed world runs above x1; setup can supply a candidate speed before config exists. */
    public static function isSpeedWorld(?int $speed = null): bool
    {
        return ($speed ?? (int)Config::getInstance()->game->speed) > 1;
    }

    public static function isEnabled(): bool
    {
        $config = Config::getInstance();

        return self::isSpeedWorld((int)$config->game->speed)
            && (int)($config->dynamic->finishConstruction ?? 0) === 1;
    }

    public static function canFinishWithGold(int $buildingGid, bool $isWorldWonder): bool
    {
        if ($isWorldWonder || $buildingGid === 40) {
            return false;
        }
        if (!in_array($buildingGid, [self::RESIDENCE_GID, self::PALACE_GID], true)) {
            return true;
        }

        return self::isEnabled();
    }

    /**
     * Shared Finish now eligibility for the three queue types.
     *
     * @param list<int> $buildingGids
     */
    public static function hasEligibleFinishNowQueues(
        array $buildingGids,
        bool $hasResearch,
        bool $hasDemolition,
        bool $isWorldWonder
    ): bool {
        if ($isWorldWonder) {
            return false;
        }
        if ($hasResearch || $hasDemolition) {
            return true;
        }
        foreach ($buildingGids as $buildingGid) {
            if (self::canFinishWithGold($buildingGid, false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the visible village queues once so the Finish now button follows the
     * same policy as the popup and guarded purchase action.
     */
    public static function hasEligibleFinishNowOrder(Village $village): bool
    {
        if ($village->isWW()) {
            return false;
        }

        $buildingGids = [];
        foreach ($village->onLoadBuildings['normal'] ?? [] as $order) {
            $field = $village->getField((int)$order['building_field']);
            $buildingGids[] = (int)($field['item_id'] ?? 0);
        }

        $kid = (int)$village->getKid();
        $statement = DB::getInstance()->run(
            'SELECT EXISTS(SELECT 1 FROM research WHERE kid=?) AS research, EXISTS(SELECT 1 FROM demolition WHERE kid=?) AS demolition',
            [$kid, $kid]
        );
        $otherQueues = $statement === false ? null : $statement->get_result()->fetch_assoc();

        return self::hasEligibleFinishNowQueues(
            $buildingGids,
            is_array($otherQueues) && !empty($otherQueues['research']),
            is_array($otherQueues) && !empty($otherQueues['demolition']),
            false
        );
    }
}
