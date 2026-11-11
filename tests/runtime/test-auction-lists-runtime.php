<?php

declare(strict_types=1);

// `GET auction/items`, the lists area of the auction pages as JSON: sorting, paging, layouts, grouped, "show more" and the
// filter come from the controller's own render and are checked on a throwaway fixture player with auction data
// (tests/support/auction-fixture.php), including that a stored preference becomes the default.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Auction lists runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/ajax.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/ajax.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';
require_once dirname(__DIR__) . '/support/auction-fixture.php';

use Controller\Ajax\auction\items;
use Controller\HeroAuctionCtrl;
use Core\Database\DBI;
use Core\Helper\PreferencesHelper;
use Core\Helper\WebService;
use Core\Session;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Auction lists runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
auctionFixtureSeed($user['id']);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
$session = Session::getInstance();

/** @return array<string, mixed> */
$call = static function (array $query) use ($session): array {
    $_GET = $query;
    $_REQUEST = $query;
    $response = ['error' => false];
    (new items($response))->dispatch();

    return $response;
};
$rows = static fn (string $html, string $needle): int => substr_count($html, $needle);
$firstHref = static fn (string $html): string => preg_match('#href=\'(/auctions/buy/\d+)\'#', $html, $match) === 1 ? $match[1] : '';

// A bad or missing view is an error, not an empty area.
foreach ([[], ['view' => 'nope'], ['view' => ['buy']]] as $bad) {
    if (empty($call($bad)['error'])) {
        $fail('A missing or unknown view must be refused: ' . json_encode($bad));
    }
}

// Buy: grouped and detailed by default, ten groups per page and a pager the script can drive.
$buy = $call(['view' => 'buy']);
$state = $buy['state'];
if (($state['view'] ?? '') !== 'buy' || $state['grouped'] !== '1' || $state['layout'] !== 'detailed' || $state['page'] !== 1
    || $state['filter'] !== 'all' || $state['sortBy'] !== 'NAME' || $state['sortOrder'] !== 'ASC') {
    $fail('The default buy state must be grouped, detailed, page 1, unfiltered and sorted by name: ' . json_encode($state));
}
if ($rows($buy['html'], '<tr class="default"') !== 10 || !str_contains($buy['html'], 'data-auction-page="2"')) {
    $fail('The first buy page must list ten groups with a pager.');
}
if (preg_match('#/auctions\?|\?filter=|location\.reload#', $buy['html']) === 1) {
    $fail('The lists area must not reload the page: no list links and no reload handlers.');
}
$second = $call(['view' => 'buy', 'page' => '2']);
if ($second['state']['page'] !== 2 || $firstHref($second['html']) === $firstHref($buy['html']) || $firstHref($second['html']) === '') {
    $fail('Page 2 must list other groups.');
}
$last = $call(['view' => 'buy', 'page' => '99'])['state']['page'];
if ($last < 3 || $call(['view' => 'buy', 'page' => '-500'])['state']['page'] !== $last || $call(['view' => 'buy', 'page' => 'x'])['state']['page'] !== 1) {
    $fail('A page past the end falls back to the last page (a negative number is its absolute value, text is page 1).');
}
// Compact shows 20 per page, ungrouped lists every auction, and the filter keeps only that slot.
$compact = $call(['view' => 'buy', 'layout' => 'compact']);
if ($compact['state']['layout'] !== 'compact' || $rows($compact['html'], '<tr class="default"') !== 20) {
    $fail('The compact buy layout lists twenty groups per page.');
}
$ungrouped = $call(['view' => 'buy', 'grouped' => '0']);
if ($ungrouped['state']['grouped'] !== '0' || $rows($ungrouped['html'], '<tr class="default"') !== 10) {
    $fail('The ungrouped buy list lists ten auctions per page.');
}
// The ungrouped view lists single auctions in the item page's columns (Item with its menu, Bids, Finish, Maximum bid
// with a bid input) and starts with the soonest finish; compact adds Price and Highest bidder.
if (!str_contains($ungrouped['html'], 'openContextMenu') || !str_contains($ungrouped['html'], 'maximumBid')
    || !str_contains($ungrouped['html'], 'name="bidAmount"') || str_contains($ungrouped['html'], 'auctionsCount')
    || $ungrouped['state']['sortBy'] !== 'REMAINING_TIME' || $ungrouped['state']['sortOrder'] !== 'ASC') {
    $fail('The ungrouped buy list must show single auctions with bid inputs, soonest finish first: ' . json_encode($ungrouped['state']));
}
$ungroupedCompact = $call(['view' => 'buy', 'grouped' => '0', 'layout' => 'compact']);
if ($rows($ungroupedCompact['html'], '<th class="item price sortable"') !== 1 || $rows($ungroupedCompact['html'], '<td class="price">') !== 20
    || !str_contains($ungroupedCompact['html'], 'th class="item highestBidder openContextMenu"')) {
    $fail('The compact ungrouped list adds Price and Highest bidder (with its menu) and lists twenty auctions.');
}
$helmets = $call(['view' => 'buy', 'filter' => 'helmet']);
// The world may hold ten or more helmet auctions, so a shorter list proves nothing: every row must be a helmet.
if ($helmets['state']['filter'] !== 'helmet' || $rows($helmets['html'], '<tr class="default"') === 0
    || preg_match('/heroItem (?:tier1|consumable) neutral (?:body|shoes|leftHand|rightHand|horse|bag)\b/', (string)substr($helmets['html'], (int)strpos($helmets['html'], '<tbody'))) === 1
    || $call(['view' => 'buy', 'filter' => 'bogus'])['state']['filter'] !== 'all') {
    $fail('The slot filter keeps helmets only and an unknown filter means all.');
}
$byPrice = static function (string $order) use ($call): array {
    $html = $call(['view' => 'buy', 'sortBy' => 'NEXT_PRICE', 'sortOrder' => $order])['html'];
    preg_match_all('#<td class="nextPrice">.*?(\d[\d.,]*)</div>#s', $html, $matches);

    return array_map(static fn (string $price): int => (int)preg_replace('/\D/', '', $price), $matches[1]);
};
$asc = $byPrice('ASC');
$desc = $byPrice('DESC');
if (count($asc) !== 10 || $asc !== array_values($asc = (static function (array $v): array { sort($v); return $v; })($asc)) || max($desc) < max($asc) && $desc[0] < $desc[1]) {
    $fail('Sorting by price must order every auction, ascending and descending.');
}
if ($desc[0] < $desc[count($desc) - 1]) {
    $fail('Descending order must start with the highest price.');
}

