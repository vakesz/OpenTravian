<?php

namespace Controller;

use Game\Account\DeletionLock;
use Game\ButtonPagination;
use Controller\Ajax\favouriteTab;
use Controller\Ajax\heroAuction;
use Core\Config;
use Core\Database\DB;
use Core\Helper\PreferencesHelper;
use Core\Helper\RechartsMarkup;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Hero\AuctionFee;
use Game\Hero\AuctionListState;
use Game\Hero\AuctionSort;
use Game\Hero\HeroItemTile;
use Game\Hero\HeroItemTooltip;
use Game\Hero\HeroItems;
use Game\Hero\SessionHero;
use Model\AuctionModel;
use Model\AutomationModel;
use Model\Quest;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function number_format_x;

class HeroAuctionCtrl extends GameCtrl
{
    /** @var mixed */
    private $justContent        = false;
    /** @var mixed */
    private $spreadRandom       = false;

    private bool $canTakePlaceInAuction = false;

    /**
     * Everything that decides what the lists show (view, filter, sorting, layouts, pages, show more), as the
     * request resolved it. The page embeds it for auction-lists.js and the refresh endpoint returns it, so the
     * script always continues from the state the server actually rendered.
     *
     * @var array<string, string|int>
     */
    private array $state = [];

    /**
     * @param bool $justContent
     */
    public function __construct($justContent = false)
    {
        parent::__construct();
        $this->spreadRandom = Config::getInstance()->auction->fakeAuction->SpreadOutRandomlyBetweenPlayers;

        $this->justContent = $justContent;
        $this->view = new GameView();
        $this->view->vars['bodyCssClass'] = 'auctions';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['contentCssClass'] = 'auctions';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        // -1 opens the manual overview (buildingHelp.php); the auction hub has no building entry.
        $this->view->vars['contextualHelpBuildingId'] = -1;
        $session = Session::getInstance();
        $completedAdventures = (int)$session->getSuccessAdventuresCount();
        // An avatar being deleted can no longer take part in auctions (reference: errorUserDel, and for selling
        // auctionSellNotAllowedDeletion).
        $inDeletion = DeletionLock::inDeletion((int)$session->getPlayerId());
        $canTakePlaceInAuction = $completedAdventures >= 10 && !$inDeletion;
        $this->canTakePlaceInAuction = $canTakePlaceInAuction;

        $auctionTabs = favouriteTab::NESTED_KEYS['auctions'];
        $storedFavourite = $session->getStoredFavoriteTab('auctions');
        $favouriteAction = $storedFavourite === null ? null : ($auctionTabs[$storedFavourite] ?? null);
        $action = isset($_REQUEST['action'])
            && is_string($_REQUEST['action'])
            && in_array($_REQUEST['action'], ['buy', 'sell', 'bids'], true)
            ? $_REQUEST['action']
            : ($favouriteAction ?? 'buy');
        //adding menus.
        $view = new PHPBatchView("hero/AuctionMenu");
        $view->vars['action'] = $action;
        $view->vars['favouriteAction'] = $favouriteAction;

        $this->view->vars['contentCssClass'] .= match ($action) {
            'sell' => ' auctionsSell',
            'bids' => ' auctionsAuctionsOverview',
            default => ' auctionsBuy',
        };

        if (!$this->justContent) {
            $this->view->vars['content'] .= '<div id="auctions" class="contentV2">';
            $this->view->vars['content'] .= $view->output();
            if ($inDeletion) {
                $this->view->vars['content'] .= '<p class="error">' . htmlspecialchars((string)T('AccountDeletion', $action === 'sell' ? 'auctionSellNotAllowedDeletion' : 'errorUserDel'), ENT_QUOTES, 'UTF-8') . '</p>';
            } elseif (!$canTakePlaceInAuction) {
                $lockView = new PHPBatchView('hero/AuctionLock');
                $lockView->vars['completed'] = $completedAdventures;
                $lockView->vars['progress'] = min(100, $completedAdventures * 10);
                $this->view->vars['content'] .= $lockView->output();
            }
        }
        if (!$this->justContent) {
            // The lists area that auction-lists.js refreshes in place (the endpoint returns just its content).
            $this->view->vars['content'] .= '<div id="auctionLists">';
        }
        if ($action == 'buy') {
            $this->buy($canTakePlaceInAuction);
        } elseif ($action == 'sell') {
            $this->sell($canTakePlaceInAuction);
        } elseif ($action == 'bids') {
            $this->bids($canTakePlaceInAuction);
        }
        if (!$this->justContent) {
            $this->view->vars['content'] .= '</div><script type="application/json" id="auctionListsState">'
                . json_encode($this->state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script></div>';
        }
    }

    /** @return array<string, string|int> */
    public function state(): array
    {
        return $this->state;
    }

    /**
     * @param bool $canTakePlaceInAuction
     */
    private function buy($canTakePlaceInAuction): void
    {
        $session = Session::getInstance();
        $m = new AuctionModel();
        $detailType = isset($_REQUEST['auctionType']) ? (int)$_REQUEST['auctionType'] : 0;
        if ($detailType > 0) {
            $this->buyDetail($canTakePlaceInAuction, $m, $session, $detailType);
            return;
        }

        $view = new PHPBatchView("hero/AuctionBuy");
        $view->vars['isAdmin'] = $session->isAdmin();
        $this->processBuy($canTakePlaceInAuction);
        $filterKey = $this->resolveFilterKey();
        $grouped = AuctionListState::grouped();
        $layout = AuctionListState::layout(AuctionListState::LIST_BUY, 'layout', 'detailed');

        $view->vars['results'] = '';
        $view->vars['grouped'] = $grouped;
        $view->vars['layout'] = $layout;
        $view->vars['slot'] = $filterKey;
        $view->vars['filterKey'] = $filterKey;
        $view->vars['searchItems'] = $this->getAuctionSearchItems($m, $session->getPlayerId());
        $view->vars['filterHtml'] = $this->renderFilterBar('buy', $canTakePlaceInAuction, [
            'showSearch' => true,
            'searchItems' => $view->vars['searchItems'],
        ]);
        $view->vars['filterBar'] = $view->vars['filterHtml'];
        $page = AuctionListState::page('page');
        $heroItems = new HeroItems();
        if ($grouped) {
            // One row per item type, by item name unless the player picked a column.
            [$sortBy, $sortOrder] = AuctionSort::state('', self::BUY_LIST_COLUMNS, 'NAME', 'ASC');
            $rows = [];
            $groupResult = $m->getAllBuyAuctionGroups($session->getPlayerId(), $filterKey);
            while ($group = $groupResult->fetch_assoc()) {
                $rows[] = $group;
            }
            $groupResult->free();
            $rows = AuctionSort::order($rows, static fn (array $row): array => [
                'NAME' => (string)$heroItems->getHeroItemProperties($row['btype'], $row['type'])['name'],
                'AUCTIONS_COUNT' => (int)$row['auction_count'],
                'NEXT_FINISH_AT' => (int)$row['next_finish'],
                'NEXT_PRICE' => (int)$row['next_price'],
            ], $sortBy, $sortOrder);
        } else {
            // Single auctions, soonest finish first, in the item page's columns and rows.
            [$sortBy, $sortOrder] = AuctionSort::state('', self::BID_LIST_COLUMNS, 'REMAINING_TIME', 'ASC');
            $rows = [];
            $result = $m->getBuyAuction($session->getPlayerId(), 1, 1000000, $filterKey);
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            $rows = AuctionSort::order($this->withBidders($rows), fn (array $row): array => $this->bidRowSortValues($row), $sortBy, $sortOrder);
        }
        $view->vars['sortBy'] = $sortBy;
        $view->vars['sortOrder'] = $sortOrder;
        // The reference shows 10 auctions per page in the detailed layout and 20 in the compact one.
        [$pageRows, $page, $pages] = AuctionListState::slice($rows, $page, AuctionListState::PAGE_SIZE[$layout]);
        foreach ($pageRows as $row) {
            $view->vars['results'] .= $grouped
                ? $this->renderAuctionListRow($row, $grouped)
                : $this->renderAuctionDetailRow($row, $session, $layout, '/auctions/buy');
        }
        $view->vars['pagination'] = $pages > 1 ? ButtonPagination::renderForScript($page, $pages, 'buy') : '';
        if ($rows === []) {
            $view->vars['results'] = '<tr class="empty"><td colspan="5">' . T('Auction', 'noAuction') . '</td></tr>';
        }
        $this->state = [
            'view' => 'buy', 'filter' => $filterKey, 'grouped' => $grouped ? '1' : '0', 'layout' => $layout,
            'sortBy' => $sortBy, 'sortOrder' => $sortOrder, 'page' => $page,
        ];
        $this->view->vars['content'] .= $view->output() . $this->auctionTimerScript();
    }

    /** Columns of the item lists (grouped buy list, per-auction lists) and their default orders. */
    private const BUY_LIST_COLUMNS = ['NAME' => 'ASC', 'AUCTIONS_COUNT' => 'ASC', 'NEXT_FINISH_AT' => 'ASC', 'NEXT_PRICE' => 'ASC'];
    private const BID_LIST_COLUMNS = [
        'ITEM_NAME' => 'ASC', 'CURRENT_BID' => 'DESC', 'CURRENT_BIDDER_NAME' => 'ASC',
        'CURRENT_BIDDER_ALLIANCE_NAME' => 'ASC', 'BIDS_COUNT' => 'DESC', 'REMAINING_TIME' => 'ASC',
    ];

    /**
     * Name and alliance of bidders, by player id.
     *
     * @param array<int, mixed> $uids
     * @return array<int, array{name: string, alliance: string}>
     */
    private function bidderDetails(array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if ($uids === []) {
            return [];
        }
        $result = DB::getInstance()->query(
            'SELECT users.id, users.name, COALESCE(alidata.name, \'\') AS alliance FROM users '
            . 'LEFT JOIN alidata ON alidata.id=users.aid WHERE users.id IN (' . implode(',', $uids) . ')'
        );
        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[(int)$row['id']] = ['name' => (string)$row['name'], 'alliance' => (string)$row['alliance']];
        }
        $result->free();

        return $details;
    }

