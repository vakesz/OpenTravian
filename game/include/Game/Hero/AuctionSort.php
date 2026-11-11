<?php

namespace Game\Hero;

/**
 * Sorting of the auction lists, shared by the controller (state from the query string, row order) and the
 * templates (header cells and arrows). The rules are the reference list component's: a header sorts by its
 * column with that column's default order, a second click on the active column flips the order, and the arrow
 * reads "up" for ascending and "down" for descending, dimmed ("inactive") while its column is not sorted.
 * The state travels as `<prefix>SortBy` / `<prefix>SortOrder` query parameters (plain `sortBy` / `sortOrder`
 * without a prefix); only the columns a list declares are accepted.
 */
final class AuctionSort
{
    /**
     * @param array<string, string> $columns column key => default order (ASC or DESC)
     * @return array{0: string, 1: string} the sorted column and its order
     */
    public static function state(string $prefix, array $columns, string $defaultBy, string $defaultOrder): array
    {
        $by = $_GET[self::parameter($prefix, 'By')] ?? null;
        if (!is_string($by) || !isset($columns[$by])) {
            return [$defaultBy, $defaultOrder];
        }
        $order = $_GET[self::parameter($prefix, 'Order')] ?? null;

        return [$by, in_array($order, ['ASC', 'DESC'], true) ? $order : $columns[$by]];
    }

    public static function parameter(string $prefix, string $kind): string
    {
        return $prefix === '' ? 'sort' . $kind : $prefix . 'Sort' . $kind;
    }

    /**
     * Orders rows by one of their sort values, keeping the incoming order for ties.
     *
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): array<string, int|string> $values the sort values of a row
     * @return list<array<string, mixed>>
     */
    public static function order(array $rows, callable $values, string $by, string $order): array
    {
        $keyed = [];
        foreach ($rows as $index => $row) {
            $keyed[] = [$values($row)[$by] ?? '', $index, $row];
        }
        $direction = $order === 'ASC' ? 1 : -1;
        usort($keyed, static function (array $a, array $b) use ($direction): int {
            $result = is_int($a[0]) && is_int($b[0])
                ? $a[0] <=> $b[0]
                : strnatcasecmp((string)$a[0], (string)$b[0]);

            return $result * $direction ?: $a[1] <=> $b[1];
        });

        return array_column($keyed, 2);
    }

    public static function arrow(string $by, string $default, string $sortBy, string $sortOrder): string
    {
        $active = $by === $sortBy;

        return '<svg viewBox="0 0 10 5" class="sortingArrow' . ($active ? '' : ' inactive') . ' '
            . (($active ? $sortOrder === 'ASC' : $default === 'ASC') ? 'up' : 'down')
            . '" aria-hidden="true"><path d="M0 5l5-5 5 5z"></path></svg>';
    }

    /** `data-` attributes that make a list table sortable by auction-lists.js. */
    public static function tableAttributes(string $prefix, string $sortBy, string $sortOrder): string
    {
        return ' data-sort-param="' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '" data-sort-by="' . $sortBy
            . '" data-sort-order="' . $sortOrder . '"';
    }

    public static function header(
        string $class,
        string $title,
        string $by,
        string $default,
        string $sortBy,
        string $sortOrder,
        string $colspan = ''
    ): string {
        return '<th class="item ' . $class . ' sortable"' . $colspan . ' data-sort-by="' . $by . '" data-sort-default="' . $default
            . '"><div class="cellWrapper"><span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>'
            . self::arrow($by, $default, $sortBy, $sortOrder) . '</div></th>';
    }

