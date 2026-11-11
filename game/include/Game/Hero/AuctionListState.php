<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Helper\PreferencesHelper;

/**
 * The state of the auction lists that the reference keeps in the player's preferences or in the page: list
 * layout, grouped buy view and "show more" per list, and the page sizes the reference derives from the layout.
 * A request parameter (the in-place refresh sends every one of them) wins over the stored preference, which wins
 * over the list's default, so a plain page load restores what the player last chose, like the reference.
 */
final class AuctionListState
{
    public const LAYOUTS = ['detailed', 'compact'];

    /** Rows per page of an expanded list (reference: HT). */
    public const PAGE_SIZE = ['detailed' => 10, 'compact' => 20];

    /** Rows of a collapsed overview list (reference: e2). */
    public const COLLAPSED_SIZE = ['detailed' => 3, 'compact' => 5];

    /** Rows of the collapsed "your auctions" list on the sell page; "show more" shows them all. */
    public const SELL_COLLAPSED_ROWS = 3;

    /** Stack sizes the item-type detail list can be narrowed to (reference: the 5x to 50x buttons). */
    public const BATCH_SIZES = [5, 10, 30, 50];

    public const PREFERENCE_BATCH_SIZE = 'auctionsBuyItemTypeDetail_batchSize';

    public const PREFERENCE_BUY_GROUPED = 'auctionsBuy_viewGroupedByItemType';

    /** Preference prefix of each list, as the reference names them (`<prefix>_listLayout`, `<prefix>_showMore`). */
    public const LIST_BUY = 'auctionsBuy';
    public const LIST_DETAIL = 'auctionsBuyItemTypeDetail';
    public const LIST_SELL = 'ongoingSellList';
    public const LIST_BIDS = 'auctionsOverviewBids';
    public const LIST_SOLD = 'auctionsOverviewSold';

    /** The "show more" preference of the sell list is named after the page, not the list (as in the reference). */
    public const SHOW_MORE_PREFERENCE = [
        self::LIST_SELL => 'auctionsSell_showMore',
        self::LIST_BIDS => 'auctionsOverviewBids_showMore',
        self::LIST_SOLD => 'auctionsOverviewSold_showMore',
    ];

    private function __construct()
    {
    }

    public static function layout(string $list, string $requestKey, string $default): string
    {
        $requested = $_GET[$requestKey] ?? null;
        if (is_string($requested) && in_array($requested, self::LAYOUTS, true)) {
            return $requested;
        }
        $stored = PreferencesHelper::getPreference($list . '_listLayout');

        return is_string($stored) && in_array($stored, self::LAYOUTS, true) ? $stored : $default;
    }

    /** The chosen stack size of the item-type detail list, or null for all of them. */
    public static function batchSize(): ?int
    {
        $requested = $_GET['batchSize'] ?? null;
        $value = is_string($requested) ? $requested : PreferencesHelper::getPreference(self::PREFERENCE_BATCH_SIZE);
        $size = is_scalar($value) && ctype_digit((string)$value) ? (int)$value : 0;

        return in_array($size, self::BATCH_SIZES, true) ? $size : null;
    }

    public static function grouped(): bool
    {
        $requested = $_GET['grouped'] ?? null;
        if ($requested === '0' || $requested === '1') {
            return $requested === '1';
        }
        $stored = PreferencesHelper::getPreference(self::PREFERENCE_BUY_GROUPED);

        return $stored === '' ? true : self::truthy($stored);
    }

    public static function showMore(string $list, string $requestKey): bool
    {
        $requested = $_GET[$requestKey] ?? null;
        if ($requested === '0' || $requested === '1') {
            return $requested === '1';
        }

        return self::truthy(PreferencesHelper::getPreference(self::SHOW_MORE_PREFERENCE[$list]));
    }

    /** A page number of the request, at least 1. */
    public static function page(string $requestKey): int
    {
        $page = $_GET[$requestKey] ?? $_REQUEST[$requestKey] ?? 1;

        return is_scalar($page) ? max(1, abs((int)$page)) : 1;
    }

    /**
     * Rows of one page of a list and the page that exists (an out-of-range page falls back to the last one).
     *
     * @template T
     * @param list<T> $rows
     * @return array{0: list<T>, 1: int, 2: int} the page rows, the page number and the number of pages
     */
    public static function slice(array $rows, int $page, int $size): array
    {
        $pages = max(1, (int)ceil(count($rows) / $size));
        $page = min(max(1, $page), $pages);

        return [array_slice($rows, ($page - 1) * $size, $size), $page, $pages];
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }
}