// The endpoint is the page's own render: the full page embeds exactly this area and the state it returns.
$_GET = $_REQUEST = ['action' => 'buy'];
$timers = static fn (string $html): string => (string)preg_replace('/\d\d:\d\d:\d\d/', '00:00:00', $html);
$page = $timers((new HeroAuctionCtrl(false))->returnContent());
if (!str_contains($page, '<div id="auctionLists">' . $timers($buy['html']) . '</div>')
    || !str_contains($page, '<script type="application/json" id="auctionListsState">')) {
    $fail('The full page must contain the endpoint\'s area and state.');
}

// Sell: three of the five running auctions until "show more".
$sell = $call(['view' => 'sell']);
$sellRows = static fn (array $answer): int => $rows(explode('ongoingSellListWrapper', $answer['html'])[1] ?? '', '<tr class="');
if ($sell['state']['layout'] !== 'compact' || $sell['state']['showMore'] !== '0' || !str_contains($sell['html'], 'data-auction-more-state="0"')
    || !str_contains($sell['html'], '>Show more<')) {
    $fail('The sell list starts collapsed with a "Show more" link: ' . json_encode($sell['state']));
}
$sellMore = $call(['view' => 'sell', 'showMore' => '1']);
if ($sellMore['state']['showMore'] !== '1' || !str_contains($sellMore['html'], '>Show less<') || $sellRows($sellMore) <= $sellRows($sell)) {
    $fail('"Show more" must list all running auctions, with a "Show less" link.');
}
if (!str_contains($sell['html'], 'data-auction-keep="sellItems"')) {
    $fail('The sell inventory and form must be marked as parts that survive a refresh.');
}