    /**
     * A header with the sorting context menu.
     *
     * @param list<array{cls: string, text: string, by: string, default: string}> $menu
     */
    public static function menuHeader(string $class, string $title, array $menu, string $sortBy, string $colspan = ''): string
    {
        $active = false;
        foreach ($menu as $entry) {
            $active = $active || $entry['by'] === $sortBy;
        }

        return '<th class="item ' . $class . ' openContextMenu"' . $colspan . ' data-sort-menu="'
            . htmlspecialchars((string)json_encode($menu, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
            . '"><div class="cellWrapper"><span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</span><span class="sortingContextWrapper"><svg viewBox="0 0 16 20" class="' . ($active ? '' : 'inactive')
            . '" aria-hidden="true"><path d="M8 4h3v15h2V4h3l-4-4zM8 16H5V1H3v15H0l4 4z"></path></svg></span></div></th>';
    }

    /**
     * The item column's context menu: item name, highest bid, highest bidder and bidder alliance.
     *
     * @return list<array{cls: string, text: string, by: string, default: string}>
     */
    public static function itemMenu(string $nameBy, string $bidBy, string $bidderBy, string $allianceBy): array
    {
        return [
            ['cls' => 'itemName', 'text' => (string)T('Auction', 'Item name'), 'by' => $nameBy, 'default' => 'ASC'],
            ['cls' => 'currentBid', 'text' => (string)T('Auction', 'Highest bid'), 'by' => $bidBy, 'default' => 'DESC'],
            ['cls' => 'currentBidderName', 'text' => (string)T('Auction', 'Highest bidder (player)'), 'by' => $bidderBy, 'default' => 'ASC'],
            ['cls' => 'currentBidderAllianceName', 'text' => (string)T('Auction', 'Highest bidder (alliance)'), 'by' => $allianceBy, 'default' => 'ASC'],
        ];
    }

    /**
     * The `<thead>` of a table of single auctions (the item page, the ungrouped buy list and the overview's bids
     * list). Detailed: Item with the sorting menu, Bids, Finish, Maximum bid. Compact: Item, Price and Highest
     * bidder in front of Bids, Finish and Maximum bid. In the compact item and ungrouped tables Item is a plain
     * cell and Highest bidder has a menu (player, alliance); the overview's compact table sorts by both
     * columns directly.
     *
     * @param array{name: string, price: string, bidder: string, alliance: string, bids: string, finish: string} $by sort columns
     */
    public static function bidTableHead(string $layout, array $by, string $sortBy, string $sortOrder, bool $overview = false): string
    {
        $t = static fn (string $key): string => (string)T('Auction', $key);
        $menu = self::itemMenu($by['name'], $by['price'], $by['bidder'], $by['alliance']);
        $tail = self::header('bidCount', $t('Bids'), $by['bids'], 'DESC', $sortBy, $sortOrder)
            . self::header('finishedAt', $t('Finish'), $by['finish'], 'ASC', $sortBy, $sortOrder)
            . '<th class="item maximumBid"><div class="cellWrapper"><span>' . htmlspecialchars($t('Maximum bid'), ENT_QUOTES, 'UTF-8') . '</span></div></th>';
        if ($layout !== 'compact') {
            return '<thead><tr>' . self::menuHeader('itemInformation', $t('Item'), $menu, $sortBy, ' colspan="2"') . $tail . '</tr></thead>';
        }
        $head = $overview
            ? self::header('itemInformation', $t('Item'), $by['name'], 'ASC', $sortBy, $sortOrder, ' colspan="2"')
                . self::header('price', $t('Price'), $by['price'], 'DESC', $sortBy, $sortOrder)
                . self::header('highestBidder', $t('Highest bidder'), $by['bidder'], 'ASC', $sortBy, $sortOrder)
            : '<th class="item itemInformation" colspan="2"><div class="cellWrapper"><span>' . htmlspecialchars($t('Item'), ENT_QUOTES, 'UTF-8') . '</span></div></th>'
                . self::header('price', $t('Price'), $by['price'], 'DESC', $sortBy, $sortOrder)
                . self::menuHeader('highestBidder', $t('Highest bidder'), array_slice($menu, 2), $sortBy);

        return '<thead><tr>' . $head . $tail . '</tr></thead>';
    }
}
