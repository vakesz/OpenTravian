/*
 * The auction lists (buy, an item's auctions, sell, overview) update in place, like the reference's lists: a
 * filter slot, a sortable header or the item sorting menu, the grouped switch, a layout button, reload, the pager
 * and "show more" ask `GET auction/items` for the lists area with the new state and put the answer into
 * #auctionLists. The server renders the area (sorting, paging and layouts live in HeroAuctionCtrl); this script
 * only holds the state it last got back (#auctionListsState, then each answer's `state`), keeps the parts of the
 * page that must survive a refresh (the filter bar with its search and exchange office, the sell inventory), and
 * stores layout, grouped and "show more" as preferences like the reference does.
 *
 * Lists name themselves with data-auction-list: buy, detail, sell, bids, sold.
 */
(function () {
  "use strict";

  if (window.AuctionLists) {
    return;
  }

  // State key of each list's layout, page and show-more, and the preference that remembers the first and last.
  var LISTS = {
    buy: { layout: "layout", page: "page", layoutPref: "auctionsBuy_listLayout" },
    detail: { layout: "layout", page: "page", layoutPref: "auctionsBuyItemTypeDetail_listLayout", batchPref: "auctionsBuyItemTypeDetail_batchSize" },
    sell: { layout: "layout", more: "showMore", layoutPref: "ongoingSellList_listLayout", morePref: "auctionsSell_showMore" },
    bids: { layout: "bidsLayout", page: "bidsPage", more: "bidsShowMore", layoutPref: "auctionsOverviewBids_listLayout", morePref: "auctionsOverviewBids_showMore" },
    sold: { layout: "soldLayout", page: "soldPage", more: "soldShowMore", layoutPref: "auctionsOverviewSold_listLayout", morePref: "auctionsOverviewSold_showMore" },
  };

  var state = {};
  var request = 0;

  function region() {
    return document.getElementById("auctionLists");
  }

  function readState() {
    var element = document.getElementById("auctionListsState");
    try {
      state = JSON.parse(element ? element.textContent : "{}") || {};
    } catch (error) {
      state = {};
    }
  }

  function copy(source) {
    return Object.assign({}, source);
  }

  function preference(key, value) {
    if (window.Travian && Travian.Game && Travian.Game.Preferences) {
      Travian.Game.Preferences.set(key, value);
    }
  }

  // The element to focus again after the area is replaced, as a selector inside it.
  function signature(element) {
    if (!element || !region() || !region().contains(element)) {
      return null;
    }
    var control = element.closest("[data-auction-layout], [data-auction-batch], [data-auction-reload], [data-auction-page], [data-auction-more]");
    if (!control) {
      return null;
    }
    var attribute = function (name) {
      return control.hasAttribute(name) ? "[" + name + '="' + control.getAttribute(name) + '"]' : "";
    };
    if (control.hasAttribute("data-auction-more")) {
      return "a" + attribute("data-auction-more");
    }
    return "button" + attribute("data-auction-layout") + attribute("data-auction-batch") + attribute("data-auction-list") + attribute("data-auction-page")
      + (control.hasAttribute("data-auction-reload") ? "[data-auction-reload]" : "");
  }

  function runInlineScripts(container) {
    container.querySelectorAll("script").forEach(function (old) {
      if (old.src || old.hasAttribute("data-auction-template") || (old.type && !/javascript/i.test(old.type))) {
        return;
      }
      var script = document.createElement("script");
      script.text = old.text;
      old.replaceWith(script);
    });
  }

  function syncFilterBar(bar, next, wallet) {
    bar.querySelectorAll(".filterSlot").forEach(function (slot) {
      var active = slot.getAttribute("data-key") === (next.filter || "all");
      slot.classList.toggle("active", active);
      slot.setAttribute("aria-pressed", active ? "true" : "false");
    });
    var gold = bar.querySelector(".exchangeWidget > .exchangeGold span");
    var silver = bar.querySelector(".exchangeWidget > .exchangeSilver span");
    if (gold && wallet) {
      gold.textContent = wallet.goldText;
    }
    if (silver && wallet) {
      silver.textContent = wallet.silverText;
    }
  }

  function apply(answer, focus) {
    var area = region();
    if (!area || typeof answer.html !== "string") {
      return;
    }
    var holder = document.createElement("template");
    holder.innerHTML = answer.html;
    var fragment = holder.content;
    var next = answer.state || {};
    // The filter bar and the sell inventory keep their nodes (and the handlers on them); the new copies are dropped.
    fragment.querySelectorAll("[data-auction-keep]").forEach(function (fresh) {
      var name = fresh.getAttribute("data-auction-keep");
      var old = area.querySelector('[data-auction-keep="' + name + '"]');
      if (!old) {
        return;
      }
      if (name === "filter") {
        syncFilterBar(old, next, answer.wallet);
      }
      fresh.replaceWith(old);
    });
    // Scripts that already ran for this page must not run again.
    fragment.querySelectorAll("script[data-auction-once]").forEach(function (script) {
      script.remove();
    });
    area.replaceChildren(fragment);
    runInlineScripts(area);
    Travian.Tip.updateAllInElement(area);
    state = copy(next);
    initTables();
    if (focus) {
      var target = area.querySelector(focus);
      if (target) {
        target.focus();
      }
    }
    document.dispatchEvent(new CustomEvent("auction-lists:updated", { detail: { state: copy(state) } }));
  }

  function refresh(next, focus) {
    var id = ++request;
    var query = new URLSearchParams(next).toString();
    Travian.api(
      "auction/items?" + query,
      {
        success: function (answer) {
          if (id === request) {
            apply(answer, focus);
          }
        },
      },
      "get"
    );
  }

  // ---- sortable tables: header and item-menu sorting with the reference's rules ----

  function arrowClass(by, order, activeBy, defaultOrder) {
    var active = by === activeBy;
    return "sortingArrow" + (active ? "" : " inactive") + " " + ((active ? order === "ASC" : defaultOrder === "ASC") ? "up" : "down");
  }

  function SortableTable(table) {
    this.table = table;
    this.param = table.getAttribute("data-sort-param") || "";
    this.by = table.getAttribute("data-sort-by");
    this.order = table.getAttribute("data-sort-order") === "ASC" ? "ASC" : "DESC";
    this.openMenu = null;
    var self = this;
    table.querySelectorAll("thead th[data-sort-by], thead th[data-sort-menu]").forEach(function (header) {
      header.addEventListener("click", function (event) {
        self.onHeader(header, event);
      });
    });
  }

  SortableTable.prototype.change = function (by, defaultOrder) {
    var order = this.by !== by ? defaultOrder : this.order === "ASC" ? "DESC" : "ASC";
    var next = copy(state);
    var prefix = this.param;
    next[prefix === "" ? "sortBy" : prefix + "SortBy"] = by;
    next[prefix === "" ? "sortOrder" : prefix + "SortOrder"] = order;
    next[prefix === "" ? "page" : prefix + "Page"] = "1";
    refresh(next);
  };

  SortableTable.prototype.onHeader = function (header, event) {
    var menu = header.getAttribute("data-sort-menu");
    if (menu) {
      if (event.target.closest(".contextMenuWrapper")) {
        return;
      }
      this.toggleMenu(header, JSON.parse(menu));
      return;
    }
    this.change(header.getAttribute("data-sort-by"), header.getAttribute("data-sort-default") === "ASC" ? "ASC" : "DESC");
  };

  SortableTable.prototype.closeMenu = function () {
    if (this.openMenu) {
      this.openMenu.remove();
      this.openMenu = null;
    }
  };

  SortableTable.prototype.toggleMenu = function (header, entries) {
    var self = this;
    var wasOpen = this.openMenu !== null;
    this.closeMenu();
    if (wasOpen) {
      return;
    }
    var wrapper = document.createElement("div");
    wrapper.className = "contextMenuWrapper";
    var menu = document.createElement("div");
    menu.className = "contextMenu from sorting";
    var anchor = header.querySelector(".sortingContextWrapper").getBoundingClientRect();
    menu.style.left = anchor.left + "px";
    menu.style.top = anchor.bottom + "px";
    entries.forEach(function (entry) {
      var link = document.createElement("a");
      link.className = entry.cls;
      link.innerHTML = '<i class=""></i><span></span><svg viewBox="0 0 10 5"><path d="M0 5l5-5 5 5z"></path></svg>';
      link.querySelector("span").textContent = entry.text;
      link.querySelector("svg").setAttribute("class", arrowClass(entry.by, self.order, self.by, entry.default));
      link.addEventListener("click", function (event) {
        event.stopPropagation();
        self.closeMenu();
        self.change(entry.by, entry.default);
      });
      menu.appendChild(link);
    });
    wrapper.appendChild(menu);
    header.querySelector(".sortingContextWrapper").appendChild(wrapper);
    this.openMenu = wrapper;
    var close = function (event) {
      if (!wrapper.contains(event.target)) {
        self.closeMenu();
        document.removeEventListener("click", close, true);
      }
    };
    window.setTimeout(function () {
      document.addEventListener("click", close, true);
    }, 0);
  };

  function initTables() {
    document.querySelectorAll("#auctionLists table.itemType[data-sort-by]").forEach(function (table) {
      new SortableTable(table);
    });
  }

  // ---- the other controls, by delegation (the area's nodes are replaced on every refresh) ----

  function onFilter(slot) {
    if (state.view === "sell") {
      return; // the sell inventory filters its tiles in the page, as in the reference
    }
    var bar = slot.closest("[data-auction-keep]") || slot.parentNode;
    var next = copy(state);
    next.filter = slot.getAttribute("data-key") || "all";
    ["page", "bidsPage", "soldPage"].forEach(function (key) {
      if (key in next) {
        next[key] = "1";
      }
    });
    syncFilterBar(bar, next, null);
    refresh(next);
  }

  function onLayout(button) {
    var list = LISTS[button.getAttribute("data-auction-list")];
    var layout = button.getAttribute("data-auction-layout");
    if (!list || state[list.layout] === layout) {
      return;
    }
    var next = copy(state);
    next[list.layout] = layout;
    if (list.page) {
      next[list.page] = "1";
    }
    preference(list.layoutPref, layout);
    refresh(next, signature(button));
  }

  function onBatch(button) {
    var size = button.getAttribute("data-auction-batch");
    if ((state.batchSize || "") === size) {
      return;
    }
    var next = copy(state);
    next.batchSize = size;
    next.page = "1";
    preference(LISTS.detail.batchPref, size === "" ? null : size);
    refresh(next, signature(button));
  }

  function onGrouped(input) {
    var next = copy(state);
    next.grouped = input.checked ? "1" : "0";
    next.page = "1";
    delete next.sortBy;
    delete next.sortOrder;
    preference("auctionsBuy_viewGroupedByItemType", input.checked);
    refresh(next);
  }

  function onPage(button) {
    var list = LISTS[button.getAttribute("data-auction-list")];
    if (!list || !list.page || button.disabled) {
      return;
    }
    var next = copy(state);
    next[list.page] = button.getAttribute("data-auction-page");
    refresh(next);
  }

  function onMore(link) {
    var list = LISTS[link.getAttribute("data-auction-more")];
    if (!list || !list.more) {
      return;
    }
    var open = state[list.more] !== "1";
    var next = copy(state);
    next[list.more] = open ? "1" : "0";
    if (list.page) {
      next[list.page] = "1";
    }
    preference(list.morePref, open);
    refresh(next);
  }

  function onClick(event) {
    if (!region() || !region().contains(event.target)) {
      return;
    }
    var control = event.target.closest(".filterSlot, [data-auction-layout], [data-auction-batch], [data-auction-reload], [data-auction-page], [data-auction-more]");
    if (!control) {
      return;
    }
    if (control.matches(".filterSlot")) {
      onFilter(control);
    } else if (control.hasAttribute("data-auction-layout")) {
      onLayout(control);
    } else if (control.hasAttribute("data-auction-batch")) {
      onBatch(control);
    } else if (control.hasAttribute("data-auction-reload")) {
      refresh(copy(state), signature(control));
    } else if (control.hasAttribute("data-auction-page")) {
      onPage(control);
    } else {
      onMore(control);
    }
  }

  function init() {
    readState();
    initTables();
    document.addEventListener("click", onClick);
    document.addEventListener("change", function (event) {
      if (event.target.matches && event.target.matches("#auctionLists [data-auction-grouped]")) {
        onGrouped(event.target);
      }
    });
    document.addEventListener("keydown", function (event) {
      var slot = event.target.closest && event.target.closest("#auctionLists .filterSlot");
      if (slot && (event.key === "Enter" || event.key === " ")) {
        event.preventDefault();
        slot.click();
      }
    });
  }

  window.AuctionLists = { refresh: refresh, state: function () { return copy(state); } };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