// Overview: the bids list and the sold list each have their own layout, page and "show more".
$bids = $call(['view' => 'bids']);
$bidRows = static fn (array $answer): int => $rows(explode('soldListWrapper', $answer['html'])[0], '<tr class="');
if ($bids['state']['bidsShowMore'] !== '0' || $bidRows($bids) !== 3 || $rows($bids['html'], 'data-auction-more=') !== 2) {
    $fail('The overview lists start with three rows each: ' . json_encode($bids['state']));
}
$bidsCompact = $call(['view' => 'bids', 'bidsLayout' => 'compact']);
if ($bidsCompact['state']['bidsLayout'] !== 'compact' || $bidsCompact['state']['soldLayout'] !== 'detailed' || $bidRows($bidsCompact) !== 5) {
    $fail('The bids list shows five rows when collapsed in the compact layout and does not change the sold layout.');
}
if ($rows($bidsCompact['html'], '<td class="price">') !== 5 || $rows($bidsCompact['html'], '<td class="highestBidder">') !== 5
    || !str_contains($bidsCompact['html'], 'th class="item highestBidder sortable"') || $rows($bids['html'], '<td class="price">') !== 0) {
    $fail('The compact bids table has Price and Highest bidder columns; the detailed one does not.');
}
$expanded = $call(['view' => 'bids', 'bidsShowMore' => '1']);
if ($bidRows($expanded) !== 10 || !str_contains($expanded['html'], 'data-auction-list="bids"')) {
    $fail('The expanded bids list shows ten rows with a pager.');
}
// A finished auction takes no bid: its input shows "-" disabled, its button is dimmed and it says "Finished"; the
// compact layout shows icon buttons, the detailed one text buttons.
if (!str_contains($expanded['html'], 'value="-"') || !str_contains($expanded['html'], 'disabled') || !str_contains($expanded['html'], '>Finished<')
    || !str_contains($expanded['html'], 'withText green disabled') || !str_contains($bidsCompact['html'], 'withIcon green')) {
    $fail('Finished bids show a disabled "-" bid input and "Finished"; the compact bid button is an icon.');
}
$expanded2 = $call(['view' => 'bids', 'bidsShowMore' => '1', 'bidsPage' => '2']);
if ($expanded2['state']['bidsPage'] !== 2 || $bidRows($expanded2) !== 4) {
    $fail('Page 2 of the fourteen bids lists the remaining four.');
}
$sold = $call(['view' => 'bids', 'soldShowMore' => '1', 'soldPage' => '2']);
if ($sold['state']['soldPage'] !== 2 || $sold['state']['bidsShowMore'] !== '0' || !str_contains($sold['html'], 'data-auction-list="sold"')) {
    $fail('The sold list pages on its own.');
}
if ($call(['view' => 'bids', 'filter' => 'helmet'])['state']['filter'] !== 'helmet') {
    $fail('The overview filter applies to both lists.');
}

// An item's own auctions.
$type = (int)$pdo->fetchScalar('SELECT type FROM auction WHERE finish=0 AND uid=0 AND btype=4 ORDER BY id LIMIT 1');
$detail = $call(['view' => 'buy', 'type' => (string)$type, 'layout' => 'compact']);
if ($detail['state']['type'] !== $type || $detail['state']['layout'] !== 'compact' || !str_contains($detail['html'], 'data-auction-list="detail"')) {
    $fail('An item type shows its own auctions: ' . json_encode($detail['state']));
}

// The wallet for the exchange widget.
$wallet = $buy['wallet'];
if ($wallet['silver'] !== (int)$session->getAvailableSilver() || $wallet['gold'] !== (int)$session->getAvailableGold()
    || $wallet['silverText'] !== number_format_x($wallet['silver'])) {
    $fail('The wallet must be the player\'s gold and silver: ' . json_encode($wallet));
}

// Stored preferences are the defaults of a plain request; invalid values are not stored.
PreferencesHelper::setPreference('auctionsBuy_listLayout', 'compact');
PreferencesHelper::setPreference('auctionsBuy_viewGroupedByItemType', 'false');
PreferencesHelper::setPreference('auctionsOverviewBids_showMore', 'true');
PreferencesHelper::setPreference('ongoingSellList_listLayout', 'sideways');
$preferred = $call(['view' => 'buy'])['state'];
if ($preferred['layout'] !== 'compact' || $preferred['grouped'] !== '0') {
    $fail('The stored layout and grouped view must be the buy defaults: ' . json_encode($preferred));
}
if ($call(['view' => 'bids'])['state']['bidsShowMore'] !== '1' || $call(['view' => 'sell'])['state']['layout'] !== 'compact') {
    $fail('A stored "show more" is the default and an invalid layout is ignored (sell stays compact).');
}
if ($call(['view' => 'buy', 'layout' => 'detailed', 'grouped' => '1'])['state']['layout'] !== 'detailed') {
    $fail('A request parameter wins over the stored preference.');
}
PreferencesHelper::setPreference('auctionsBuy_listLayout', 'detailed');
PreferencesHelper::setPreference('auctionsBuy_viewGroupedByItemType', 'true');
PreferencesHelper::setPreference('auctionsOverviewBids_showMore', 'false');

echo "Auction lists runtime passed.\n";
