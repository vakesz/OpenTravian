<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Config;

/**
 * The auction house keeps a share of every sale: the seller is paid the sale price minus the fee. The
 * player-facing wording is "You have to pay a 2% fee on auction house sales", and the example is price 100, fee 2,
 * payout 98, rounding the fee up (the dialog computes `ceil(price * fee)`). The percentage is `auction.feePercent`
 * in the world configuration; 0 turns the fee and its confirmation dialog off.
 */
final class AuctionFee
{
    /** The preference that holds when the player chose not to see the fee confirmation again (the reference's name). */
    public const PREFERENCE_CONFIRMED = 'confirmedAuctionFeeAt';

    private function __construct()
    {
    }

    public static function percent(): int
    {
        return max(0, min(100, (int)(Config::getInstance()->auction->feePercent ?? 0)));
    }

    /** The fee taken from a sale price, rounded up. */
    public static function of(int $price): int
    {
        return intdiv(max(0, $price) * self::percent() + 99, 100);
    }

    /** What the seller receives for a sale price. */
    public static function payout(int $price): int
    {
        return max(0, $price) - self::of($price);
    }
}
