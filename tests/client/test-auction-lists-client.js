"use strict";

// The auction lists update in place: every control of the buy, item, sell and overview pages (filter, sorting, grouped
// switch, layout, reload, pager, "show more") refreshes the lists area from `GET auction/items` without navigating or
// changing the URL, keeps the filter bar and its exchange office working, and layout, grouped and "show more" survive
// a reload as preferences. Runs on a throwaway fixture player with auction data; needs docker compose.

const assert = require("node:assert/strict");
const { chromium } = require("playwright");
const { startFixture, loginState } = require("./support/fixture-session.js");

const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
if (!["localhost", "127.0.0.1"].includes(new URL(baseUrl).hostname)) {
  throw new Error("This check creates a fixture player; only loopback worlds are supported.");
}

const rowCount = (page, selector = "#auctionLists tbody tr") => page.locator(selector).count();

// Page loads and refresh requests of one case: a control must not navigate and must ask the endpoint once.
function watch(page) {
  const seen = { navigations: 0, requests: [] };
  page.on("framenavigated", (frame) => {
    if (frame === page.mainFrame()) {
      seen.navigations += 1;
    }
  });
  page.on("request", (request) => {
    if (request.url().includes("/api/v1/auction/items")) {
      seen.requests.push(new URL(request.url()).searchParams);
    }
  });
  return seen;
}

async function act(page, seen, action, quietMs = 1200) {
  const before = { url: page.url(), navigations: seen.navigations, requests: seen.requests.length };
  await action();
  await page.waitForTimeout(quietMs);
  assert.equal(page.url(), before.url, "the URL must not change");
  assert.equal(seen.navigations, before.navigations, "the control must not navigate");
  return seen.requests.slice(before.requests);
}

async function open(page, seen, path) {
  await page.goto(baseUrl + path, { waitUntil: "networkidle" });
  seen.navigations = 0;
  seen.requests.length = 0;
}

