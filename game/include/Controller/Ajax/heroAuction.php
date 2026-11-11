<?php

namespace Controller\Ajax;

/**
 * Quantity bundles used by player and system hero auctions (Knowledge Base
 * article 94): bandages, cages, scrolls, ointments, buckets, books of wisdom
 * and tablets of law are sold in stacks of 5, 10, 30 or 50. Equipment and
 * artwork are always sold as a single item.
 */
final class heroAuction
{
    public const ITEM_PACKAGES = [5, 10, 30, 50];

    /** Base item types that support quantity stacks when sold. */
    public const STACKABLE_BASE_TYPES = [7, 8, 9, 10, 11, 12, 13, 14];

    /**
     * @return list<int>
     */
    public static function getPackagesForItem(int $btype, int $typeId = 0): array
    {
        if (!in_array($btype, self::STACKABLE_BASE_TYPES, true)) {
            return [1];
        }

        return self::ITEM_PACKAGES;
    }

    public static function isStackable(int $btype): bool
    {
        return in_array($btype, self::STACKABLE_BASE_TYPES, true);
    }

    /**
     * @return array<mixed>
     */
    public static function getPackagesFakeAuctionForItem(int $btype, int $typeId = 0): array
    {
        return self::getPackagesForItem($btype, $typeId);
    }
}