    /**
     * Adds the highest bidder's name and alliance to auction rows.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withBidders(array $rows): array
    {
        $bidders = $this->bidderDetails(array_column($rows, 'activeUid'));
        foreach ($rows as &$row) {
            $bidder = $bidders[(int)($row['activeUid'] ?? 0)] ?? null;
            $row['active_bidder_name'] = $bidder['name'] ?? '';
            $row['active_bidder_alliance'] = $bidder['alliance'] ?? '';
        }

        return $rows;
    }

    /**
     * @return array<string, int|string> sort values of a per-auction row
     *
     * @param array<string, mixed> $row
     */
    private function bidRowSortValues(array $row): array
    {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);

        return [
            'ITEM_NAME' => (string)$item['name'],
            'CURRENT_BID' => (int)$row['silver'],
            'CURRENT_BIDDER_NAME' => (string)($row['active_bidder_name'] ?? ''),
            'CURRENT_BIDDER_ALLIANCE_NAME' => (string)($row['active_bidder_alliance'] ?? ''),
            'BIDS_COUNT' => (int)$row['bids'],
            'REMAINING_TIME' => (int)$row['time'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param bool $grouped
     */
    private function renderAuctionListRow($row, $grouped): string
    {
        $item = (new HeroItems())->getHeroItemProperties($row['btype'], $row['type']);
        $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $attributes = htmlspecialchars($this->auctionItemMeta((int)$row['btype'], (int)$row['type']), ENT_QUOTES, 'UTF-8');
        $href = '/auctions/buy/' . (int)$row['type'];

        $count = $grouped ? (int)$row['auction_count'] : 1;
        $nextFinish = $grouped ? (int)$row['next_finish'] : (int)$row['time'];
        $nextPrice = $grouped ? (int)$row['next_price'] : (int)$row['silver'];

        return '<tr class="default" role="link" tabindex="0" onclick="window.location.href=\'' . $href . '\'" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();window.location.href=\'' . $href . '\';}">'
            . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
            . '<td class="itemInformation"><span><strong>' . $name . '</strong></span><br><span class="itemMeta">' . $attributes . '</span></td>'
            . '<td class="auctionsCount">' . $count . ' ' . ($count === 1 ? 'Auction' : 'Auctions') . '</td>'
            . '<td class="nextFinishAt">' . $this->renderAuctionTimer($nextFinish) . '</td>'
            . '<td class="nextPrice"><div class="valueWrapper">' . $this->renderAuctionSilverIcon() . number_format_x($nextPrice) . '</div></td>'
            . '</tr>';
    }

    private function buyDetail(
        bool $canTakePlaceInAuction,
        AuctionModel $model,
        Session $session,
        int $type
    ): void {
        $this->processBuy($canTakePlaceInAuction);
        $this->state = ['view' => 'buy', 'type' => $type];
        $page = AuctionListState::page('page');
        $layout = AuctionListState::layout(AuctionListState::LIST_DETAIL, 'layout', 'detailed');
        $result = $model->getBuyAuctionItemRows($session->getPlayerId(), $type, 1, 1000000);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        // The stack-size buttons appear when the item is auctioned in stacks; a chosen size keeps only those stacks.
        $allRows = $rows;
        $stacked = array_filter($rows, static fn (array $row): bool => (int)$row['num'] > 1) !== [];
        $batchSize = $stacked ? AuctionListState::batchSize() : null;
        if ($batchSize !== null) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => (int)$row['num'] === $batchSize));
        }
        // Every auction of the item is ordered before the page is cut: soonest finish first unless a column is picked.
        [$sortBy, $sortOrder] = AuctionSort::state('', self::BID_LIST_COLUMNS, 'REMAINING_TIME', 'ASC');
        $rows = $this->withBidders($rows);
        $rows = AuctionSort::order($rows, fn (array $row): array => $this->bidRowSortValues($row), $sortBy, $sortOrder);
        [$pageRows, $page, $pages] = AuctionListState::slice($rows, $page, AuctionListState::PAGE_SIZE[$layout]);

        if ($allRows === []) {
            $this->view->vars['content'] .= '<div class="error">'
                . htmlspecialchars(T('Auction', 'AuctionNotFound'), ENT_QUOTES, 'UTF-8') . '</div>';
            return;
        }

        $first = $allRows[0];
        $item = (new HeroItems())->getHeroItemProperties((int)$first['btype'], $type);
        $prices = $model->getBuyAuctionItemPrices($session->getPlayerId(), $type);
        $view = new PHPBatchView('hero/AuctionDetail');
        $view->vars['filterBar'] = $this->renderFilterBar('buy', $canTakePlaceInAuction, [
            'showSlots' => false,
            'backPath' => '/auctions/buy',
        ]);
        $view->vars += $this->itemInformationVars((int)$first['btype'], $type, $item, $prices);
        $view->vars['layout'] = $layout;
        $view->vars['batchSizes'] = $stacked ? AuctionListState::BATCH_SIZES : [];
        $view->vars['batchSize'] = $batchSize;
        $view->vars['pagination'] = $pages > 1 ? ButtonPagination::renderForScript($page, $pages, 'detail') : '';
        $view->vars['rows'] = '';
        $view->vars['sortBy'] = $sortBy;
        $view->vars['sortOrder'] = $sortOrder;
        $this->state = [
            'view' => 'buy', 'type' => $type, 'layout' => $layout, 'batchSize' => (string)($batchSize ?? ''),
            'sortBy' => $sortBy, 'sortOrder' => $sortOrder, 'page' => $page,
        ];
        foreach ($pageRows as $row) {
            $view->vars['rows'] .= $this->renderAuctionDetailRow($row, $session, $layout);
        }
        if ($pageRows === []) {
            $view->vars['rows'] = $this->renderAuctionEmptyRow();
        }

