<?php

declare(strict_types=1);

namespace Game\Account;

use Core\Database\DBI;

use function T;

/**
 * What an avatar in deletion can no longer do. The reference's deletion text promises that it "can no longer access
 * auctions or the exchange office", and its pages refuse with the texts of the hero, marketplace, player and plus
 * bundles, kept under their bundle keys in the AccountDeletion catalogue.
 */
final class DeletionLock
{
    private function __construct()
    {
    }

    public static function inDeletion(int $userId): bool
    {
        return (int) DBI::getInstance()->fetchScalar('SELECT COUNT(uid) FROM deleting WHERE uid=?', [$userId], 0) > 0;
    }

    /**
     * The refusal text when the player's avatar is being deleted, else null.
     *
     * @param string $key errorUserDel (auctions and the exchange office), auctionSellNotAllowedDeletion,
     *                    cannot_send_resources_in_deletion or in_loeschung (buying gold)
     */
    public static function refusal(int $userId, string $key = 'errorUserDel'): ?string
    {
        return self::inDeletion($userId) ? (string) T('AccountDeletion', $key) : null;
    }
}