(async () => {
  const fixture = await startFixture("auctions");
  if (!fixture) {
    console.log("auction lists: fixture player unavailable (needs docker compose and the php container); skipped.");
    return;
  }
  const browser = await chromium.launch({ headless: true });
  try {
    const storageState = await loginState(browser, baseUrl, fixture.account);
    const context = await browser.newContext({ storageState, viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    const seen = watch(page);

    // ---- buy ----
    await open(page, seen, "/auctions/buy");
    assert.equal(await rowCount(page, "#auctionLists tr.default"), 10);
    const firstRow = () => page.locator("#auctionLists tr.default").first().getAttribute("onclick");

    let requests = await act(page, seen, () => page.locator(".filterSlot.helmet").click());
    assert.equal(requests.length, 1);
    assert.equal(requests[0].get("filter"), "helmet");
    assert.ok(await page.locator(".filterSlot.helmet.active").count(), "the clicked slot is active at once");
    // The world may hold ten or more helmet auctions, so a shorter list proves nothing: every row must be a helmet.
    assert.equal(await rowCount(page, "#auctionLists tr.default:not(:has(.heroItem.helmet))"), 0, "the filter keeps helmets only");
    assert.ok((await rowCount(page, "#auctionLists tr.default")) > 0, "the helmet filter lists helmets");
    await act(page, seen, () => page.locator(".filterSlot.all").click());
    assert.equal(await rowCount(page, "#auctionLists tr.default"), 10);

    // sorting: a header sorts by its column, a second click flips the order
    requests = await act(page, seen, () => page.locator("#auctionLists th.nextPrice").click());
    assert.deepEqual([requests[0].get("sortBy"), requests[0].get("sortOrder")], ["NEXT_PRICE", "ASC"]);
    requests = await act(page, seen, () => page.locator("#auctionLists th.nextPrice").click());
    assert.deepEqual([requests[0].get("sortBy"), requests[0].get("sortOrder")], ["NEXT_PRICE", "DESC"]);
    assert.ok(await page.locator("#auctionLists th.nextPrice svg.sortingArrow:not(.inactive)").count(), "the arrow marks the sorted column");

    // pager
    requests = await act(page, seen, () => page.locator("#auctionLists .pagination .forward").click());
    assert.equal(requests[0].get("page"), "2");
    assert.ok(await page.locator("#auctionLists .pagination .page.selected + .pageIndex").evaluate((node) => node.textContent === "2"));
    await act(page, seen, () => page.locator("#auctionLists .pagination .back").click());

    // search and exchange office live in the kept filter bar
    await act(page, seen, () => page.locator("#auctions .openSearch").click(), 300);
    assert.ok(await page.locator("#auctions .itemTypeSearch.opened").count());
    requests = await act(page, seen, () => page.locator(".listLayout_compact").click());
    assert.equal(requests[0].get("layout"), "compact");
    assert.equal(await rowCount(page, "#auctionLists tr.default"), 20, "the compact layout lists twenty per page");
    assert.ok(await page.locator("#auctions .itemTypeSearch.opened").count(), "an open search survives a refresh");
    await act(page, seen, () => page.locator("#auctions .openExchangeOffice").click(), 600);
    assert.equal(await page.locator("#dialogContent #exchangeOffice").count(), 1, "the exchange office opens once after refreshes");
    await page.keyboard.press("Escape");

    // reload button, grouped switch
    requests = await act(page, seen, () => page.locator("#auctionLists button.reload").click());
    assert.equal(requests.length, 1);
    requests = await act(page, seen, () => page.locator("#auctionLists input[data-auction-grouped]").click());
    assert.equal(requests[0].get("grouped"), "0");
    // ungrouped (the layout is still compact): single auctions in the item page's columns, with a bid input each
    assert.equal(await page.locator("#auctionLists th.price").count(), 1);
    assert.equal(await page.locator("#auctionLists th.highestBidder.openContextMenu").count(), 1);
    assert.equal(await page.locator("#auctionLists th.auctionsCount").count(), 0, "no group columns");
    assert.ok((await page.locator("#auctionLists td.maximumBid input[name='bidAmount']").count()) >= 10, "every auction has a bid input");

    // layout and grouped are preferences: a page load restores them
    await page.reload({ waitUntil: "networkidle" });
    assert.ok(await page.locator(".listLayout_compact.active").count(), "the compact layout survives a reload");
    assert.equal(await page.locator("#auctionLists input[data-auction-grouped]").isChecked(), false, "the ungrouped view survives a reload");
    seen.navigations = 0;
    await act(page, seen, () => page.locator("#auctionLists input[data-auction-grouped]").click());
    await act(page, seen, () => page.locator(".listLayout_detailed").click());
    assert.equal(await rowCount(page, "#auctionLists tr.default"), 10, "grouped and detailed again");

    // ---- an item's own auctions ----
    const href = (await firstRow()).match(/'(\/auctions\/buy\/\d+)'/)[1];
    await open(page, seen, href);
    requests = await act(page, seen, () => page.locator("#auctionLists .listLayout_compact").click());
    assert.equal(requests[0].get("layout"), "compact");
    assert.equal(requests[0].get("type"), href.split("/").pop());
    await act(page, seen, () => page.locator("#auctionLists .listLayout_detailed").click());
    await act(page, seen, () => page.locator("#auctionLists th.itemInformation").click(), 300);
    requests = await act(page, seen, () => page.locator("#auctionLists .contextMenu a.currentBid").click());
    assert.equal(requests[0].get("sortBy"), "CURRENT_BID");

    // ---- sell ----
    await open(page, seen, "/auctions/sell");
    assert.equal(await rowCount(page, ".ongoingSellListWrapper tbody tr"), 3, "three auctions until show more");
    requests = await act(page, seen, () => page.locator("[data-auction-more='sell']").click());
    assert.equal(requests[0].get("showMore"), "1");
    assert.equal(await rowCount(page, ".ongoingSellListWrapper tbody tr"), 5);
    assert.equal(await page.locator("[data-auction-more='sell']").textContent(), "Show less");
    requests = await act(page, seen, () => page.locator(".ongoingSellListWrapper th.startedAt").click());
    assert.equal(requests[0].get("sortBy"), "STARTED_AT");
    await act(page, seen, () => page.locator("#auctionLists button.reload").click());
    const inventoryNode = await page.locator("#itemsToSale").elementHandle();
    await act(page, seen, () => page.locator(".listLayout_detailed").click());
    assert.ok(await inventoryNode.evaluate((node) => node.isConnected), "the sell inventory is the same node after a refresh");
    // the inventory filter stays client side
    requests = await act(page, seen, () => page.locator(".sellItems .filterSlot.helmet").click(), 400);
    assert.equal(requests.length, 0, "the sell inventory filter asks the server for nothing");
    assert.ok(await page.locator("#itemsToSale.filter_helmet").count());
    await page.reload({ waitUntil: "networkidle" });
    assert.equal(await rowCount(page, ".ongoingSellListWrapper tbody tr"), 5, "show more survives a reload");
    await act(page, seen, () => page.locator("[data-auction-more='sell']").click());
    await act(page, seen, () => page.locator(".listLayout_compact").click());

    // ---- overview ----
    await open(page, seen, "/auctions/overview");
    const bidRows = () => rowCount(page, ".bidListWrapper tbody tr");
    const soldRows = () => rowCount(page, ".soldListWrapper tbody tr");
    assert.deepEqual([await bidRows(), await soldRows()], [3, 3], "three rows each until show more");
    requests = await act(page, seen, () => page.locator("[data-auction-more='bids']").click());
    assert.equal(requests[0].get("bidsShowMore"), "1");
    assert.equal(await bidRows(), 10);
    assert.equal(await soldRows(), 3, "the sold list is independent");
    requests = await act(page, seen, () => page.locator(".bidListWrapper ~ .pagination .forward").click());
    assert.equal(requests[0].get("bidsPage"), "2");
    assert.equal(await bidRows(), 4);
    requests = await act(page, seen, () => page.locator(".bidListWrapper th.bidCount").click());
    assert.equal(requests[0].get("bidsSortBy"), "BIDS_COUNT");
    assert.equal(requests[0].get("bidsPage"), "1", "sorting starts again at page 1");
    requests = await act(page, seen, () => page.locator("[data-auction-more='sold']").click());
    assert.equal(requests[0].get("soldShowMore"), "1");
    requests = await act(page, seen, () => page.locator(".soldListWrapper th.payout").click());
    assert.equal(requests[0].get("soldSortBy"), "SOLD_FOR");
    assert.match(await page.locator(".soldListWrapper td.finishedAt").first().innerText(), /^\d\d\.\d\d, \d\d:\d\d$/, "a sale ends on a date and time");
    await page.locator(".soldListWrapper td.payout.feeHint").first().hover();
    assert.match(await page.locator(".auctionFeePriceList").first().innerText(), /Sale price:[\s\S]*Auction fee:[\s\S]*\(\d+%\)/, "a refreshed payout still explains its fee on hover");
    requests = await act(page, seen, () => page.locator(".auctionsOverviewContainer").nth(0).locator(".listLayout_compact").click());
    assert.equal(requests[0].get("bidsLayout"), "compact");
    assert.equal(await page.locator(".bidListWrapper th.price").count(), 1, "the compact bids table adds Price");
    assert.equal(await page.locator(".bidListWrapper th.highestBidder").count(), 1, "and Highest bidder");
    assert.ok(await page.locator(".bidListWrapper td.price").count(), "its rows have the price cells");
    await act(page, seen, () => page.locator(".auctionsOverviewContainer").nth(0).locator(".listLayout_detailed").click());
    assert.equal(await page.locator(".bidListWrapper th.price").count(), 0);
    requests = await act(page, seen, () => page.locator(".auctionsOverviewContainer").nth(1).locator(".listLayout_compact").click());
    assert.equal(requests[0].get("soldLayout"), "compact");
    assert.equal(requests[0].get("bidsLayout"), "detailed", "the layouts are per list");
    requests = await act(page, seen, () => page.locator(".filterSlot.helmet").click());
    assert.equal(requests[0].get("filter"), "helmet");
    assert.equal(requests[0].get("bidsPage"), "1");
    await page.reload({ waitUntil: "networkidle" });
    assert.equal(await bidRows(), 10, "show more survives a reload");
    // put the preferences back
    await act(page, seen, () => page.locator("[data-auction-more='bids']").click());
    await act(page, seen, () => page.locator("[data-auction-more='sold']").click());
    await act(page, seen, () => page.locator(".auctionsOverviewContainer").nth(1).locator(".listLayout_detailed").click());

    await context.close();
    console.log("auction lists: every control refreshes in place");
  } finally {
    await browser.close();
    await fixture.stop();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