        $this->view->vars['content'] .= $view->output() . $this->auctionTimerScript();
    }

    /** @param array<int, array{time: int, silver: int}> $history */
    private function renderAuctionPriceChart(array $history): string
    {
        $sales = [];
        $prices = [];
        if ($history !== []) {
            $now = time();
            $start = $now - 86400;
            $buckets = array_fill(0, 24, ['sum' => 0, 'count' => 0]);
            foreach ($history as $sale) {
                $index = min(23, max(0, (int)floor(((int)$sale['time'] - $start) / 3600)));
                $buckets[$index]['sum'] += (int)$sale['silver'];
                ++$buckets[$index]['count'];
            }
            $sales = array_column($buckets, 'count');
            $prices = array_map(
                static fn(array $bucket): float => $bucket['count'] === 0 ? 0.0 : $bucket['sum'] / $bucket['count'],
                $buckets
            );
        }

        $labels = [
            'empty' => T('Auction', 'itemPriceChartEmpty', 'Not enough data'),
            'now' => T('Auction', 'itemPriceChartXTick_now', 'Now'),
            'hours' => T('Auction', 'itemPriceChartXTick_hours', '[hours]h'),
            'averagePrice' => T('Auction', 'itemPriceChartLabel_averagePrice', 'Average price'),
            'sales' => T('Auction', 'itemPriceChartLabel_sales', 'Sales'),
        ];

        return '<div class="priceChart"><div class="title">'
            . htmlspecialchars(T('Auction', 'itemPriceChartTitle', 'Average price and sales in the last 24h'), ENT_QUOTES, 'UTF-8')
            . '</div>'
            . RechartsMarkup::auctionPriceHistory($prices, $sales, 'common', $labels, getDirection() === 'RTL')
            . '</div>';
    }

    private function renderAuctionHeroItemCell(int $btype, int $type): string
    {
        $slot = htmlspecialchars(
            (string)(new HeroItems())->getHeroItemProperties($btype, $type)['slot'],
            ENT_QUOTES,
            'UTF-8'
        );

        return '<td class="heroItemImage heroV2"><div class="heroItem ' . $this->heroItemTileClasses($btype, $slot) . ' heroItemV1"'
            . $this->itemTooltipAttribute($btype, $type) . '>' . HeroItemTile::ARROWS_SVG
            . '<div class="item item' . $type . ' neutral"></div></div></td>';
    }

    /** Tier or consumable class plus the slot class the inventory shows for the item. */
    private function heroItemTileClasses(int $btype, string $slot): string
    {
        return ($btype >= 7 ? 'consumable' : 'tier1') . ' neutral ' . HeroItems::tileSlot($btype, $slot);
    }

    /** Hero item tiles carry their reference tooltip in the title attribute: an empty tip title and the markup as text. */
    private function itemTooltipAttribute(int $btype, int $type): string
    {
        return ' title="||' . htmlspecialchars(
            HeroItemTooltip::html($btype, $type, $this->session->hero->getHeroStatus() !== SessionHero::STATUS_DEAD),
            ENT_QUOTES,
            'UTF-8'
        ) . '"';
    }

    private function renderAuctionSilverIcon(): string
    {
        return '<svg viewBox="0 0 50 50" aria-hidden="true">'
            . '<circle cx="25" cy="25" r="25" fill="#303a4c"></circle>'
            . '<path fill="#b7cdeb" d="M48 25c0 .59 0 1.17-.1 1.76-.2 2.45-.78 4.8-1.66 7.05-1.08 2.74-2.74 5.19-4.7 7.24-.39.39-.78.78-1.27 1.17-.98.88-2.06 1.66-3.13 2.35C33.62 46.72 29.51 48 25 48s-8.61-1.27-12.14-3.43c-1.08-.69-2.15-1.47-3.13-2.35-.39-.39-.88-.78-1.27-1.17-1.96-2.06-3.62-4.5-4.7-7.24-.88-2.15-1.47-4.6-1.66-7.05C2 26.17 2 25.59 2 25c0-.98.1-1.86.2-2.74C3.57 10.81 13.26 2 25 2s21.43 8.81 22.8 20.26c.1.88.2 1.76.2 2.74Z"></path>'
            . '<path fill="#8ca8d2" d="M2.59 30C8 53.76 41.81 54.3 47.39 30H2.59Z"></path>'
            . '<circle cx="25" cy="25" r="17" fill="#f9fbfc"></circle>'
            . '<path fill="#465c6b" d="M9.2 27.43c-.04-.31-.08-.63-.11-.95-.05-.48-.09-.97-.1-1.49.38-21.33 31.62-21.33 32 0 0 .51-.05 1-.1 1.49-.03.32-.07.63-.11.94l.45 2.57c.49-1.58.75-3.26.75-5 0-9.39-7.61-17-17-17S8 15.61 8 25c0 1.74.26 3.42.75 5l.46-2.56Z"></path>'
            . '<path fill="#6f92aa" d="M41 25C40.62 3.67 9.38 3.67 9 25c.38 21.33 31.62 21.33 32 0Z"></path>'
            . '<path fill="#303a4c" d="m32.36 19.12 2.6 3.97 1.74-2.73-1.21-7.01h-3.18l-.4.6H18.08l-.4-.6H14.5l-1.22 7.08 2.04 2.49 2.55-3.77h3.77v13.57c-.06.74-2.18 1.16-3.1 1.22v2.72l1 1v.46h11v-.36l1-1.14v-2.75c-.91.01-3.17-.72-3.1-1.23V19.11h3.91Z"></path>'
            . '<path fill="#c4e0f5" d="m34.95 21.25.7-1.1-1-5.8h-1.8l-.4.6h-14.9l-.4-.6h-1.8l-1 5.8.9 1.1 2.1-3.1h5.3v14.6c0 1.7-3.1 2.1-3.1 2.1v1.4l.8.8h9.5l.7-.8v-1.5s-3.1-.4-3.1-2.1v-14.6h5.4l2.1 3.2Z"></path>'
            . '<path fill="#eff8f9" d="m14.45 20.25-.1-.1 1-5.8h1.8l.4.6h14.9l.4-.6h1.8l1 5.8-.1.1-.9-5.3h-1.8l-.4.6h-14.9l-.4-.6h-1.8l-.9 5.3Zm5.1 15.17c.2.1 3.7-.8 3.1-2.7 0 1.7-3.1 2.1-3.1 2.1v.6Zm7.9-2.76c-.6 2 2.8 2.8 3.1 2.7v-.6s-3.1-.5-3.1-2.1Z"></path>'
            . '</svg>';
    }

    /**
     * @param array<mixed> $row
     */
    private function renderAuctionBidForm(
        Session $session,
        array $row,
        string $action,
        string $layout = 'detailed',
        bool $finished = false
    ): string {
        $activeUid = (int)$row['activeUid'];
        $isMyBid = $activeUid === $session->getPlayerId();
        $minimum = $activeUid === 0 || $isMyBid ? (int)$row['silver'] : (int)$row['silver'] + 1;
        $maximum = max($minimum, (int)$row['maxSilver']);
        $silver = (int)$session->getAvailableSilver();
        // As in the reference: a gold button (the exchange office, preset to the gold needed) when the silver does not
        // reach the bid, a green one otherwise; the compact layout shows an icon, the detailed one a text; a finished
        // auction takes no bid, so its input shows "-" and its button is dimmed.
        $compact = $layout === 'compact';
        $exchange = $silver < $minimum;
        // The highest bidder cannot bid again: the reference dims the button on their own bids.
        $locked = $finished || ($isMyBid && !$exchange);
        $classes = 'textButtonV2 buttonFramed rectangle ' . ($compact ? 'withIcon' : 'withText') . ($exchange ? ' gold' : ' green')
            . ($locked ? ' disabled' : '');
        $icon = $exchange
            ? '<svg><svg viewBox="0 0 17 13" class="icon"><path d="m4.581 12.184.17.157.17-.158 1.05-.98.197-.184-.2-.183L4.512 9.5H9.5V7.625H4.511L5.97 6.289l.2-.183-.198-.184-1.05-.98-.17-.158-.17.157-3.75 3.437-.201.184.201.185 3.75 3.437Zm7.498-4.126.17.158.17-.157 3.75-3.437.201-.184-.201-.185-3.75-3.437-.17-.157-.17.158-1.05.98-.197.184.2.183L12.488 3.5H7.5v1.875h4.989L11.03 6.711l-.2.183.199.184 1.05.98Z"></path></svg></svg>'
            : '<svg><svg viewBox="0 0 20.18 19.44" class="icon"><path d="M20 9.44l-6.14 6.16a.54.54 0 0 1-.78 0L11 13.5a.56.56 0 0 1 0-.78l1.64-1.64-.64-.64h-1.24l-7.38 8.7L0 15.76l8.67-7.41V7.13l-.57-.57-.74.75a.49.49 0 0 1-.69 0L4.19 4.83a.49.49 0 0 1 0-.69l4-4a.49.49 0 0 1 .69 0l2.45 2.45a.52.52 0 0 1 0 .74l-.45.46.65.65h3.14v3.14l.73.73 1.75-1.75a.54.54 0 0 1 .78 0L20 8.66a.54.54 0 0 1 0 .78zm-9.35 7v3h9v-3z"></path></svg></svg>';
        $label = $compact || $exchange ? $icon : T('Auction', 'Bid');
        $attributes = '';
        if (!$locked) {
            $attributes = $exchange
                ? ' data-exchange-gold="' . (int)ceil(($minimum - $silver) / 100) . '"'
                : ' onclick="this.form.submit(); return false;"';
        }
        $button = '<button type="button" class="' . $classes . '"' . $attributes . '>'
            . ($compact ? $label : '<div>' . $label . '</div>') . '</button>';

        return '<form class="formV2 bidInput" method="post" action="'
            . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="z" value="'
            . htmlspecialchars($session->getChecker(), ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="a" value="'
            . htmlspecialchars((string)$row['secure_id'], ENT_QUOTES, 'UTF-8') . '">'
            . '<label class="input amount"><input type="text" name="bidAmount" placeholder="' . $minimum
            . '" value="' . ($finished ? '-' : $maximum) . '" inputmode="numeric" pattern="[0-9]*"' . ($finished ? ' disabled' : '') . '></label>'
            . $button . '</form>';
    }

    /** The reference highlights a running auction on which the player is the highest bidder. */
    private function ownBidClass(array $row, Session $session, string $default): string
    {
        $activeUid = (int)($row['activeUid'] ?? 0);

        return $activeUid > 0 && $activeUid === $session->getPlayerId() ? 'highlighted' : $default;
    }

    /**
     * @param array<mixed> $row
     */
    private function renderAuctionHighestBidderBadge(array $row, Session $session): string
    {
        $bidder = trim((string)($row['active_bidder_name'] ?? ''));
        $alliance = trim((string)($row['active_bidder_alliance'] ?? ''));
        $bidderLabel = $bidder === ''
            ? 'No bids yet'
            : htmlspecialchars($bidder . ($alliance !== '' ? ' (' . $alliance . ')' : ''), ENT_QUOTES, 'UTF-8');
        $activeUid = (int)($row['activeUid'] ?? 0);
        $modifier = ' none';
        if ($bidder !== '' && $activeUid > 0 && $activeUid === $session->getPlayerId()) {
            $modifier = ' self';
            $bidderLabel = htmlspecialchars((string)T('Auction', 'You'), ENT_QUOTES, 'UTF-8');
        }

        return '<span class="highestBidderBadge' . $modifier . '">' . $bidderLabel . '</span>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderAuctionDetailRow(
        array $row,
        Session $session,
        string $layout = 'detailed',
        string $returnPath = ''
    ): string {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);
        // A stack shows its size after the name, as the reference's single-auction rows do.
        $name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
        $stack = (int)$row['num'] > 1 ? ' (x' . (int)$row['num'] . ')' : '';
        $badge = $this->renderAuctionHighestBidderBadge($row, $session);
        $rowClass = $this->ownBidClass($row, $session, 'default');
        $bidCount = (int)$row['bids'];
        $bidForm = $this->renderAuctionBidForm(
            $session,
            $row,
            $returnPath !== '' ? $returnPath : '/auctions/buy/' . (int)$row['type'],
            $layout
        );

        if ($layout === 'compact') {
            return '<tr class="' . $rowClass . '">'
                . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
                . '<td class="itemInformation"><span><strong>' . $name . '</strong>' . $stack . '</span><br></td>'
                . '<td class="price"><span class="valueWrapper">' . $this->renderAuctionSilverIcon()
                . number_format_x((int)$row['silver']) . '</span></td>'
                . '<td class="highestBidder">' . $badge . '</td>'
                . '<td class="bidCount">' . $bidCount . ' </td>'
                . '<td class="finishedAt">' . $this->renderAuctionTimer((int)$row['time']) . '</td>'
                . '<td class="maximumBid">' . $bidForm . '</td>'
                . '</tr>';
        }

        return '<tr class="' . $rowClass . '">'
            . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
            . '<td class="itemInformation"><span><strong>' . $name . '</strong>' . $stack . '</span><br>'
            . '<span class="valueWrapper">' . $this->renderAuctionSilverIcon()
            . '<span>' . number_format_x((int)$row['silver']) . '</span>'
            . $badge . '</span></td>'
            . '<td class="bidCount">' . $bidCount . ' ' . T('Auction', 'Bids') . '</td>'
            . '<td class="finishedAt">' . $this->renderAuctionTimer((int)$row['time']) . '</td>'
            . '<td class="maximumBid">' . $bidForm . '</td>'
            . '</tr>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderAuctionOverviewBidRow(array $row, Session $session, bool $finished, string $layout = 'detailed'): string
    {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);
        $name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
        $stack = (int)$row['num'] > 1 ? ' (x' . (int)$row['num'] . ')' : '';
        $bidCount = (int)$row['bids'];
        $maximumCell = $this->renderAuctionBidForm($session, $row, '/auctions/overview', $layout, $finished);
        $finishCell = $finished
            ? htmlspecialchars(T('Auction', 'timerFinished'), ENT_QUOTES, 'UTF-8')
            : $this->renderAuctionTimer((int)$row['time']);

        $rowClass = $finished ? 'finished' : $this->ownBidClass($row, $session, 'default');
        if ($layout === 'compact') {
            // Price and Highest bidder are columns of their own in the compact layout.
            return '<tr class="' . $rowClass . '">'
                . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
                . '<td class="itemInformation"><span><strong>' . $name . '</strong>' . $stack . '</span><br></td>'
                . '<td class="price"><span class="valueWrapper">' . $this->renderAuctionSilverIcon()
                . number_format_x((int)$row['silver']) . '</span></td>'
                . '<td class="highestBidder">' . $this->renderAuctionHighestBidderBadge($row, $session) . '</td>'
                . '<td class="bidCount">' . $bidCount . ' </td>'
                . '<td class="finishedAt">' . $finishCell . '</td>'
                . '<td class="maximumBid">' . $maximumCell . '</td>'
                . '</tr>';
        }

        return '<tr class="' . $rowClass . '">'
            . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
            . '<td class="itemInformation"><span><strong>' . $name . '</strong>' . $stack . '</span><br>'
            . '<span class="valueWrapper">' . $this->renderAuctionSilverIcon() . '<span>' . number_format_x((int)$row['silver']) . '</span>'
            . $this->renderAuctionHighestBidderBadge($row, $session) . '</span></td>'
            . '<td class="bidCount">' . $bidCount . ' ' . T('Auction', 'Bids') . '</td>'
            . '<td class="finishedAt">' . $finishCell . '</td>'
            . '<td class="maximumBid">' . $maximumCell . '</td>'
            . '</tr>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderAuctionSoldRow(array $row): string
    {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);
        $name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
        $attributes = htmlspecialchars(
            $this->auctionItemMeta((int)$row['btype'], (int)$row['type']),
            ENT_QUOTES,
            'UTF-8'
        );
        $cancelled = (int)$row['cancel'] === 1;
        $bidCount = $cancelled ? '?' : ((int)$row['bids'] . ' ' . ((int)$row['bids'] === 1 ? 'Bid' : 'Bids'));
        // The reference prints a sale's end as day.month, hh:mm (for example 08.11, 18:12).
        $finish = $cancelled
            ? htmlspecialchars(T('Auction', 'cancelled'), ENT_QUOTES, 'UTF-8')
            : TimezoneHelper::date('d.m, H:i', (int)$row['time']);
        $payout = $cancelled
            ? '<td class="payout">?</td>'
            : $this->renderAuctionPayoutCell((int)$row['silver']);

        return '<tr class="finished">'
            . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
            . '<td class="itemInformation"><span><strong>' . $name . '</strong></span><br>'
            . '<span class="itemMeta">' . $attributes . '</span></td>'
            . '<td class="bidCount">' . $bidCount . '</td>'
            . '<td class="finishedAt">' . $finish . '</td>'
            . $payout
            . '</tr>';
    }

    /** What a sale paid; with a fee in force, hovering it shows the sale price and the fee (reference auctionFeePriceList). */
    private function renderAuctionPayoutCell(int $price): string
    {
        $icon = $this->renderAuctionSilverIcon();
        $value = '<div class="valueWrapper">' . $icon . '<span>' . number_format_x(AuctionFee::payout($price)) . '</span></div>';
        if (AuctionFee::percent() === 0) {
            return '<td class="payout">' . $value . '</td>';
        }
        $label = static fn (string $key): string => htmlspecialchars(T('Auction', $key), ENT_QUOTES, 'UTF-8');
        $tooltip = '<div class="auctionFeePriceList">'
            . '<span>' . $label('auctionFeeExampleLabelPrice') . '</span> ' . $icon . ' <span>' . number_format_x($price) . '</span>'
            . '<span>' . $label('auctionFeeExampleLabelFee') . '</span> ' . $icon
            . ' <span>' . number_format_x(AuctionFee::of($price)) . ' (' . AuctionFee::percent() . '%)</span>'
            . '</div>';

        return '<td class="payout feeHint" title="||' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '">' . $value . '</td>';
    }

    private function renderAuctionEmptyRow(int $colspan = 5): string
    {
        return '<tr class="empty"><td colspan="' . max(1, $colspan) . '"><em>'
            . htmlspecialchars(T('Auction', 'noItemsCurrently'), ENT_QUOTES, 'UTF-8')
            . '</em></td></tr>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderAuctionSellRow(array $row, Session $session): string
    {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);
        $name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
        // A consumable shows its stack size after the name, as the reference does.
        $quantityLabel = (int)$row['btype'] >= 7 ? ' (x' . (int)$row['num'] . ')' : '';
        // The reference prints the start as day.month.year, hh:mm (for example 8.11.2026, 05:44).
        $startedAt = TimezoneHelper::date('j.n.Y, H:i', (int)($row['created'] ?? 0));
        // For the first five minutes the sale can be cancelled: the reference shows the cancel button and the time
        // that is left next to the status.
        $cancelEnds = (int)($row['created'] ?? 0) + AuctionModel::CANCEL_WINDOW_SECONDS;
        $cancel = '';
        if ($cancelEnds > time()) {
            $cancelTitle = htmlspecialchars((string)T('Auction', 'del'), ENT_QUOTES, 'UTF-8');
            $cancel = '<button type="button" class="textButtonV2 buttonSecondary cancelAuction rectangle withIcon toggle brown"'
                . ' title="' . $cancelTitle . '" data-auction-cancel="' . (int)$row['id'] . '" data-cancelled="'
                . htmlspecialchars((string)T('Auction', 'auctionCancelledSuccessfully'), ENT_QUOTES, 'UTF-8') . '">'
                . '<div><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path></svg></div></button>'
                . $this->renderAuctionTimer($cancelEnds);
        }

        return '<tr class="default">'
            . $this->renderAuctionHeroItemCell((int)$row['btype'], (int)$row['type'])
            . '<td class="itemInformation"><span><strong>' . $name . '</strong>' . $quantityLabel . '</span></td>'
            . '<td class="startedAt">' . htmlspecialchars($startedAt, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="status"><span>' . htmlspecialchars((string)T('Auction', 'running'), ENT_QUOTES, 'UTF-8') . '</span>' . $cancel . '</td>'
            . '</tr>';
    }

    /**
     * @param array<string, float|int|string|null> $row
     */
    private function renderAuctionSellInventoryTile(array $row): string
    {
        $item = (new HeroItems())->getHeroItemProperties((int)$row['btype'], (int)$row['type']);
        $itemSlot = htmlspecialchars((string)$item['slot'], ENT_QUOTES, 'UTF-8');
        $consumable = (int)$row['btype'] >= 7;
        $slotClasses = $this->heroItemTileClasses((int)$row['btype'], $itemSlot);

        return '<div class="heroItem ' . $slotClasses . ' heroItemV1" id="item_' . (int)$row['id']
            . '" data-slot="' . $itemSlot . '" data-amount="' . (int)$row['num'] . '" data-min-amount="'
            . min(heroAuction::getPackagesForItem((int)$row['btype'], (int)$row['type'])) . '"' . $this->itemTooltipAttribute((int)$row['btype'], (int)$row['type']) . '>'
            . HeroItemTile::ARROWS_SVG
            . '<div class="item item' . (int)$row['type'] . ' neutral"></div>'
            . ($consumable ? '<div class="count">' . number_format_x((int)$row['num']) . '</div>' : '')
            . '</div>';
    }

    private function renderAuctionSellEmptyInventoryTile(): string
    {
        return '<div class="heroItem empty neutral heroItemV1">' . HeroItemTile::ARROWS_SVG . '<div class="item"></div></div>';
    }

    /**
     * Auction timers are React-shaped plain text, unlike the legacy global
     * countdown spans.  Keeping them self-contained avoids the old unpadded
     * H:MM:SS formatter and matches the reference's HH:MM:SS text.
     */
    private function renderAuctionTimer(int $endTime): string
    {
        return '<span class="timerReact" data-auction-end-time="' . $endTime . '">'
            . $this->formatAuctionTimer($endTime - time())
            . '</span>';
    }

    private function formatAuctionTimer(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    private function auctionTimerScript(): string
    {
        return <<<'HTML'
<script data-auction-once="timer">
    (function () {
        var render = function () {
            var now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('#auctions .timerReact[data-auction-end-time]').forEach(function (timer) {
                var remaining = Math.max(0, Number(timer.dataset.auctionEndTime) - now);
                var hours = Math.floor(remaining / 3600);
                var minutes = Math.floor((remaining % 3600) / 60);
                var seconds = remaining % 60;
                timer.textContent = String(hours).padStart(2, '0') + ':'
                    + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            });
        };
        render();
        window.setInterval(render, 1000);
    }());
</script>
HTML;
    }

    /**
     * the auction catalogue shows a compact category label, not the
     * full per-item benefit text used by inventory tooltips.
     */
    private function auctionItemMeta(int $btype, int $type): string
    {
        if ($btype >= 7) {
            return 'Consumable';
        }

        $category = [
            1 => 'Helmet',
            2 => 'Armor',
            3 => 'Weapon',
            4 => 'Weapon',
            5 => 'Boots',
            6 => 'Horse',
        ][$btype] ?? 'Item';

        return 'Tier ' . (($type - 1) % 3 + 1) . ' / ' . $category;
    }

    /**
     * @return array<int, array{btype: int, type: int, slot: string, name: string, count: int}>
     */
    private function getAuctionSearchItems(AuctionModel $model, int $uid): array
    {
        $counts = $model->getBuyAuctionSearchCounts($uid);
        $heroItems = new HeroItems();
        $items = [];

        foreach (HeroItems::getAvailableItemTypes() as $btype => $types) {
            foreach ($types as $type) {
                $properties = $heroItems->getHeroItemProperties($btype, $type);
                $items[] = [
                    'btype' => $btype,
                    'type' => $type,
                    'slot' => (string)$properties['slot'],
                    'name' => (string)$properties['name'],
                    'count' => $counts[$btype . ':' . $type] ?? 0,
                ];
            }
        }

        usort($items, static function (array $left, array $right): int {
            $leftAvailable = $left['count'] > 0;
            $rightAvailable = $right['count'] > 0;

            if ($leftAvailable !== $rightAvailable) {
                return $leftAvailable ? -1 : 1;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return $items;
    }

    /**
     * @param mixed $canTakePlaceInAuction
     */
    private function processBuy($canTakePlaceInAuction): void
    {
        if ($this->spreadRandom) {
            return;
        }
        $session = Session::getInstance();
        $current_silver = $session->getAvailableSilver();
        $m = new AuctionModel();
        $db = DB::getInstance();
        if ($canTakePlaceInAuction && WebService::isPost() && isset($_REQUEST['a']) && Session::claimAction($_REQUEST['z'] ?? null)) {
            if (Config::getInstance()->dynamic->serverFinished) {
                $this->innerRedirect("InGameWinnerPage");
            } elseif ($session->banned() && $session->getPlayerId() > 2) {
                $this->innerRedirect("InGameBannedPage");
            } elseif ($session->isInVacationMode()) {
                $this->redirect("/options/vacation");
            }
            $auction = $m->getAuctionBySecureId($_REQUEST['a']);
            if ($auction === false || $auction['uid'] == $session->getPlayerId()) {
                $this->view->vars['content'] .= '<div class="error">' . T("Auction", "AuctionNotFound") . '</div>';
            } elseif ($auction['finish'] || $auction['time'] < time()) {
                $this->view->vars['content'] .= '<div class="error">' . T("Auction", "AuctionFinished") . '</div>';
            } else {
                $quest = Quest::getInstance();
                $quest->setQuestBitwise("battle", 9, 1);
                if ($auction['activeUid'] == 0 || $auction['activeUid'] == $session->getPlayerId()) {
                    $min = $auction['silver'];
                } else {
                    $min = $auction['silver'] + 1;
                }
                $max = $auction['activeUid'] == $session->getPlayerId() ? $auction['silver'] : max($auction['maxSilver'], $auction['silver']);
                $bidSilver = abs((int)($_REQUEST['bidAmount'] ?? 0));
                if ($current_silver < $bidSilver && $auction['activeUid'] != $session->getPlayerId()) {
                    $this->view->vars['content'] .= '<div class="error">' . T("Auction", "notEnoughSilver") . '</div>';
                } elseif ($auction['activeUid'] == $session->getPlayerId() && ($current_silver + $auction['maxSilver']) < $bidSilver) {
                    $this->view->vars['content'] .= '<div class="error">' . T("Auction", "notEnoughSilver") . '</div>';
                } elseif ($bidSilver < $min) {
                    $this->view->vars['content'] .= '<div class="error">' . sprintf(T("Auction", "notEnoughSilverAuctionError"), $min) . '</div>';
                } elseif ($bidSilver <= $max && $auction['activeUid'] != $session->getPlayerId() && $auction['activeUid'] != 0) {
                    if ($m->modifyAuction($auction['id'], $auction['activeId'], $auction['activeUid'], $bidSilver, $max, $auction['time'])) {
                        $m->setAsOutBid($m->addBid($session->getPlayerId(), $auction['id']));
                    }
                } else {
                    $activeId = $auction['activeId'];
                    if ($auction['activeUid'] <> $session->getPlayerId()) {
                        $auction['activeId'] = $m->addBid($session->getPlayerId(), $auction['id']);
                    }
                    if ($activeId == 0) {
                        $m->modifyAuction($auction['id'], $auction['activeId'], $session->getPlayerId(), $auction['silver'], $bidSilver, $auction['time']);
                    } else {
                        $bidder = $db->query("SELECT * FROM bids WHERE id=$activeId")->fetch_assoc();
                        if ($bidder['uid'] == $session->getPlayerId()) {
                            $m->modifyAuction($auction['id'], $auction['activeId'], $session->getPlayerId(), $auction['silver'], $bidSilver, $auction['time'], false);
                        } else {
                            if ($m->modifyAuction($auction['id'], $auction['activeId'], $session->getPlayerId(), $max, $bidSilver, $auction['time'])) {
                                $m->setAsOutBid($activeId);
                            }
                        }
                    }
                }
            }
        }
    }

    private function resolveFilterKey(): string
    {
        $allowed = ['all', 'helmet', 'body', 'leftHand', 'rightHand', 'shoes', 'horse', 'bag'];
        $raw = $_REQUEST['filter'] ?? 'all';
        if (is_numeric($raw)) {
            $legacy = (int)$raw;
            $legacyMap = [
                1 => 'helmet',
                2 => 'body',
                3 => 'leftHand',
                4 => 'rightHand',
                5 => 'shoes',
                6 => 'horse',
                7 => 'bag',
                8 => 'bag',
                9 => 'bag',
                10 => 'bag',
                11 => 'bag',
                12 => 'bag',
                13 => 'bag',
                14 => 'bag',
                15 => 'bag',
            ];

            return $legacyMap[$legacy] ?? 'all';
        }

        $key = is_string($raw) ? $raw : 'all';

        return in_array($key, $allowed, true) ? $key : 'all';
    }

    /**
     * @param array{showSearch?: bool, showSlots?: bool, searchItems?: array, wrapperClass?: string, backPath?: string} $options
     * @param array<string, array<int, array<string, int|string>>|bool|string>|array<string, bool>|array<string, bool|string> $options
     */
    private function renderFilterBar(string $action, bool $canTakePlaceInAuction, array $options = []): string
    {
        $session = Session::getInstance();
        $view = new PHPBatchView('hero/AuctionFilter');
        $view->vars['slot'] = $this->resolveFilterKey();
        $view->vars['showSearch'] = !empty($options['showSearch']);
        $view->vars['showSlots'] = !array_key_exists('showSlots', $options) || !empty($options['showSlots']);
        $view->vars['searchItems'] = (array)($options['searchItems'] ?? []);
        $view->vars['wrapperClass'] = (string)($options['wrapperClass'] ?? 'heroV2');
        $view->vars['backPath'] = (string)($options['backPath'] ?? '');
        $view->vars['locked'] = !$canTakePlaceInAuction;
        $view->vars['gold'] = (int)$session->getAvailableGold();
        $view->vars['availableSilver'] = (int)$session->getAvailableSilver();

        return $view->output();
    }

    private function showFilters(string $action, bool $canTakePlaceInAuction = true): string
    {
        $html = $this->renderFilterBar($action, $canTakePlaceInAuction, [
            'showSearch' => $action === 'buy',
        ]);
        $this->view->vars['content'] .= $html;

        return $this->resolveFilterKey();
    }

    /**
     * @param bool $canTakePlaceInAuction
     */
    /** Auctions one player may have running at a time. */
    private const MAX_RUNNING_AUCTIONS = 5;

    /**
     * The "Sell item" dialog of an inventory item: its tile, name, effects, selling prices and price chart, and
     * the stack sizes it can be sold in (the reference asks the player for one when the item stacks).
     *
     * @return array{html: string, amounts: list<int>, amount: int, fee: int, feeHtml: string, skipFee: bool}|null null when the player does not own the item
     */
    public function sellDialog(int $itemId): ?array
    {
        $session = Session::getInstance();
        $item = DB::getInstance()->run('SELECT btype, type, num FROM items WHERE id=? AND uid=? AND proc=0', [$itemId, $session->getPlayerId()]);
        $row = $item ? $item->get_result()->fetch_assoc() : null;
        if (!$row) {
            return null;
        }
        $btype = (int)$row['btype'];
        $type = (int)$row['type'];
        $amount = (int)$row['num'];
        $packages = heroAuction::getPackagesForItem($btype, $type);
        $amounts = array_values(array_filter($packages, static fn (int $package): bool => $package <= $amount));
        $properties = (new HeroItems())->getHeroItemProperties($btype, $type);
        $prices = (new AuctionModel())->getBuyAuctionItemPrices($session->getPlayerId(), $type);

        $information = new PHPBatchView('hero/AuctionItemInformation');
        $information->vars = [
            // Only equipment lists its effects; a consumable shows its name and kind (as on the reference).
            'stats' => $btype <= 6 ? HeroItemTooltip::stats($btype, $type) : [],
        ] + $this->itemInformationVars($btype, $type, $properties, $prices);
        $form = new PHPBatchView('hero/AuctionSellDialog');
        $form->vars['information'] = $information->output();
        $form->vars['amounts'] = count($packages) > 1 ? $amounts : [];
        $form->vars['autoSelect'] = $amount < min($packages) || count($amounts) === 1;

        // The sale is confirmed in a dialog that explains the auction house's fee, unless the player asked not to see
        // it again (the reference remembers that as the preference confirmedAuctionFeeAt).
        return [
            'html' => $form->output(),
            'amounts' => $amounts,
            'amount' => $amount,
            'fee' => AuctionFee::percent(),
            'feeHtml' => AuctionFee::percent() > 0 ? (new PHPBatchView('hero/AuctionFeeDialog'))->output() : '',
            'skipFee' => (string)PreferencesHelper::getPreference(AuctionFee::PREFERENCE_CONFIRMED) !== '',
        ];
    }

    /**
     * Puts one inventory item up for auction. Returns the message to show the player when it cannot be done.
     */
    public function sellItem(int $itemId, int $amount): ?string
    {
        $session = Session::getInstance();
        if (Config::getInstance()->dynamic->serverFinished || $session->banned()) {
            return (string)T('inGame', 'bannedSmallPage');
        }
        if (!$this->canTakePlaceInAuction) {
            return (string)T('AccountDeletion', 'auctionSellNotAllowedDeletion');
        }
        $m = new AuctionModel();
        $db = DB::getInstance();
        if ($m->getMyRunningAuctionsCount($session->getPlayerId()) >= self::MAX_RUNNING_AUCTIONS) {
            return sprintf((string)T('Auction', 'You can only have x auctions at a time'), self::MAX_RUNNING_AUCTIONS);
        }
        $result = $db->run('SELECT * FROM items WHERE id=? AND uid=? AND proc=0', [$itemId, $session->getPlayerId()]);
        $item = $result ? $result->get_result()->fetch_assoc() : null;
        if (!$item) {
            return (string)T('Auction', 'AuctionNotFound');
        }
        $packages = heroAuction::getPackagesForItem((int)$item['btype'], (int)$item['type']);
        if (!in_array($amount, $packages, true) || $amount > (int)$item['num']) {
            return str_replace('[MIN_AMOUNT]', (string)min($packages), (string)T('Auction', 'Not enough items available for auction ([MIN_AMOUNT] items)'));
        }
        if (!$db->begin_transaction()) {
            return (string)T('Auction', 'AuctionNotFound');
        }
        $added = $m->getItemFromUser($item['id'], $item['num'], $amount)
            && $m->addAuction(
                false,
                $session->getPlayerId(),
                $item['btype'],
                $item['type'],
                $amount,
                time() + Config::getInstance()->auction->auctionTime
            );
        if (!$added || !$db->commit()) {
            $db->inTransaction() && $db->rollback();

            return (string)T('Auction', 'AuctionNotFound');
        }
        Quest::getInstance()->setQuestBitwise('battle', 9, 1);

        return null;
    }

    /**
     * Cancels a sale of the player's own, which is only possible in its first five minutes and while nobody has bid.
     * Returns the message to show the player when it cannot be done.
     */
    public function cancelListing(int $auctionId): ?string
    {
        $session = Session::getInstance();
        if (Config::getInstance()->dynamic->serverFinished || $session->banned()) {
            return (string)T('inGame', 'bannedSmallPage');
        }
        $m = new AuctionModel();
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return (string)T('Auction', 'AuctionNotFound');
        }
        $result = $db->query("SELECT * FROM auction WHERE id=$auctionId AND uid=" . (int)$session->getPlayerId() . ' FOR UPDATE');
        $auction = $result ? $result->fetch_assoc() : false;
        if ($auction && AuctionModel::canCancel($auction)
            && $m->cancelAuction($auctionId)
            && $m->addItemToUser((int)$session->getPlayerId(), (int)$auction['btype'], (int)$auction['type'], (int)$auction['num'])
            && $db->commit()) {
            return null;
        }
        if ($db->inTransaction()) {
            $db->rollback();
        }

        return (string)T('Auction', 'AuctionNotFound');
    }

    /**
     * The values the item block of the detail page and of the sell dialog share.
     *
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $prices
     * @return array<string, mixed>
     */
    private function itemInformationVars(int $btype, int $type, array $properties, array $prices): array
    {
        return [
            'tileClasses' => $this->heroItemTileClasses($btype, (string)$properties['slot']),
            'tileTooltip' => $this->itemTooltipAttribute($btype, $type),
            'arrows' => HeroItemTile::ARROWS_SVG,
            'type' => $type,
            'name' => htmlspecialchars((string)$properties['name'], ENT_QUOTES, 'UTF-8'),
            'attributes' => htmlspecialchars($this->auctionItemMeta($btype, $type), ENT_QUOTES, 'UTF-8'),
            'lowest' => (int)($prices['lowest'] ?? 0),
            'average' => (int)($prices['average'] ?? 0),
            'highest' => (int)($prices['highest'] ?? 0),
            // The legacy item catalogue does not persist the newer crafting rarity enum; its auction cards are the
            // reference common tier.
            'rarityIndicator' => RechartsMarkup::auctionRarityIndicator('common'),
            'chart' => $this->renderAuctionPriceChart((new AuctionModel())->getBuyAuctionItemHistory($type, time() - 86400)),
        ];
    }

    private function sell($canTakePlaceInAuction): void
    {

        $session = Session::getInstance();
        $m = new AuctionModel();
        $db = DB::getInstance();
        // Sell defaults to compact until the player picks a layout.
        $layout = AuctionListState::layout(AuctionListState::LIST_SELL, 'layout', 'compact');
        $view = new PHPBatchView("hero/AuctionSell");
        $view->vars['filterBar'] = $this->renderFilterBar('sell', $canTakePlaceInAuction, [
            'showSearch' => false,
            'wrapperClass' => '',
        ]);
        $view->vars['layout'] = $layout;
        $hours = (string)round(Config::getInstance()->auction->auctionTime / 3600, 1);
        $view->vars['spacer'] = sprintf(
            T(
                'Auction',
                'Choose an item to sell An auction can last up to x hours'
            ),
            $hours
        );
        // Wraps the word "auction" for the help tooltip anchor.
        $view->vars['spacer'] = preg_replace(
            '/\bauction\b/i',
            '<span class="tooltipHelpAnchor">$0</span>',
            $view->vars['spacer'],
            1
        );
        $view->vars['auctions'] = '';
        $view->vars['checker'] = $session->getChecker();
        $view->vars['itemsToSale'] = '';
        $result = $m->getAvailableItemsToSell($session->getPlayerId());
        // Like the inventory, the sell grid keeps every item on its own slot and leaves the gaps empty.
        $itemsByPlace = [];
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['placeId'] > 0) {
                $itemsByPlace[(int)$row['placeId']] = $row;
            }
        }
        $visibleSlots = AuctionModel::visibleInventorySlotsForMaxPlaceId($itemsByPlace === [] ? 0 : max(array_keys($itemsByPlace)));
        for ($place = 1; $place <= $visibleSlots; ++$place) {
            if (!isset($itemsByPlace[$place])) {
                $view->vars['itemsToSale'] .= $this->renderAuctionSellEmptyInventoryTile();
                continue;
            }
            $row = $itemsByPlace[$place];
            $view->vars['itemsToSale'] .= $this->renderAuctionSellInventoryTile($row);
        }

        $running = $m->getMyRunningAuctions($session->getPlayerId());
        $runningRows = [];
        while ($row = $running->fetch_assoc()) {
            $runningRows[] = $row;
        }
        $running->free();
        // Reference defaults: newest start first; the item column sorts descending first.
        [$sortBy, $sortOrder] = AuctionSort::state('', ['ITEM_NAME' => 'DESC', 'STARTED_AT' => 'DESC'], 'STARTED_AT', 'DESC');
        $view->vars['sortBy'] = $sortBy;
        $view->vars['sortOrder'] = $sortOrder;
        $showMore = AuctionListState::showMore(AuctionListState::LIST_SELL, 'showMore');
        $view->vars['showMore'] = $showMore;
        $view->vars['hasMoreRows'] = count($runningRows) > AuctionListState::SELL_COLLAPSED_ROWS;
        if ($runningRows === []) {
            $view->vars['auctions'] = $this->renderAuctionEmptyRow(4);
        } else {
            $heroItems = new HeroItems();
            $runningRows = AuctionSort::order($runningRows, static fn (array $row): array => [
                'ITEM_NAME' => (string)$heroItems->getHeroItemProperties((int)$row['btype'], (int)$row['type'])['name'],
                'STARTED_AT' => (int)($row['created'] ?? 0),
            ], $sortBy, $sortOrder);
            // The reference lists the first three auctions until "show more" is chosen.
            foreach ($showMore ? $runningRows : array_slice($runningRows, 0, AuctionListState::SELL_COLLAPSED_ROWS) as $row) {
                $view->vars['auctions'] .= $this->renderAuctionSellRow($row, $session);
            }
        }
        $this->state = [
            'view' => 'sell', 'layout' => $layout, 'sortBy' => $sortBy, 'sortOrder' => $sortOrder,
            'showMore' => $showMore ? '1' : '0',
        ];
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * @param bool $canTakePlaceInAuction
     */
    private function bids($canTakePlaceInAuction): void
    {
        $filter = $this->resolveFilterKey();
        // Both lists of the overview have their own layout, paging and "show more", as in the reference.
        $bidsLayout = AuctionListState::layout(AuctionListState::LIST_BIDS, 'bidsLayout', 'detailed');
        $soldLayout = AuctionListState::layout(AuctionListState::LIST_SOLD, 'soldLayout', 'detailed');
        $bidsMore = AuctionListState::showMore(AuctionListState::LIST_BIDS, 'bidsShowMore');
        $soldMore = AuctionListState::showMore(AuctionListState::LIST_SOLD, 'soldShowMore');
        $view = new PHPBatchView('hero/AuctionBids');
        $view->vars['filterHtml'] = $this->renderFilterBar('bids', $canTakePlaceInAuction, [
            'showSearch' => false,
        ]);
        $view->vars['bidsLayout'] = $bidsLayout;
        $view->vars['soldLayout'] = $soldLayout;
        $view->vars['bidRows'] = '';
        $view->vars['soldRows'] = '';
        $session = Session::getInstance();
        $m = new AuctionModel();
        $this->processBuy($canTakePlaceInAuction);

        $uid = $session->getPlayerId();
        $bids = [];
        $running = $m->getMyRunningBids($uid, $filter);
        while ($row = $running->fetch_assoc()) {
            $row['finishedList'] = false;
            $bids[] = $row;
        }
        $running->free();
        // Every finished bid takes part in the order, not just one page of them.
        $finished = $m->getMyFinishedBids($uid, $filter, 1, 1000000);
        while ($row = $finished->fetch_assoc()) {
            $row['finishedList'] = true;
            $bids[] = $row;
        }
        $finished->free();

        [$bidBy, $bidOrder] = AuctionSort::state('bids', [
            'ITEM_NAME' => 'ASC', 'CURRENT_PRICE' => 'DESC', 'HIGHEST_BIDDER' => 'ASC',
            'CURRENT_BIDDER_ALLIANCE_NAME' => 'ASC', 'BIDS_COUNT' => 'DESC', 'FINISHED_AT' => 'ASC',
        ], 'FINISHED_AT', 'DESC');
        $view->vars['bidSortBy'] = $bidBy;
        $view->vars['bidSortOrder'] = $bidOrder;
        $bids = $this->withBidders($bids);
        $bids = AuctionSort::order($bids, function (array $row): array {
            $values = $this->bidRowSortValues($row);

            return [
                'ITEM_NAME' => $values['ITEM_NAME'],
                'CURRENT_PRICE' => $values['CURRENT_BID'],
                'HIGHEST_BIDDER' => $values['CURRENT_BIDDER_NAME'],
                'CURRENT_BIDDER_ALLIANCE_NAME' => $values['CURRENT_BIDDER_ALLIANCE_NAME'],
                'BIDS_COUNT' => $values['BIDS_COUNT'],
                'FINISHED_AT' => $values['REMAINING_TIME'],
            ];
        }, $bidBy, $bidOrder);

        [$bidPageRows, $bidsPage, $bidsPages] = AuctionListState::slice(
            $bids,
            $bidsMore ? AuctionListState::page('bidsPage') : 1,
            $bidsMore ? AuctionListState::PAGE_SIZE[$bidsLayout] : AuctionListState::COLLAPSED_SIZE[$bidsLayout]
        );
        foreach ($bidPageRows as $row) {
            $view->vars['bidRows'] .= $this->renderAuctionOverviewBidRow($row, $session, $row['finishedList'], $bidsLayout);
        }
        if ($view->vars['bidRows'] === '') {
            $view->vars['bidRows'] = $this->renderAuctionEmptyRow();
        }
        $view->vars['bidsNav'] = $bidsMore && $bidsPages > 1 ? ButtonPagination::renderForScript($bidsPage, $bidsPages, 'bids') : '';
        $view->vars['bidsShowMore'] = $bidsMore;
        $view->vars['bidsHasMore'] = count($bids) > AuctionListState::COLLAPSED_SIZE[$bidsLayout];

        $soldRows = [];
        $sold = $m->getMyFinishedAuctions($uid, 1, 1000000, $filter);
        while ($row = $sold->fetch_assoc()) {
            $soldRows[] = $row;
        }
        $sold->free();
        [$soldBy, $soldOrder] = AuctionSort::state('sold', [
            'ITEM_NAME' => 'ASC', 'BIDS_COUNT' => 'DESC', 'FINISHED_AT' => 'ASC', 'SOLD_FOR' => 'DESC',
        ], 'FINISHED_AT', 'DESC');
        $view->vars['soldSortBy'] = $soldBy;
        $view->vars['soldSortOrder'] = $soldOrder;
        $heroItems = new HeroItems();
        $soldRows = AuctionSort::order($soldRows, static fn (array $row): array => [
            'ITEM_NAME' => (string)$heroItems->getHeroItemProperties((int)$row['btype'], (int)$row['type'])['name'],
            'BIDS_COUNT' => (int)$row['cancel'] === 1 ? 0 : (int)$row['bids'],
            'FINISHED_AT' => (int)$row['time'],
            'SOLD_FOR' => (int)$row['cancel'] === 1 ? 0 : (int)$row['silver'],
        ], $soldBy, $soldOrder);
        [$soldPageRows, $soldPage, $soldPages] = AuctionListState::slice(
            $soldRows,
            $soldMore ? AuctionListState::page('soldPage') : 1,
            $soldMore ? AuctionListState::PAGE_SIZE[$soldLayout] : AuctionListState::COLLAPSED_SIZE[$soldLayout]
        );
        foreach ($soldPageRows as $row) {
            $view->vars['soldRows'] .= $this->renderAuctionSoldRow($row);
        }
        if ($view->vars['soldRows'] === '') {
            $view->vars['soldRows'] = $this->renderAuctionEmptyRow();
        }
        $view->vars['soldNav'] = $soldMore && $soldPages > 1 ? ButtonPagination::renderForScript($soldPage, $soldPages, 'sold') : '';
        $view->vars['soldShowMore'] = $soldMore;
        $view->vars['soldHasMore'] = count($soldRows) > AuctionListState::COLLAPSED_SIZE[$soldLayout];

        $this->state = [
            'view' => 'bids', 'filter' => $filter,
            'bidsLayout' => $bidsLayout, 'bidsSortBy' => $bidBy, 'bidsSortOrder' => $bidOrder,
            'bidsPage' => $bidsPage, 'bidsShowMore' => $bidsMore ? '1' : '0',
            'soldLayout' => $soldLayout, 'soldSortBy' => $soldBy, 'soldSortOrder' => $soldOrder,
            'soldPage' => $soldPage, 'soldShowMore' => $soldMore ? '1' : '0',
        ];
        $this->view->vars['content'] .= $view->output() . $this->auctionTimerScript();
    }

    /**
     * @return mixed
     */
    public function returnContent()
    {
        return $this->view->vars['content'];
    }
}
