/*
 * The Farm List tab of the rally point. The server embeds the whole read model (Game\FarmList\FarmListData) as
 * `viewData`; this script draws the page from it with the DOM the reference's React app renders, keeps the
 * selection, the expanded state and the open menus and dialogs, and sends every change to the farm-list API.
 * A response carries the lists that changed, so the server stays the only place that sorts and counts.
 *
 * Travian.Game.FarmList.render(config) starts the page. The pure helpers sit on Travian.Game.FarmList.Model so
 * they can be tested without a browser.
 */
(function () {
  "use strict";

  var LRO = "\u202D";
  var RLO = "\u202E";
  var PDF = "\u202C";
  var UNITS = ["t1", "t2", "t3", "t4", "t5", "t6", "t7", "t8", "t9", "t10"];
  var REPORT_ICONS = {
    1: "attack_won_withoutLosses_small",
    2: "attack_won_withLosses_small",
    3: "attack_lost_small",
    15: "spy_small",
    16: "spy_small",
    17: "spy_small",
  };
  var HEADER_SORTS = [
    { cls: "target", field: "village", label: "village", icon: "village_medium" },
    { cls: "population", field: "ew", label: "population", icon: "population_medium" },
    { cls: "distance", field: "distance", label: "distance", icon: "distance_medium" },
    { cls: "troops", field: "troops", label: "troops", icon: "troops_medium" },
  ];
  /* [field, class, icon, default direction, arrow points up when the sort is ascending] */
  var SORT_MENU = [
    { field: "combatResult", icon: { cls: "attack_lost_small" }, direction: "asc" },
    { field: "lastRaid", icon: { cls: "clock_small" }, direction: "asc" },
    { field: "bounty", icon: { cls: "bounty_full_small" }, direction: "desc" },
    { field: "totalBounty", icon: { glyph: "average" }, direction: "desc" },
    { field: "nextAttack", icon: { cls: "nextAttack_small" }, direction: "asc" },
  ];

  function esc(value) {
    return String(value).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function pad(value, length) {
    var text = String(value);
    while (text.length < (length || 2)) text = "0" + text;
    return text;
  }

  /** The pure part: no DOM, no network. */
  var Model = {
    /** Direction after a click on a sort control: the active field flips, any other starts with `fallback`. */
    nextSort: function (list, field, fallback) {
      if (list.sortField === field) return list.sortDirection === "asc" ? "desc" : "asc";
      return fallback;
    },

    /** Troops of the selected slots summed per unit key (t1..t10). */
    summedTroops: function (slots, selected) {
      var sum = {};
      slots.forEach(function (slot) {
        if (selected.indexOf(slot.id) < 0) return;
        UNITS.forEach(function (unit) {
          if (slot.troop[unit] > 0) sum[unit] = (sum[unit] || 0) + slot.troop[unit];
        });
      });
      return sum;
    },

    /** The start button of a list: whether it is disabled, its title and the count in its label. */
    startState: function (own, village, activeAmount, selectedAmount, labels) {
      var count = selectedAmount || activeAmount;
      var locked = own.banInfo && own.banInfo.lockStatus && own.banInfo.lockStatus !== "NOT_LOCKED";
      var result;
      if (locked) result = [true, labels.avatar_locked];
      else if (!own.accessRights.sendRaids) result = [true, labels.sitter_not_allowed_only_with_right];
      else if (own.isInVacationMode) result = [true, labels.sendingNotAllowedOnVacationMode];
      else if (!village.hasRallyPoint) result = [true, labels.noRallyPoint.replace("[NAME]", village.name)];
      else if (activeAmount === 0) result = [true, selectedAmount > 0 ? labels.noActiveSelected : labels.hasNoActive];
      else result = [false, selectedAmount > 0 ? labels.startSelectedActive : labels.startAllActive];
      return { disabled: result[0], title: result[1], count: count };
    },

    /** The bounty icon: nothing, a part of the carry capacity, or all of it. */
    bountyIcon: function (lastRaid) {
      var total = Model.booty(lastRaid);
      return "bounty_" + (total === 0 ? "empty" : total === lastRaid.bootyMax ? "full" : "half") + "_small";
    },

    booty: function (lastRaid) {
      var r = lastRaid.raidedResources;
      return r.lumber + r.clay + r.iron + r.crop;
    },

    /** Substitutes [KEY] placeholders. */
    fill: function (text, values) {
      return text.replace(/\[([A-Z_]+)\]/g, function (match, key) {
        return key in values ? values[key] : match;
      });
    },

    /**
     * A time as the reference formats it: `format` is dateTime, date, time, timeWithoutSeconds or dynamicDateTime
     * (a time today, "yesterday", a date before, a date and time ahead); `style` is the player's time format 0..4.
     */
    formatTime: function (timestamp, format, options) {
      var zone = options.timeZone;
      var parts = Model.zoned(timestamp, zone);
      var today = Model.zoned(options.now, zone);
      var style = options.timeFormat;
      var dayDiff = Math.round((Date.UTC(parts.y, parts.M - 1, parts.d) - Date.UTC(today.y, today.M - 1, today.d)) / 864e5);
      var asText = false;
      if (format === "dynamicDateTime") {
        if (dayDiff > 0) format = "dateTime";
        else if (dayDiff === 0) format = "time";
        else if (dayDiff === -1) {
          asText = true;
          format = "date";
        } else format = "date";
      }
      if (asText && format === "date" && Math.abs(dayDiff) <= 2) return options.labels["day-1"] || "";
      var twelve = style === 1 || style === 2;
      var clock = function (seconds) {
        var hour = twelve ? parts.H % 12 || 12 : parts.H;
        return pad(hour) + ":" + pad(parts.m) + (seconds ? ":" + pad(parts.s) : "") + (twelve ? " " + (parts.H < 12 ? "AM" : "PM") : "");
      };
      var date = [
        parts.d + "." + parts.M + "." + (style === 4 ? parts.y : pad(parts.y % 100)),
        parts.M + "/" + parts.d + "/" + pad(parts.y % 100),
        parts.d + "/" + parts.M + "/" + pad(parts.y % 100),
        pad(parts.y % 100) + "/" + parts.M + "/" + parts.d,
        parts.d + "." + parts.M + "." + parts.y,
      ][style] || parts.d + "." + parts.M + "." + pad(parts.y % 100);
      if (format === "time") return clock(true);
      if (format === "timeWithoutSeconds") return clock(false);
      if (format === "date") return date;
      return date + ", " + clock(false);
    },

    /** Year, month, day, hour, minute and second of a timestamp in an IANA time zone. */
    zoned: function (timestamp, zone) {
      var out = {};
      new Intl.DateTimeFormat("en-GB", {
        timeZone: zone, hourCycle: "h23", year: "numeric", month: "numeric", day: "numeric", hour: "numeric", minute: "numeric", second: "numeric",
      }).formatToParts(new Date(timestamp * 1000)).forEach(function (part) {
        var key = { year: "y", month: "M", day: "d", hour: "H", minute: "m", second: "s" }[part.type];
        if (key) out[key] = parseInt(part.value, 10);
      });
      return out;
    },
  };

  /* ------------------------------------------------------------------ dialogs */

  /**
   * The reference's dialog (Travian.React.Dialog): an overlay with a close button in #reactDialogWrapper.
   * Returns { close, node, content }.
   */
  function openDialog(page, options) {
    var wrapper = document.getElementById("reactDialogWrapper") || document.body;
    var overlay = document.createElement("div");
    overlay.id = "dialogOverlay";
    overlay.className = "dialogOverlay" + (options.enableBackground === false ? "" : " enabled");
    overlay.innerHTML =
      '<div class="dialogWrapper dialogV2" data-context=""><div class="dialog ' + esc(options.cssClass) + '"><div class="dialogContainer"><div class="dialogContents">' +
      '<form action="?" method="get" accept-charset="UTF-8"><div class="dialogDragBar"></div>' +
      (options.buttonCancel === false ? "" : '<div class="dialogCancelButton buttonFramed green withIcon rectangle cancel">' + page.layered("close") + "</div>") +
      '<div class="content" id="dialogContent"></div></form></div></div></div></div>';
    var content = overlay.querySelector("#dialogContent");
    var dialog = { node: overlay, content: content, closed: false };
    var down = false;
    dialog.close = function () {
      if (dialog.closed) return;
      dialog.closed = true;
      document.removeEventListener("keyup", onKey);
      overlay.classList.remove("dialogVisible");
      setTimeout(function () {
        overlay.remove();
        if (options.onClose) options.onClose();
      }, 400);
    };
    function onKey(event) {
      if (event.key === "Escape" && Array.prototype.slice.call(document.querySelectorAll("#dialogOverlay")).pop() === overlay) dialog.close();
    }
    var cancel = overlay.querySelector(".dialogCancelButton");
    if (cancel) cancel.addEventListener("click", dialog.close);
    overlay.addEventListener("mousedown", function (event) {
      down = event.target === overlay;
    });
    overlay.addEventListener("click", function () {
      if (down) dialog.close();
    });
    overlay.querySelector("form").addEventListener("submit", function (event) {
      event.preventDefault();
    });
    document.addEventListener("keyup", onKey);
    wrapper.appendChild(overlay);
    setTimeout(function () {
      overlay.classList.add("dialogVisible");
    }, 0);
    return dialog;
  }

  /* --------------------------------------------------------------------- page */

  function FarmListPage(config) {
    this.config = config;
    this.labels = config.labels;
    this.own = config.viewData.ownPlayer;
    this.serverTime = config.viewData.bootstrapData.timestamp;
    this.loadedAt = Date.now();
    this.selected = {};
    this.nodes = {};
    this.menu = null;
    this.root = document.getElementById("rallyPointFarmList");
    this.sticky = document.getElementById("stickyPin");
    this.tipTimer = null;
  }

  FarmListPage.prototype.L = function (key, values) {
    var text = this.labels[key] === undefined ? key : this.labels[key];
    return values ? Model.fill(text, values) : text;
  };

  FarmListPage.prototype.glyph = function (name, cls) {
    return (this.config.glyphs[name] || "").replace("<svg", '<svg' + (cls ? ' class="' + cls + '"' : ""));
  };

  /** An icon drawn twice for the framed buttons (outline and icon). */
  FarmListPage.prototype.layered = function (name) {
    return "<svg>" + this.glyph(name, "outline") + this.glyph(name, "icon") + "</svg>";
  };

  /** The same with each layer wrapping the icon, as the context menu entries draw it. */
  FarmListPage.prototype.layeredNested = function (name) {
    return '<svg><svg class="outline">' + this.glyph(name, "") + '</svg><svg class="icon">' + this.glyph(name, "") + "</svg></svg>";
  };

  /** Numerals the way the reference's number component writes them: isolated left to right, a minus sign outside. */
  FarmListPage.prototype.number = function (value, grouping, fraction) {
    var language = this.config.language;
    var options = { useGrouping: !!grouping };
    if (fraction !== undefined) {
      options.minimumFractionDigits = fraction;
      options.maximumFractionDigits = fraction;
    }
    var locale = language === "ar-AE" || language === "he-IL" ? "de-DE" : language || "de-DE";
    var text = LRO + Math.abs(Number(value)).toLocaleString(locale, options) + PDF;
    if (Number(value) < 0) text = (language === "ar-AE" ? RLO : LRO) + "\u2212" + text + PDF;
    return text;
  };

  /** A ratio: numerals isolated, the slash mirrored for Arabic, in spans when `wrap` is set. */
  FarmListPage.prototype.ratio = function (nominator, denominator, wrap) {
    var rtl = this.config.language === "ar-AE";
    var n = this.number(nominator);
    var d = this.number(denominator);
    if (wrap) {
      n = '<span class="nominator">' + n + "</span>";
      d = '<span class="denominator">' + d + "</span>";
    }
    return (rtl ? RLO : LRO) + n + (rtl ? "\\" : "/") + d + PDF;
  };

  FarmListPage.prototype.coordinates = function (x, y, aligned) {
    var rtl = this.config.language === "ar-AE";
    return (
      (rtl ? RLO : LRO) + '<span class="coordinates coordinatesWrapper' + (aligned ? " coordinatesAligned coordinates" + (rtl ? "rtl" : "ltr") : "") + '"><span class="coordinateX">(' + this.number(x) +
      '</span><span class="coordinatePipe">|</span><span class="coordinateY">' + this.number(y) + ")</span></span>" + PDF
    );
  };

  FarmListPage.prototype.time = function (timestamp, format) {
    return Model.formatTime(timestamp, format, {
      timeZone: this.config.timeZone, timeFormat: this.config.timeFormat, now: this.serverNow(), labels: this.labels,
    });
  };

  FarmListPage.prototype.serverNow = function () {
    return this.serverTime + Math.floor((Date.now() - this.loadedAt) / 1000);
  };

  /** A framed button the way the reference's button component draws it. */
  FarmListPage.prototype.button = function (options) {
    var cls = "textButtonV2 buttonFramed" + (options.cls ? " " + options.cls : "") + (options.disabled ? " disabled" : "") + (options.withTextAndIcon ? " withTextAndIcon" : "") +
      " rectangle" + (options.icon ? " withIcon" : " withText") + " " + (options.appearance || "green");
    return (
      "<button" + (options.type === "submit" ? "" : "") + ' class="' + cls + '" type="' + (options.type || "button") + '"' + (options.title !== undefined ? ' title="' + esc(options.title) + '"' : "") +
      ">" + (options.icon ? options.html : "<div>" + options.html + "</div>") + "</button>"
    );
  };

  FarmListPage.prototype.unitTitle = function (unit) {
    return this.config.unitTitles[parseInt(unit.slice(1), 10)];
  };

  FarmListPage.prototype.villageOf = function (list) {
    return this.own.villages.filter(function (village) {
      return village.id === list.ownerVillage.id;
    })[0];
  };

  FarmListPage.prototype.selection = function (list) {
    return this.selected[list.id] || (this.selected[list.id] = []);
  };

  FarmListPage.prototype.refreshTips = function () {
    clearTimeout(this.tipTimer);
    this.tipTimer = setTimeout(function () {
      if (window.Travian && Travian.Tip) Travian.Tip.refresh();
      if (window.Travian && Travian.TimersAndCounters) Travian.TimersAndCounters.initTimers();
    }, 250);
  };

  /* ---------------------------------------------------------------- drawing */

  FarmListPage.prototype.render = function () {
    this.redrawAll();
    this.drawSticky();
    var self = this;
    this.bindDrag();
    document.addEventListener("timerEnd", function (event) {
      var arrival = /^timer_(\d+)_\d+_\d+$/.exec((event.target && event.target.id) || "");
      if (arrival) self.reload(parseInt(arrival[1], 10));
    });
    document.addEventListener("click", function (event) {
      if (self.menu && !event.target.closest(".contextMenuWrapper") && !event.target.closest(".sorting, .openContextMenu")) self.closeMenu();
    });
  };

  /** Draws the notices, the villages and the abandoned lists in front of the sticky bar. */
  FarmListPage.prototype.redrawAll = function () {
    var self = this;
    Array.prototype.slice.call(this.root.children).forEach(function (node) {
      if (node !== self.sticky && node.tagName !== "SCRIPT") node.remove();
    });
    this.nodes = {};
    var html = "";
    if (this.own.beginnersProtection) html += '<div class="noticeBox beginnersProtection">' + esc(this.L("beginnersProtectionDescriptionShort")) + "</div>";
    var targets = this.own.deactivatedFarmListTargets;
    if (targets.length > 0) {
      html +=
        '<div class="noticeBox deactivatedTargets">' + this.glyph("close", "close") + '<div class="description">' + esc(this.L("deactivatedTargets")) + "</div>" +
        targets.map(function (target) { return self.targetHtml(target); }).join(", ") + "</div>";
    }
    var holder = document.createElement("div");
    holder.innerHTML = html;
    while (holder.firstChild) this.root.insertBefore(holder.firstChild, this.sticky);
    var close = this.root.querySelector(".deactivatedTargets .close");
    if (close) {
      close.addEventListener("click", function () {
        self.api("farm-list/close-inbox", "POST", undefined, function () {
          self.own.deactivatedFarmListTargets = [];
          self.redrawAll();
        });
      });
    }
    this.own.villages.forEach(function (village) {
      if (self.listsOf(village.id).length > 0) self.drawVillage(village.id);
    });
    this.drawAbandoned();
  };

  /** The block of lists whose village the player lost, with a link that shows more of them. */
  FarmListPage.prototype.drawAbandoned = function () {
    var self = this;
    var lists = this.own.abandonedFarmLists;
    var node = this.root.querySelector(".abandonedFarmLists");
    if (node) node.remove();
    if (lists.length === 0) return;
    this.abandonedLimit = this.abandonedLimit || 100;
    node = document.createElement("div");
    node.className = "villageWrapper abandonedFarmLists";
    node.innerHTML = '<div class="villageHeader ' + (lists.some(function (l) { return l.isExpanded; }) ? "expanded" : "collapsed") + '"><div class="villageName">' + esc(this.L("abandonedFarmLists")) + "</div></div>";
    this.root.insertBefore(node, this.sticky);
    lists.slice(0, this.abandonedLimit).forEach(function (list) {
      var holder = document.createElement("div");
      holder.innerHTML = self.listHtml(list, true);
      node.appendChild(holder.firstChild);
      self.nodes[list.id] = node.lastChild;
      self.bindList(node.lastChild, list, true);
    });
    if (lists.length > this.abandonedLimit) {
      var more = document.createElement("a");
      more.className = "showMore";
      more.textContent = this.L("showMore");
      more.addEventListener("click", function () {
        self.abandonedLimit += 100;
        self.drawAbandoned();
      });
      node.appendChild(more);
    }
    this.refreshTips();
  };

  FarmListPage.prototype.drawAbandonedHeader = function () {
    var header = this.root.querySelector(".abandonedFarmLists .villageHeader");
    var expanded = this.own.abandonedFarmLists.some(function (list) { return list.isExpanded; });
    header.classList.toggle("expanded", expanded);
    header.classList.toggle("collapsed", !expanded);
  };

  FarmListPage.prototype.listsOf = function (kid) {
    return this.own.farmLists.filter(function (list) {
      return list.ownerVillage.id === kid;
    }).sort(function (a, b) {
      return a.sortIndex - b.sortIndex;
    });
  };

  FarmListPage.prototype.villageNode = function (kid) {
    return this.root.querySelector('.villageWrapper[data-did="' + kid + '"]');
  };

  /** The wrapper of a village, created in the villages' order when its first list appears. */
  FarmListPage.prototype.villageNodeFor = function (kid) {
    var node = this.villageNode(kid);
    if (node) return node;
    node = document.createElement("div");
    node.className = "villageWrapper";
    node.setAttribute("data-did", kid);
    var order = this.own.villages.map(function (village) { return village.id; });
    var next = null;
    for (var i = order.indexOf(kid) + 1; i < order.length && !next; i++) next = this.villageNode(order[i]);
    this.root.insertBefore(node, next || this.root.querySelector(".abandonedFarmLists") || this.sticky);
    return node;
  };

  FarmListPage.prototype.drawVillage = function (kid) {
    var self = this;
    var lists = this.listsOf(kid);
    var node = this.villageNode(kid);
    if (lists.length === 0) {
      if (node) node.remove();
      return;
    }
    node = this.villageNodeFor(kid);
    var village = this.own.villages.filter(function (v) { return v.id === kid; })[0];
    var anyExpanded = lists.some(function (list) { return list.isExpanded; });
    node.innerHTML =
      '<div class="villageHeader ' + (anyExpanded ? "expanded" : "collapsed") + '"><div class="villageName">' + esc(village.name) + "</div>" +
      '<a class="expandCollapse">' + this.glyph("chevron", "") + "</a></div>";
    node.querySelector(".expandCollapse").addEventListener("click", function () {
      if (anyExpanded) self.setExpanded(lists, false);
    });
    lists.forEach(function (list) {
      var holder = document.createElement("div");
      holder.innerHTML = self.listHtml(list, false);
      var drop = holder.firstChild;
      node.appendChild(drop);
      self.nodes[list.id] = drop;
      self.bindList(drop, list, false);
    });
    this.refreshTips();
  };

  FarmListPage.prototype.listHtml = function (list, abandoned) {
    var selected = this.selection(list);
    var village = this.villageOf(list);
    var slots = list.isExpanded ? list.slots : [];
    var activeIds = list.slotsStates.filter(function (s) { return s.isActive; }).map(function (s) { return s.id; });
    var activeAmount = list.isExpanded ? activeIds.length : activeIds.length;
    var start = abandoned ? "" : this.startButton(list, village, activeAmount, selected.length);
    var results = (this.startResults && this.startResults[list.id]) || null;
    var status = '<i class="attack_small"></i>';
    if (results && results.success > 0) {
      status += this.glyph("success", "success").replace("<svg", '<svg title="' + esc(this.L("startSuccess") + ":&nbsp;" + results.success) + '"');
    }
    if (results && Object.keys(results.errors).length > 0) {
      var errors = "";
      Object.keys(results.errors).forEach(function (key) {
        errors += "<br>" + results.errors[key].count + " <span>" + results.errors[key].message + "</span>";
      });
      status += this.glyph("error", "error").replace("<svg", '<svg title="' + esc(this.L("startWarning") + ":" + errors) + '"');
    }
    status += "<span>" + (abandoned ? this.number(list.slotsAmount) : this.ratio(list.runningRaidsAmount, list.slotsAmount) + " " + esc(this.L("beingRaided"))) + "</span>";
    return (
      '<div class="dropContainer" data-sortindex="' + list.sortIndex + '"><div class="farmListWrapper ' + (list.isExpanded ? "expanded" : "collapsed") + '">' +
      '<div class="farmListHeader"><div class="dragAndDrop preventMobileSwipeNavigation" data-list="' + list.id + '">' + this.glyph("drag", "") + "</div>" +
      '<div class="farmListName"><div class="name">' + esc(list.name) + '</div><div class="editFarmListName">' + this.glyph("edit", "") + "</div></div>" +
      '<div class="farmListStatus">' + status + "</div>" + start + '<a class="expandCollapse">' + this.glyph("chevron", "") + "</a></div>" +
      '<div class="slotsWrapper formV2">' + this.tableHtml(list, slots, abandoned) + (slots.length > 0 && list.slotsAmount > 0 ? this.footerHtml(list, village, abandoned, activeAmount) : "") + "</div></div></div>"
    );
  };

  /** The start button of a list (and of its footer). */
  FarmListPage.prototype.startButton = function (list, village, activeAmount, selectedAmount) {
    var state = Model.startState(this.own, village, activeAmount, selectedAmount, this.labels);
    var title = '<div class="text">' + esc(state.title) + (list.lastStartedTime ? "<br>" + esc(this.L("lastStartOfFarmList")) + ": " + esc(this.time(list.lastStartedTime, "dateTime")) : "") + "</div>";
    return this.button({
      cls: "startFarmList", disabled: state.disabled || this.startButtonsDisabled, title: title,
      html: esc(this.L("startRaidList")) + (state.count > 0 ? " (" + state.count + ")" : ""),
    });
  };

  FarmListPage.prototype.sortArrow = function (list, field, defaultsDown) {
    var active = list.sortField === field;
    var up = defaultsDown ? active && list.sortDirection === "asc" : active ? list.sortDirection === "asc" : true;
    return this.glyph("arrow", "sortingArrow" + (active ? "" : " inactive") + " " + (up ? "up" : "down"));
  };

  FarmListPage.prototype.checkbox = function (attributes, checked) {
    return '<label class="checkbox"><input type="checkbox"' + (checked ? " checked" : "") + " " + attributes + ">" + this.glyph("checkbox", "checkbox") + "</label>";
  };

  FarmListPage.prototype.tableHtml = function (list, slots, abandoned) {
    var self = this;
    var selected = this.selection(list);
    var all = selected.length === list.slotsAmount && list.slotsAmount > 0;
    var head = '<thead class="includedHeader"><tr><th class="selection">' + this.checkbox('data-check-all="true" name="selectAll"', all) + '</th><th class="state"></th>';
    HEADER_SORTS.forEach(function (column) {
      head += '<th class="' + column.cls + ' sortable" data-field="' + column.field + '" title="' + esc(self.L(column.label)) + '"><div><i class="' + column.icon + '"></i>' + self.sortArrow(list, column.field) + "</div></th>";
    });
    head += '<th class="lastRaid"><div class="headWrapper"><span>' + esc(this.L("lastRaid")) + '</span><a class="sorting">' + this.glyph("sort", "") + "</a></div></th><th class=\"contextMenu\"></th></tr></thead>";
    var body = "<tbody>";
    slots.forEach(function (slot) {
      body += self.slotHtml(list, slot, abandoned);
    });
    var limit = this.config.limits.slots;
    body +=
      '<tr class="' + (list.slotsAmount === 0 ? "noGapTop" : "") + '"><td colspan="8" class="addTarget">' +
      (abandoned ? "" : '<a class="' + (list.slotsAmount >= limit ? "disabled" : "") + '">' + esc(this.L("addTarget")) + " <svg>" + this.glyph("plus", "outline") + this.glyph("plus", "icon") + "</svg></a>") +
      this.ratio(list.slotsAmount, limit) + "</td></tr></tbody>";
    var foot = '<tfoot><tr><td class="selection">' + this.checkbox('data-check-all="true" name="selectAll"', all) + '</td><td colspan="7">' + this.summaryHtml(list, abandoned) + "</td></tr></tfoot>";
    return '<table class="slots borderGap ' + (list.slotsAmount === 0 ? "noSlots" : "") + '">' + head + body + foot + "</table>";
  };

  FarmListPage.prototype.targetHtml = function (target) {
    var types = this.config.types;
    var isVillage = target.type === types.village || target.type === types.capital;
    return (
      '<a href="/karte.php?x=' + target.x + "&amp;y=" + target.y + '">' +
      (isVillage ? "<span>" + esc(target.name) + "</span>" : esc(this.L(target.type === types.oasisFree ? "unoccupiedOasis" : "occupiedOasis")) + " " + this.coordinates(target.x, target.y)) + "</a>"
    );
  };

  FarmListPage.prototype.slotHtml = function (list, slot, abandoned) {
    var config = this.config;
    var troops = "";
    UNITS.forEach(function (unit) {
      if (slot.troop[unit] > 0) {
        troops += '<span><i class="unit_small tribe' + config.race + " " + unit + '" title="' + esc(this.unitTitle(unit)) + '"></i><span class="value">' + slot.troop[unit] + "</span></span>";
      }
    }, this);
    var state = "";
    if (slot.isRunning && slot.nextAttackAt) {
      var tip =
        esc(this.L("attacksOnTheWay")) + ": " + slot.runningAttacks + "<br>" + esc(this.L("nextArrivalIn")) + ': <span class="timer" counting="down" value="' +
        Math.max(0, slot.nextAttackAt - this.serverNow()) + '" id="timer_' + list.id + "_" + slot.id + "_" + slot.nextAttackAt + '"></span>';
      state += '<i class="' + (slot.isSpying ? "spy_small" : "attack_small") + '" title="' + esc(tip) + '"></i>';
    }
    var error = this.startErrors && this.startErrors[slot.id];
    if (error) state += this.glyph("error", "error").replace("<svg", '<svg title="' + esc(error) + '"');
    var highlighted = this.menu && this.menu.slot === slot.id ? " highlighted" : "";
    var selected = this.selection(list).indexOf(slot.id) >= 0;
    var raid = slot.lastRaid;
    return (
      '<tr class="slot' + highlighted + (slot.isActive ? "" : " disabled") + '" data-slot="' + slot.id + '">' +
      '<td class="selection">' + this.checkbox('data-slot-id="' + slot.id + '" name="selectOne"', selected) + "</td>" +
      '<td class="state">' + state + "</td>" +
      '<td class="target">' + this.targetHtml(slot.target) + "</td>" +
      '<td class="population"><span>' + (slot.target.population === null ? "" : slot.target.population) + "</span></td>" +
      '<td class="distance"><span>' + (abandoned ? "-" : Math.round(10 * (slot.distance + Number.EPSILON)) / 10) + "</span></td>" +
      '<td class="troops"><div>' + troops + "</div></td>" +
      '<td class="lastRaid">' + (raid ? this.lastRaidHtml(slot, raid) : "") + "</td>" +
      '<td class="openContextMenu"><a>' + this.glyph("menu", "") + "</a></td></tr>"
    );
  };

  FarmListPage.prototype.lastRaidHtml = function (slot, raid) {
    var linked = raid.reportObjectId && raid.authKey;
    var html =
      '<div class="lastRaidWrapper"><a class="lastRaidReport ' + (linked ? "" : "disabled") + '"' + (linked ? ' href="/report?id=' + raid.reportObjectId + esc(raid.authKey) + '"' : "") + ">" +
      '<i class="lastRaidState ' + REPORT_ICONS[raid.icon] + '" title="' + esc(this.L("report_icon_" + raid.icon)) + '"></i>' +
      '<span class="value" title="' + esc(this.time(raid.time, "dateTime")) + '">' + esc(this.time(raid.time, "dynamicDateTime")) + "</span></a>";
    if (!slot.isSpying) {
      var r = raid.raidedResources;
      var tooltip =
        '<div class="farmListSlotBootyTooltip"><i class="lumber_tiny"></i><span class="value">' + this.number(r.lumber, true, 0) + '</span><i class="clay_tiny"></i><span class="value">' +
        this.number(r.clay, true, 0) + '</span><i class="iron_tiny"></i><span class="value">' + this.number(r.iron, true, 0) + '</span><i class="crop_tiny"></i><span class="value">' +
        this.number(r.crop, true, 0) + '</span><span class="maximumCapacity">' + esc(this.L("maximumCapacity")) + ": " + this.number(raid.bootyMax, true, 0) + "</span></div>";
      html +=
        '<div class="lastRaidBounty"><i class="' + Model.bountyIcon(raid) + '" title="' + esc(tooltip) + '"></i><span class="value">' + this.number(Model.booty(raid), true, 0) + "</span></div>" +
        '<div class="averageRaidBounty">' + this.glyph("average", "") + '<span class="value">' + (slot.totalBooty ? this.number(slot.totalBooty.booty, true, 0) : 0) + "</span></div>";
    }
    return html + "</div>";
  };

  FarmListPage.prototype.summaryHtml = function (list, abandoned) {
    var selected = this.selection(list);
    if (selected.length === 0) return esc(this.L("noTargetSelected"));
    var sum = Model.summedTroops(list.slots, selected);
    var home = !abandoned && list.ownerVillage.troops ? list.ownerVillage.troops.ownTroopsAtTown.units : {};
    var troops = "";
    var self = this;
    Object.keys(sum).forEach(function (unit) {
      var enough = (home[unit] || -1) >= sum[unit];
      troops +=
        '<span><i class="unit_small tribe' + self.config.race + " " + unit + '" title="' + esc(self.unitTitle(unit)) + '"></i>' +
        (abandoned ? '<span class="value">' + sum[unit] + "</span>" : '<span class="value ' + (enough ? "" : "notEnough") + '">' + self.ratio(sum[unit], home[unit] || 0) + "</span>") + "</span>";
    });
    return (
      '<div><span class="selected">' + esc(this.L("selected", { COUNT: selected.length })) + '</span><span class="equals">=</span><div class="summedTroops">' + troops + "</div></div>"
    );
  };

  FarmListPage.prototype.footerHtml = function (list, village, abandoned, activeAmount) {
    return (
      '<div class="farmListFooter">' + this.button({ cls: "openContextMenu", icon: true, html: this.layered("menu") }) +
      (abandoned ? "" : this.startButton(list, village, activeAmount, this.selection(list).length)) + "</div>"
    );
  };

  /** The sticky bar's counter, buttons and the start-all state follow the lists. */
  FarmListPage.prototype.drawSticky = function () {
    var self = this;
    if (!this.sticky) return;
    var lists = this.own.farmLists;
    var limit = this.config.limits.lists;
    var locked = this.own.banInfo && this.own.banInfo.lockStatus && this.own.banInfo.lockStatus !== "NOT_LOCKED";
    var run = this.startAllRun;
    var create = locked ? [true, this.L("avatar_locked")] : lists.length >= limit ? [true, this.L("error_limitReached")] : [false, ""];
    var all = this.startAllState();
    var wrapper = this.sticky.querySelector("#stickyWrapper");
    wrapper.innerHTML =
      '<div class="farmListCount">' + esc(this.L("farmLists")) + ": " + this.ratio(lists.length, limit, true) + "</div>" +
      '<div class="runningFarmLists">' + (run ? '<div class="farmListCount">' + esc(this.L("startingFarmLists")) + ": " + this.ratio(run.started, lists.length, true) + "</div>" : esc(this.L("startFromAllVillages"))) + "</div>" +
      this.button({ cls: "createFarmList", disabled: create[0], title: create[1], html: esc(this.L("createFarmList")) }) +
      (run
        ? this.button({ cls: "cancelStartAllFarmLists", appearance: "red", title: this.L("cancelStarting"), html: esc(this.L("cancel_with_capital_c")) })
        : this.button({ cls: "startAllFarmLists", disabled: all.disabled, title: all.title, html: esc(this.L("startAllFarmLists")) }));
    if (!this.sticky.dataset.bound) {
      this.sticky.dataset.bound = "1";
      this.sticky.addEventListener("click", function (event) {
        var button = event.target.closest("button");
        if (!button || button.classList.contains("disabled")) return;
        if (button.classList.contains("createFarmList")) self.openListDialog({ type: "create" });
        else if (button.classList.contains("startAllFarmLists")) self.startAll();
        else if (button.classList.contains("cancelStartAllFarmLists")) self.cancelStartAll();
      });
    }
    this.refreshTips();
  };

  /** Whether "Start all farm lists" can run: the first reason that blocks it, else its normal title. */
  FarmListPage.prototype.startAllState = function () {
    var own = this.own;
    var dispatchable = own.farmLists.filter(function (list) {
      return list.slotsStates.some(function (slot) { return slot.isActive; });
    });
    if (dispatchable.length === 0) return { disabled: true, title: this.L("noTargetsFound") };
    if (own.banInfo && own.banInfo.lockStatus && own.banInfo.lockStatus !== "NOT_LOCKED") return { disabled: true, title: this.L("account_locked") };
    if (own.isSitter && !own.accessRights.sendRaids) return { disabled: true, title: this.L("sitter_not_allowed_only_with_right") };
    return { disabled: false, title: this.L("startFromAllVillages") };
  };

  /* ------------------------------------------------------------ interaction */

  FarmListPage.prototype.bindList = function (drop, list, abandoned) {
    var self = this;
    var wrapper = drop.querySelector(".farmListWrapper");
    wrapper.querySelector(".farmListHeader .expandCollapse").addEventListener("click", function () {
      self.setExpanded([list], !list.isExpanded, abandoned);
    });
    wrapper.querySelector(".editFarmListName").addEventListener("click", function () {
      self.openListDialog({ type: "edit", list: list, abandoned: abandoned });
    });
    wrapper.querySelectorAll(".startFarmList").forEach(function (button) {
      button.addEventListener("click", function () {
        if (!button.classList.contains("disabled") && self.startList) self.startList(list, abandoned);
      });
    });
    var table = wrapper.querySelector("table.slots");
    table.querySelectorAll("th.sortable").forEach(function (header) {
      header.addEventListener("click", function () {
        self.sort(list, header.getAttribute("data-field"), "asc", abandoned);
      });
    });
    table.querySelector("th.lastRaid .sorting").addEventListener("click", function (event) {
      self.toggleMenu("sorting" + list.id, event.currentTarget, list, abandoned);
    });
    table.querySelectorAll("input[data-check-all]").forEach(function (box) {
      box.addEventListener("change", function () {
        self.selected[list.id] = box.checked ? list.slots.map(function (slot) { return slot.id; }) : [];
        self.drawList(list, abandoned);
      });
    });
    table.querySelectorAll('input[name="selectOne"]').forEach(function (box) {
      box.addEventListener("change", function () {
        var id = parseInt(box.getAttribute("data-slot-id"), 10);
        var selection = self.selection(list);
        if (box.checked) selection.push(id);
        else selection.splice(selection.indexOf(id), 1);
        self.drawList(list, abandoned);
      });
    });
    table.querySelectorAll("tr.slot .openContextMenu a").forEach(function (link) {
      link.addEventListener("click", function () {
        self.toggleMenu("buttons" + link.closest("tr").getAttribute("data-slot"), link, list, abandoned);
      });
    });
    var add = table.querySelector(".addTarget a");
    if (add && !add.classList.contains("disabled")) {
      add.addEventListener("click", function () {
        self.openSlotDialog({ type: "create", list: list, abandoned: false });
      });
    }
    var footerMenu = wrapper.querySelector(".farmListFooter .openContextMenu");
    if (footerMenu) {
      footerMenu.addEventListener("click", function () {
        self.toggleMenu("footer" + list.id, footerMenu, list, abandoned);
      });
    }
    if (this.menu && this.menu.list === list.id) this.showMenu(this.menu.key, list, abandoned);
  };

  FarmListPage.prototype.drawList = function (list, abandoned) {
    var drop = this.nodes[list.id];
    if (!drop) return;
    var holder = document.createElement("div");
    holder.innerHTML = this.listHtml(list, abandoned);
    var fresh = holder.firstChild;
    drop.parentNode.replaceChild(fresh, drop);
    this.nodes[list.id] = fresh;
    this.bindList(fresh, list, abandoned);
    this.refreshTips();
  };

  FarmListPage.prototype.setExpanded = function (lists, expanded, abandoned) {
    var self = this;
    var changed = lists.filter(function (list) { return list.isExpanded !== expanded; });
    if (changed.length === 0) return;
    changed.forEach(function (list) {
      list.isExpanded = expanded;
      if (!expanded) self.selected[list.id] = [];
    });
    if (abandoned) {
      changed.forEach(function (list) { self.drawList(list, true); });
      this.drawAbandonedHeader();
    } else {
      var kids = {};
      changed.forEach(function (list) { kids[list.ownerVillage.id] = true; });
      Object.keys(kids).forEach(function (kid) { self.drawVillage(parseInt(kid, 10)); });
      this.api("farm-list/change-expanded-state", "PUT", { farmLists: changed.map(function (list) { return list.id; }), isExpanded: expanded });
    }
    this.drawSticky();
  };

  FarmListPage.prototype.sort = function (list, field, fallback, abandoned) {
    var self = this;
    var direction = Model.nextSort(list, field, fallback);
    this.api("farm-list/" + list.id + "/change-sorting", "PUT", { sortField: field, sortDirection: direction, abandoned: !!abandoned }, function (response) {
      self.applyResponse(response);
    });
  };

  /**
   * Applies an API response: the lists it carries replace or join the page's lists, deleted ones leave, and every
   * village that changed is drawn again. The selection survives because the page keeps it.
   */
  FarmListPage.prototype.applyResponse = function (response) {
    var self = this;
    var kids = {};
    (response.deletedFarmListIds || []).forEach(function (id) {
      self.own.farmLists = self.own.farmLists.filter(function (list) {
        if (list.id !== id) return true;
        kids[list.ownerVillage.id] = true;
        delete self.selected[id];
        return false;
      });
      self.own.abandonedFarmLists = self.own.abandonedFarmLists.filter(function (list) { return list.id !== id; });
    });
    (response.farmLists || []).forEach(function (fresh) {
      var abandoned = self.own.abandonedFarmLists.some(function (list) { return list.id === fresh.id; });
      var lists = abandoned ? self.own.abandonedFarmLists : self.own.farmLists;
      var index = lists.map(function (list) { return list.id; }).indexOf(fresh.id);
      if (index >= 0) {
        kids[lists[index].ownerVillage.id] = true;
        lists[index] = fresh;
      } else lists.push(fresh);
      kids[fresh.ownerVillage.id] = true;
    });
    if (!this.root) return;
    this.redrawAll();
    this.drawSticky();
  };

  /** Reads one list again, or the whole page, and draws what comes back. */
  FarmListPage.prototype.reload = function (listId, done) {
    var self = this;
    this.api(listId ? "farm-list/" + listId : "farm-list", "GET", undefined, function (response) {
      if (response.viewData) {
        self.serverTime = response.viewData.bootstrapData.timestamp;
        self.loadedAt = Date.now();
        self.own = response.viewData.ownPlayer;
        self.redrawAll();
        self.drawSticky();
      } else self.applyResponse(response);
      if (done) done();
    });
  };

  /* ------------------------------------------------------------ context menu */

  FarmListPage.prototype.toggleMenu = function (key, anchor, list, abandoned) {
    if (this.menu && this.menu.key === key) {
      this.closeMenu();
      return;
    }
    this.closeMenu();
    this.menu = { key: key, list: list.id, slot: key.indexOf("buttons") === 0 ? parseInt(key.slice(7), 10) : null };
    this.showMenu(key, list, abandoned);
    this.drawListRows(list, abandoned);
  };

  FarmListPage.prototype.closeMenu = function () {
    if (!this.menu) return;
    var list = this.own.farmLists.concat(this.own.abandonedFarmLists).filter(function (l) { return l.id === this.menu.list; }, this)[0];
    document.querySelectorAll(".contextMenuWrapper").forEach(function (node) { node.remove(); });
    var abandoned = list && this.own.abandonedFarmLists.indexOf(list) >= 0;
    this.menu = null;
    if (list) this.highlight(list, null);
    return abandoned;
  };

  /** Marks the row whose menu is open. */
  FarmListPage.prototype.highlight = function (list, slotId) {
    var drop = this.nodes[list.id];
    if (!drop) return;
    drop.querySelectorAll("tr.slot").forEach(function (row) {
      row.classList.toggle("highlighted", slotId !== null && parseInt(row.getAttribute("data-slot"), 10) === slotId);
    });
  };

  FarmListPage.prototype.drawListRows = function (list) {
    this.highlight(list, this.menu ? this.menu.slot : null);
  };

  FarmListPage.prototype.showMenu = function (key, list, abandoned) {
    var drop = this.nodes[list.id];
    var self = this;
    var anchor, parent, html, appearance, entries, description;
    if (key.indexOf("sorting") === 0) {
      appearance = "sorting";
      anchor = parent = drop.querySelector("th.lastRaid .sorting");
      description = this.L("sortBy");
      entries = SORT_MENU.map(function (entry) {
        var active = list.sortField === entry.field;
        var downFirst = entry.direction === "desc";
        var up = downFirst ? active && list.sortDirection === "asc" : active ? list.sortDirection === "asc" : true;
        return {
          cls: entry.field,
          icon: entry.icon.glyph ? self.glyph(entry.icon.glyph, "") : '<i class="' + entry.icon.cls + '"></i>',
          text: self.L(entry.field),
          arrow: self.glyph("arrow", "sortingArrow" + (active ? "" : " inactive") + " " + (up ? "up" : "down")),
          run: function () { self.sort(list, entry.field, entry.direction, abandoned); },
        };
      });
      parent = drop.querySelector("th.lastRaid");
    } else if (key.indexOf("buttons") === 0) {
      appearance = "defaultButtons";
      var slotId = parseInt(key.slice(7), 10);
      var slot = list.slots.filter(function (s) { return s.id === slotId; })[0];
      parent = drop.querySelector('tr.slot[data-slot="' + slotId + '"] .openContextMenu');
      entries = [{ cls: "edit", icon: "edit", text: this.L("edit"), title: this.L("editSlot"), run: function () { self.editSlots(list, [slot.id], "edit", abandoned); } }];
      if (!abandoned) {
        entries.push(
          slot.isActive
            ? { cls: "deactivate", icon: "deactivate", text: this.L("deactivate"), title: this.L("deactivateSlot"), run: function () { self.toggleSlots(list, [slot.id]); } }
            : { cls: "activate", icon: "activate", text: this.L("activate"), title: this.L("activateSlot"), run: function () { self.toggleSlots(list, [slot.id]); } },
          { cls: "duplicate", icon: "duplicate", text: this.L("duplicate"), title: this.L("duplicateSelectedSlots"), run: function () { self.editSlots(list, [slot.id], "duplicate", abandoned); } }
        );
      }
      entries.push({ cls: "delete", icon: "delete", text: this.L("delete"), title: this.L("deleteSlot"), run: function () { self.deleteSlots(list, [slot.id], abandoned); } });
    } else {
      appearance = "defaultButtons";
      parent = drop.querySelector(".farmListFooter .openContextMenu");
      var selected = this.selection(list);
      var any = selected.length > 0;
      var all = list.slots.map(function (s) { return s.id; });
      var activeIds = list.slotsStates.filter(function (s) { return s.isActive; }).map(function (s) { return s.id; });
      var target = any ? selected : all;
      var inactiveSelected = any ? selected.filter(function (id) { return activeIds.indexOf(id) < 0; }) : [];
      var hasInactiveSelected = inactiveSelected.length > 0;
      var hasInactive = all.some(function (id) { return activeIds.indexOf(id) < 0; });
      entries = [{ cls: "edit", icon: "edit", text: this.L("edit") + " (" + target.length + ")", title: this.L(any ? "editSelectedSlots" : "editAllSlots"), run: function () { self.editSlots(list, target, "edit", abandoned); } }];
      if (!abandoned) {
        var activating = any ? hasInactiveSelected : hasInactive;
        var amount = any ? (hasInactiveSelected ? inactiveSelected.length : selected.length) : hasInactive ? all.length - activeIds.length : all.length;
        entries.push(
          {
            cls: activating ? "activate" : "deactivate", icon: activating ? "activate" : "deactivate",
            text: this.L(activating ? "activate" : "deactivate") + " (" + amount + ")",
            title: this.L(any ? (activating ? "activateSelectedSlots" : "deactivateSelectedSlots") : activating ? "activateAllSlots" : "deactivateAllSlots"),
            run: function () { self.toggleSlots(list, any ? selected : undefined); },
          },
          { cls: "duplicate", icon: "duplicate", text: this.L("duplicate") + " (" + target.length + ")", title: this.L(any ? "duplicateSelectedSlots" : "duplicateAllSlots"), run: function () { self.editSlots(list, target, "duplicate", abandoned); } }
        );
      }
      entries.push({ cls: "delete", icon: "delete", text: this.L("delete") + " (" + target.length + ")", title: this.L(any ? "deleteSelectedSlots" : "deleteAllSlots"), run: function () { self.deleteSlots(list, target, abandoned); } });
    }
    html = description ? '<div class="description">' + esc(description) + "</div>" : "";
    entries.forEach(function (entry, index) {
      if (appearance === "sorting") {
        html += '<a class="' + entry.cls + '" data-entry="' + index + '">' + entry.icon + "<span>" + esc(entry.text) + "</span>" + entry.arrow + "</a>";
      } else {
        html += self.button({ cls: "entry " + entry.cls, withTextAndIcon: true, title: entry.title, html: self.layeredNested(entry.icon) + "<span>" + esc(entry.text) + "</span>" }).replace("<button", '<button data-entry="' + index + '"');
      }
    });
    var wrapper = document.createElement("div");
    wrapper.className = "contextMenuWrapper";
    wrapper.innerHTML = '<div class="contextMenu from ' + appearance + '">' + html + "</div>";
    parent.appendChild(wrapper);
    var menu = wrapper.firstChild;
    wrapper.addEventListener("click", function (event) {
      event.stopPropagation();
      if (event.target === wrapper) self.closeMenu();
    });
    wrapper.querySelectorAll("[data-entry]").forEach(function (node) {
      node.addEventListener("click", function () {
        var entry = entries[parseInt(node.getAttribute("data-entry"), 10)];
        self.closeMenu();
        entry.run();
      });
    });
    this.placeMenu(wrapper, menu, parent);
    this.refreshTips();
  };

  /** Puts the menu beside the control that opened it, on the side that has room. */
  FarmListPage.prototype.placeMenu = function (wrapper, menu, parent) {
    var t = { x: menu.clientWidth, y: menu.clientHeight };
    var n = { x: wrapper.clientWidth, y: wrapper.clientHeight };
    var o = wrapper.getBoundingClientRect();
    var l = { x: parent.clientWidth, y: parent.clientHeight };
    var c = parent.getBoundingClientRect();
    var u = { x: c.left + window.scrollX, y: c.top + window.scrollY };
    var d = { x: u.x + l.x + 2 - window.scrollX, y: u.y - o.top - (t.y - l.y) / 2 - window.scrollY };
    if (d.y < 0) d.y = u.y - o.top;
    d.y = Math.max(d.y, 0);
    if (d.y + t.y > n.y) d.y = u.y - o.top + l.y - t.y;
    d.y = Math.min(d.y, n.y - t.y);
    if (getComputedStyle(document.body).direction === "ltr") {
      if (d.x + t.x > o.x + n.x) {
        d.x = u.x - t.x - 2;
        menu.classList.replace("from", "to");
      }
    } else {
      d.x = u.x - t.x - 2;
      if (d.x < 0) {
        d.x = u.x + l.x + 2;
        menu.classList.replace("from", "to");
      }
    }
    menu.style.left = d.x + "px";
    menu.style.top = d.y + "px";
  };

  /* ----------------------------------------------------------------- targets */

  FarmListPage.prototype.toggleSlots = function (list, ids) {
    var self = this;
    var slots = list.slots.filter(function (slot) { return !ids || ids.indexOf(slot.id) >= 0; });
    var activate = list.slots.filter(function (slot) { return !slot.isActive; }).some(function (slot) { return slots.indexOf(slot) >= 0; });
    var payload = slots.map(function (slot) {
      return { id: slot.id, listId: list.id, x: slot.target.x, y: slot.target.y, units: Object.assign({}, slot.troop), active: activate };
    });
    this.api("farm-list/slot?force", "PUT", { slots: payload }, function (response) {
      self.selected[list.id] = [];
      self.applyResponse(response);
    });
  };

  FarmListPage.prototype.deleteSlots = function (list, ids, abandoned) {
    var self = this;
    this.confirm({
      message: this.L("deleteSlotsConfirmation"), confirmText: this.L("delete"), appearance: "red",
      onConfirm: function () {
        self.api("farm-list/slot", "DELETE", { slots: ids, abandoned: !!abandoned }, function (response) {
          self.selected[list.id] = [];
          self.applyResponse(response);
        });
      },
    });
  };

  /** Opens the target dialog for the given slots of a list (edit or duplicate). */
  FarmListPage.prototype.editSlots = function (list, ids, type, abandoned) {
    var slots = list.slots.filter(function (slot) { return ids.indexOf(slot.id) >= 0; });
    var first = slots[0];
    var same = function (read) {
      return slots.every(function (slot) { return read(slot) === read(first); }) ? read(first) : undefined;
    };
    var troop = {};
    UNITS.forEach(function (unit) {
      troop[unit] = same(function (slot) { return slot.troop[unit]; });
    });
    this.openSlotDialog({
      type: type, list: list, editSlotIds: slots.map(function (slot) { return slot.id; }), abandoned: !!abandoned,
      selectedActive: same(function (slot) { return slot.isActive; }), selectedTroop: troop,
      target: slots.length === 1 ? first.target : undefined,
    });
  };

  /* -------------------------------------------------------------------- api */

  FarmListPage.prototype.api = function (path, method, data, success, error) {
    var options = { action: "farmList", success: success || function () {}, error: error };
    if (data !== undefined) options.data = data;
    Travian.api(path, options, method);
  };

  /** The confirmation dialog: a message and two buttons. */
  FarmListPage.prototype.confirm = function (options) {
    var page = this;
    var dialog = openDialog(page, { cssClass: "plain confirmationDialog", buttonCancel: false });
    dialog.content.innerHTML =
      '<div class="confirmationDialogContent formV2"><div class="message"><p>' + esc(options.message) + "</p></div>" +
      page.button({ cls: "", appearance: "grey", html: esc(options.cancelText || page.L("cancel_with_capital_c")) }) +
      page.button({ cls: "", appearance: options.appearance || "green", html: esc(options.confirmText) }) + "</div>";
    var buttons = dialog.content.querySelectorAll("button");
    buttons[0].addEventListener("click", function () { dialog.close(); });
    buttons[1].addEventListener("click", function () {
      options.onConfirm();
      dialog.close();
    });
    return dialog;
  };

  /* ------------------------------------------------------------------ ordering */

  /**
   * Moving a list by its handle within its village: a copy of the list follows the pointer, the list nearest to the
   * pointer shows where it would land, and letting go stores the new position.
   */
  FarmListPage.prototype.bindDrag = function () {
    var self = this;
    var drag = null;
    var pointer = function (event) {
      var point = event.touches && event.touches.length ? event.touches[0] : event.changedTouches && event.changedTouches.length ? event.changedTouches[0] : event;
      return { x: point.clientX, y: point.clientY };
    };
    var onMove = function (event) {
      var at = pointer(event);
      var best = null;
      var closest = drag.closest;
      drag.containers.forEach(function (container) {
        var header = container.querySelector(".farmListHeader").getBoundingClientRect();
        var distance = Math.abs(at.y - header.height / 2 - header.top);
        if (best === null || distance < best) {
          best = distance;
          closest = parseInt(container.getAttribute("data-sortindex"), 10);
        }
      });
      drag.closest = closest;
      drag.containers.forEach(function (container) {
        var index = parseInt(container.getAttribute("data-sortindex"), 10);
        var near = index === closest;
        container.classList.toggle("highlightDropSection", near && index !== drag.sortIndex);
        container.classList.toggle("down", near && index > drag.sortIndex);
        container.classList.toggle("up", near && !(index > drag.sortIndex));
      });
      var handle = drag.handle.getBoundingClientRect();
      var parent = drag.handle.parentNode.getBoundingClientRect();
      var left = parent.left - handle.left + handle.width / 2;
      if (getComputedStyle(document.body).direction === "rtl") left = -1 * left + handle.width;
      var top = handle.top - parent.top + handle.height / 2;
      drag.object.style.left = at.x - left + "px";
      drag.object.style.top = at.y - top + "px";
    };
    var onUp = function () {
      window.removeEventListener("mousemove", onMove);
      window.removeEventListener("touchmove", onMove);
      window.removeEventListener("mouseup", onUp);
      window.removeEventListener("touchend", onUp);
      var state = drag;
      drag = null;
      var list = state.list;
      var target = state.closest;
      var finish = function () {
        document.querySelectorAll(".villageWrapper.inactive").forEach(function (node) { node.classList.remove("inactive"); });
        state.object.remove();
        self.drawVillage(list.ownerVillage.id);
      };
      if (target === state.sortIndex) {
        finish();
        return;
      }
      self.listsOf(list.ownerVillage.id).forEach(function (other) {
        if (other.id === list.id) other.sortIndex = target;
        else if (target > state.sortIndex && other.sortIndex > state.sortIndex && other.sortIndex <= target) other.sortIndex -= 1;
        else if (target < state.sortIndex && other.sortIndex < state.sortIndex && other.sortIndex >= target) other.sortIndex += 1;
      });
      self.api("farm-list/" + list.id + "/change-sort-index", "PUT", { newIndex: target });
      var rect = state.wrapper.getBoundingClientRect();
      state.wrapper.classList.add("hidden");
      state.object.style.left = rect.left + "px";
      state.object.style.top = rect.top + "px";
      state.object.style.animation = "dragObjectDropping 300ms ease-in-out forwards";
      state.object.style.transitionDuration = "300ms";
      setTimeout(finish, 300);
    };
    var onDown = function (event) {
      var handle = event.target.closest && event.target.closest(".dragAndDrop");
      if (!handle || !handle.getAttribute("data-list") || drag) return true;
      if (getComputedStyle(handle).opacity === "0") return true;
      var list = self.own.farmLists.filter(function (l) { return l.id === parseInt(handle.getAttribute("data-list"), 10); })[0];
      if (!list) return true;
      event.preventDefault();
      var village = self.villageNode(list.ownerVillage.id);
      var wrapper = self.nodes[list.id].querySelector(".farmListWrapper");
      var object = document.createElement("div");
      object.className = "dragObject";
      object.innerHTML = wrapper.outerHTML;
      village.appendChild(object);
      wrapper.classList.add("dragged");
      document.querySelectorAll(".villageWrapper").forEach(function (node) {
        if (node !== village) node.classList.add("inactive");
      });
      drag = {
        list: list, sortIndex: list.sortIndex, closest: list.sortIndex, handle: handle, wrapper: wrapper, object: object,
        containers: Array.prototype.slice.call(village.querySelectorAll(".dropContainer")),
      };
      onMove(event);
      window.addEventListener("mousemove", onMove);
      window.addEventListener("touchmove", onMove);
      window.addEventListener("mouseup", onUp);
      window.addEventListener("touchend", onUp);
      return false;
    };
    window.addEventListener("mousedown", onDown);
    window.addEventListener("touchstart", onDown);
  };

  /* ------------------------------------------------------------------ starting */

  /** The ids a list's start button sends: the selected targets that are active, else every active target. */
  FarmListPage.prototype.startTargets = function (list) {
    var active = list.slotsStates.filter(function (slot) { return slot.isActive; }).map(function (slot) { return slot.id; });
    var selected = this.selection(list);
    return selected.length > 0 ? active.filter(function (id) { return selected.indexOf(id) >= 0; }) : active;
  };

  /** Disables or enables every start button while raids are being started. */
  FarmListPage.prototype.setStartDisabled = function (disabled) {
    if (this.startButtonsDisabled === disabled || !this.root) return;
    this.startButtonsDisabled = disabled;
    this.redrawAll();
    this.drawSticky();
  };

  /**
   * Starts a list. `startData` carries the start-all flags: the first list of a start-all run says startedAll, the
   * following ones triggeredBySendAll.
   */
  FarmListPage.prototype.startList = function (list, abandoned, startData) {
    var self = this;
    startData = startData || {};
    if (this.raidBeingSent) return;
    this.startResults = this.startResults || {};
    this.startErrors = this.startErrors || {};
    list.slots.forEach(function (slot) { delete self.startErrors[slot.id]; });
    delete this.startResults[list.id];
    this.raidBeingSent = true;
    this.setStartDisabled(true);
    var payload = Object.assign({ lists: [{ id: list.id, targets: this.startTargets(list) }] }, startData);
    this.api("farm-list/send", "POST", payload, function (response) {
      self.selected[list.id] = [];
      var results = { success: 0, errors: {} };
      response.lists[0].targets.forEach(function (target) {
        if (target.error) {
          var key = JSON.stringify(target.error);
          results.errors[key] = results.errors[key] || { message: target.error, count: 0 };
          results.errors[key].count += 1;
          self.startErrors[target.id] = target.error;
        } else results.success += 1;
      });
      if (response.lists[0].targets.length > 0) self.startResults[list.id] = results;
      self.raidBeingSent = false;
      if (startData.triggeredBySendAll || startData.startedAll) {
        self.drawList(list, false);
        return;
      }
      self.reload(list.id, function () { self.setStartDisabled(false); });
    }, function (error) {
      self.openMessage(error && error.message);
      self.raidBeingSent = false;
      self.setStartDisabled(false);
      self.cancelStartAll();
    });
  };

  /** A plain dialog with a message and an OK button. */
  FarmListPage.prototype.openMessage = function (message) {
    var dialog = openDialog(this, { cssClass: "plain contentV2", buttonCancel: false });
    dialog.content.innerHTML = '<span>' + (message || "") + "</span><div class=\"buttons\">" + this.button({ cls: "dialogButtonOk", html: esc(this.L("ok")) }) + "</div>";
    dialog.content.querySelector("button").addEventListener("click", function (event) {
      event.preventDefault();
      dialog.close();
    });
    return dialog;
  };

  /** "Start all farm lists": every list with active targets in turn, one every 100 ms, while the sticky bar counts. */
  FarmListPage.prototype.startAll = function () {
    var self = this;
    if (this.startAllRun) return;
    var queue = this.own.farmLists.filter(function (list) {
      return list.slotsStates.some(function (slot) { return slot.isActive; });
    }).map(function (list) { return list; });
    this.startResults = {};
    this.startErrors = {};
    this.redrawAll();
    var total = queue.length;
    var run = (this.startAllRun = { started: 0, total: total });
    this.drawSticky();
    run.timer = setInterval(function () {
      if (queue.length === 0 && !self.raidBeingSent) {
        self.cancelStartAll();
        return;
      }
      if (self.raidBeingSent) return;
      var list = queue.shift();
      run.started += 1;
      self.drawSticky();
      self.startList(list, false, run.started === 1 ? { startedAll: true } : { triggeredBySendAll: true });
    }, 100);
  };

  FarmListPage.prototype.cancelStartAll = function () {
    var run = this.startAllRun;
    var wasRunning = !!run;
    if (run) clearInterval(run.timer);
    this.startAllRun = null;
    this.setStartDisabled(false);
    this.drawSticky();
    if (wasRunning) this.reload();
  };

  /* ------------------------------------------------------------ list dialog */

  /** A validated form of the dialogs, run by the game's own form handler. */
  FarmListPage.prototype.formHandler = function (form) {
    var handler = new Travian.FormV2(form);
    handler.valid = function () {
      handler.isFormValid = true;
      handler.validateForm();
      return handler.isFormValid;
    };
    return handler;
  };

  FarmListPage.prototype.troopInputs = function (names, values, tribe, pageTitle) {
    var self = this;
    return names.map(function (unit) {
      var value = values[unit];
      return (
        '<label class="input fixed ' + (pageTitle || "") + '"><i class="unit_medium tribe' + tribe + " " + unit + '" title="' + esc(self.unitTitle(unit)) + '"></i>' +
        '<input type="text" class="unitAmount" name="' + unit + '" maxlength="7" autocomplete="off" inputmode="numeric" value="' + (value === undefined ? "?" : value === null ? "" : value) + '"></label>'
      );
    }).join("");
  };

  /** Keeps a troop field a whole number between 0 and the limit, as the reference does while typing. */
  FarmListPage.prototype.clampTroop = function (input) {
    var amount = parseInt(input.value, 10);
    amount = isNaN(amount) ? 0 : Math.min(this.config.limits.units, Math.max(0, amount));
    input.value = String(amount);
    return amount;
  };

  FarmListPage.prototype.openListDialog = function (options) {
    var self = this;
    var edit = options.type === "edit";
    var list = options.list;
    var abandoned = !!options.abandoned;
    var villages = this.own.villages;
    var state = {
      name: edit ? list.name : "", villageId: edit ? list.ownerVillage.id : undefined,
      useShip: edit ? list.useShip : false, onlyLosses: edit ? list.onlyLosses : false,
    };
    var troops = {};
    UNITS.forEach(function (unit) { troops[unit] = edit ? list.defaultTroop[unit] : null; });
    var dialog = openDialog(this, { cssClass: "basic farmListDialog" });
    var allLists = this.own.farmLists;
    var limitReached = allLists.length >= this.config.limits.lists;
    dialog.content.innerHTML = '<div class="contentV2"><h3>' + esc(this.L(edit ? "editFarmlist" : "createFarmList")) + '</h3><div id="createFarmListForm" class="formV2"></div></div>';
    var form = dialog.content.querySelector("#createFarmListForm");
    var handler;
    var draw = function () {
      var tribe = self.config.race;
      var current = villages.filter(function (v) { return v.id === state.villageId; })[0] || self.own.village;
      var options_ = '<option></option>' + villages.map(function (v) {
        return '<option value="' + v.id + '"' + (v.id === state.villageId ? " selected" : "") + ">" + esc(v.name) + "</option>";
      }).join("") + (edit && abandoned ? '<option value="0"' + (state.villageId === undefined ? " selected" : "") + ">" + esc(self.L("abandonedVillage")) + "</option>" : "");
      var duplicate = allLists.some(function (l) { return state.villageId !== undefined && l.ownerVillage.id === state.villageId && l.name === state.name && !(edit && state.name === list.name); });
      var tribeWarning = edit && state.villageId !== undefined && current && villages.some(function (v) { return v.id === list.ownerVillage.id && v.tribeId !== current.tribeId; });
      var saveDisabled = duplicate || (abandoned && limitReached);
      form.innerHTML =
        '<label class="input"><input type="text" name="listName" maxlength="30" value="' + esc(state.name) + '"><div class="label">' + esc(self.L("listName")) + "</div>" +
        '<div class="validation" data-rule="notEmpty">' + esc(self.L("nameNotEmpty")) + "</div>" +
        (duplicate ? '<div class="validation custom" data-rule="customError" data-name="listName">' + esc(self.L("farmListNameExistsForVillage")) + "</div>" : "") + "</label>" +
        '<label class="select"><select size="1" name="villageId">' + options_ + '</select><div class="label">' + esc(self.L("selectVillage")) + '</div><div class="validation" data-rule="notEmpty">' + esc(self.L("villageNotEmpty")) + "</div></label>" +
        '<h4 class="troopsHeader">' + esc(self.L("setDefaultTroops")) + '</h4><div class="troopsDescription">' + esc(self.L("newEntryPrefilled")) + "</div>" +
        '<div class="troopSelection">' + self.troopInputs(UNITS, troops, tribe) + "</div>" +
        (self.config.harbours.useDirectPath && current && current.hasHarbour ? '<div class="useShipSelection">' + self.checkboxLabel("useShip", state.useShip, self.L("useRaidShips"), self.L("useRaidShipsNote")) + "</div>" : "") +
        '<div class="onlyLossesSelection">' + self.checkboxLabel("onlyLosses", state.onlyLosses, self.L("onlyLosses")) + "</div>" +
        (tribeWarning ? '<p class="error">' + esc(self.L("tribeWarningForSourceVillage")) + "</p>" : "") +
        '<p class="error" data-api-error style="display:none"></p>' +
        '<div class="actionButtons">' + self.button({ cls: "cancel", appearance: "grey", html: esc(self.L("cancel_with_capital_c")) }) +
        (edit ? self.button({ cls: "delete", icon: true, title: self.L("delete"), html: self.layered("delete") }) : "") +
        self.button({ cls: "save", type: "submit", disabled: saveDisabled, title: abandoned && limitReached ? self.L("error_limitReached") : "", html: esc(self.L(edit ? "save" : "create")) }) + "</div>";
      bind();
    };
    var bind = function () {
      var focused = document.activeElement && document.activeElement.name;
      handler = self.formHandler(form);
      form.querySelector('input[name="listName"]').addEventListener("input", function (event) {
        state.name = event.target.value;
        var wasDuplicate = form.querySelector(".validation.custom");
        var duplicate = allLists.some(function (l) { return state.villageId !== undefined && l.ownerVillage.id === state.villageId && l.name === state.name && !(edit && state.name === list.name); });
        if (!!wasDuplicate !== duplicate) {
          self.keepFocus = "listName";
          draw();
        }
      });
      form.querySelector('select[name="villageId"]').addEventListener("change", function (event) {
        state.villageId = parseInt(event.target.value, 10);
        if (isNaN(state.villageId)) state.villageId = undefined;
        draw();
      });
      form.querySelectorAll("input.unitAmount").forEach(function (input) {
        input.addEventListener("input", function () {
          troops[input.name] = self.clampTroop(input);
        });
      });
      form.querySelectorAll('input[name="useShip"], input[name="onlyLosses"]').forEach(function (box) {
        box.addEventListener("change", function () { state[box.name] = box.checked; });
      });
      form.querySelector(".cancel").addEventListener("click", dialog.close);
      var remove = form.querySelector(".delete");
      if (remove) {
        remove.addEventListener("click", function () {
          self.confirm({
            message: self.L("farmListDeleteConfirmation"), confirmText: self.L("delete"), appearance: "red",
            onConfirm: function () {
              self.api("farm-list/" + list.id, "DELETE", { abandoned: abandoned }, function (response) {
                self.applyResponse(response);
                dialog.close();
              }, function (error) {
                self.showError(form, (error && error.message) || self.L("error_delete"));
              });
            },
          });
        });
      }
      var save = form.querySelector(".save");
      save.addEventListener("click", function (event) {
        event.preventDefault();
        if (save.classList.contains("disabled") || !handler.valid()) return;
        var units = {};
        UNITS.forEach(function (unit) { units[unit] = troops[unit] || 0; });
        var data = { villageId: state.villageId, name: form.querySelector('input[name="listName"]').value, defaultUnits: units, useShip: state.useShip, onlyLosses: state.onlyLosses };
        if (!edit) data.abandoned = abandoned;
        self.api(edit ? "farm-list/" + list.id : "farm-list", edit ? "PUT" : "POST", data, function (response) {
          if (edit && abandoned && state.villageId !== undefined) self.reload();
          else self.applyResponse(response);
          if (options.onSaveSuccess) options.onSaveSuccess(response.id, state.villageId);
          dialog.close();
        }, function (error) {
          self.showError(form, error && error.message);
        });
      });
      if (self.keepFocus) {
        var field = form.querySelector('[name="' + self.keepFocus + '"]');
        self.keepFocus = null;
        if (field) {
          field.focus();
          field.setSelectionRange(field.value.length, field.value.length);
        }
      }
    };
    draw();
    this.refreshTips();
    return dialog;
  };

  FarmListPage.prototype.showError = function (form, message) {
    var node = form.querySelector("p.error[data-api-error]");
    if (!node) return;
    node.textContent = message || "";
    node.style.display = message ? "" : "none";
  };

  FarmListPage.prototype.checkboxLabel = function (name, checked, label, note) {
    return (
      '<label class="checkbox"><input type="checkbox" name="' + name + '"' + (checked ? " checked" : "") + ">" + this.glyph("checkbox", "checkbox") + '<div class="label">' + esc(label) + "</div>" +
      (note ? '<div class="note">' + esc(note) + "</div>" : "") + "</label>"
    );
  };

  /* ------------------------------------------------------------- slot dialog */

  FarmListPage.prototype.openSlotDialog = function (options) {
    var self = this;
    var config = this.config;
    var limit = config.limits.slots;
    var create = options.type === "create";
    var edit = options.type === "edit";
    var duplicate = options.type === "duplicate";
    var ids = options.editSlotIds || [];
    var bulk = ids.length > 1;
    var abandonedList = options.abandoned && options.list ? options.list : null;
    var lists = this.own.farmLists.slice();
    var listOf = function (id) {
      return lists.filter(function (l) { return l.id === id; })[0] || (abandonedList && abandonedList.id === id ? abandonedList : undefined);
    };
    var state = {
      listId: options.list ? options.list.id : undefined, target: options.target ? { x: options.target.x, y: options.target.y, mapId: options.target.mapId } : {},
      active: options.selectedActive, troops: {}, touched: false, error: undefined, listError: undefined, notice: undefined,
      troopError: false, duration: undefined, distance: undefined, player: undefined, alliance: undefined, targetError: undefined, lastLookup: {}, checked: false, key: "",
    };
    UNITS.forEach(function (unit) { state.troops[unit] = options.selectedTroop ? options.selectedTroop[unit] : undefined; });
    if (!options.selectedTroop && state.listId !== undefined && listOf(state.listId)) state.troops = Object.assign({}, listOf(state.listId).defaultTroop);
    if (create && state.listId === undefined && this.own.farmLists.length > 0) {
      var inVillage = this.own.farmLists.filter(function (l) { return l.ownerVillage.id === self.own.village.id && l.slotsAmount < limit; });
      if (inVillage.length > 0) state.listId = inVillage[inVillage.length - 1].id;
      if (state.listId !== undefined && !options.selectedTroop) state.troops = Object.assign({}, listOf(state.listId).defaultTroop);
    }
    var dialog = openDialog(this, { cssClass: "basic slotDialog", enableBackground: false });
    var heading = create ? "addTarget" : edit ? (bulk ? "editSlots" : "editSlot") : bulk ? "duplicateSelectedSlots" : "duplicateSlot";
    dialog.content.innerHTML =
      '<div class="contentV2"><h3>' + esc(this.L(heading)) + '</h3><div id="farmListTargetForm" class="formV2"></div></div>';
    var form = dialog.content.querySelector("#farmListTargetForm");
    var handler;
    var tribe = config.race;
    var noTroops = function () {
      return UNITS.reduce(function (sum, unit) { return sum + (state.troops[unit] || 0); }, 0) === 0 && !UNITS.some(function (unit) { return state.troops[unit] === undefined; });
    };
    var coordsKnown = function () { return state.target.x !== undefined && state.target.y !== undefined; };

    var listSelector = function () {
      var current = state.listId;
      var full = function (l) {
        return l.slotsAmount >= limit && (create || (l.id !== (options.list && options.list.id)));
      };
      var opts = "<option></option>" + lists.map(function (l) {
        var v = self.own.villages.filter(function (village) { return village.id === l.ownerVillage.id; })[0];
        var isFull = full(l);
        return '<option value="' + l.id + '"' + (isFull ? " disabled" : "") + (current === l.id ? " selected" : "") + ">" + esc((v ? v.name : "") + " - " + l.name + (isFull ? " " + self.L("full") : "")) + "</option>";
      }).join("") + (abandonedList ? '<option value="' + abandonedList.id + '"' + (current === abandonedList.id ? " selected" : "") + ">" + esc(self.L("abandonedFarmList") + " - " + abandonedList.name) + "</option>" : "");
      var createLimit = self.own.farmLists.length >= config.limits.lists;
      return (
        '<div class="listSelector"><label class="select"><select size="1" name="listId"' + (lists.length === 0 ? " disabled" : "") + ' class="' + (duplicate && current === (options.list && options.list.id) ? "optionDisabled" : "") + '">' + opts +
        '</select><div class="label">' + esc(self.L("selectFarmList")) + '</div><div class="validation" data-rule="notEmpty">' + esc(self.L("errorNoListSelected")) + "</div>" +
        (state.listError ? '<div class="validation" data-rule="customError">' + esc(state.listError) + "</div>" : "") +
        (lists.length === 0 ? '<div class="validation show" data-rule="customError">' + esc(self.L("noFarmList")) + "</div>" : "") + "</label>" +
        self.button({ cls: "createList", icon: true, disabled: createLimit, title: self.L(createLimit ? "error_limitReached" : "create_list"), html: "<svg>" + self.glyph("plus", "outline") + self.glyph("plus", "icon") + "</svg>" }) + "</div>"
      );
    };

    var targetSelection = function () {
      var disabled = edit || duplicate ? " disabled" : "";
      return (
        '<div class="targetSelection"><div class="inputWrapper"><label class="input search"><input type="text" name="villageName"' + disabled + '><div class="label">' + esc(self.L("dorfname")) + "</div></label>" +
        '<label class="input coordinateX"><input name="x" type="text" inputmode="numeric" maxlength="4"' + disabled + ' value="' + (state.target.x === undefined ? "" : state.target.x) + '"><div class="label">' + esc(self.L("coordinateX")) +
        '</div><div class="validation" data-rule="notEmpty" data-render-into=".targetSelectionValidation">' + esc(self.L("koordinaten_eingeben")) + "</div></label>" +
        '<label class="input coordinateY"><input name="y" type="text" inputmode="numeric" maxlength="4"' + disabled + ' value="' + (state.target.y === undefined ? "" : state.target.y) + '"><div class="label">' + esc(self.L("coordinateY")) +
        '</div><div class="validation" data-rule="notEmpty" data-render-into=".targetSelectionValidation">' + esc(self.L("koordinaten_eingeben")) + "</div></label></div>" +
        '<div class="targetSelectionResultWrapper"></div></div>'
      );
    };

    var resultHtml = function () {
      return (
        '<div class="customValidationRenderElement targetSelectionValidation ' + (state.targetError ? "show" : "") + '">' + (state.targetError || "") + "</div>" +
        '<div class="targetWrapper"><div class="player">' + esc(self.L("spieler")) + ": " + (state.player ? '<a href="/profile/' + state.player.uid + '" title="">' + esc(state.player.name) + "</a>" : '<span class="value">-</span>') + "</div>" +
        '<div class="duration">' + esc(self.L("dauer")) + ': <span class="value">' + (state.duration ? esc(self.duration(state.duration)) : "-") + "</span></div>" +
        '<div class="alliance">' + esc(self.L("alliance")) + ": " + (state.alliance && state.alliance.id ? '<a href="/alliance/' + state.alliance.id + '" title="">' + esc(state.alliance.name) + "</a>" : '<span class="value">-</span>') + "</div>" +
        '<div class="arrival">' + (state.distance ? esc(self.L("a2b_distance")) + ': <span class="value">' + state.distance + "</span>" : esc(self.L("ankunft")) + ': <span class="value">-</span>') + "</div></div>"
      );
    };

    var draw = function () {
      var source = listOf(state.listId) ? listOf(state.listId).ownerVillage : self.own.village;
      var indeterminate = UNITS.some(function (unit) { return state.troops[unit] === undefined; });
      var units = UNITS.map(function (unit) { return unit; });
      var disabled = state.listId === undefined || !!state.listError || !(edit || duplicate || (coordsKnown() && (indeterminate || !noTroops()))) || state.troopError;
      form.innerHTML =
        listSelector() + (bulk ? "" : targetSelection()) +
        (indeterminate ? '<div class="noticeBox indeterminateTroopsNotice">' + esc(self.L("indeterminateTroopsNotice")) + "</div>" : "") +
        (state.notice ? '<div class="noticeBox targetListTooFullNotice">' + esc(state.notice) + "</div>" : "") +
        '<h4 class="troopsHeader">' + esc(self.L("setTroops")) + ':</h4><div class="troopSelection">' + self.troopInputs(units, state.troops, tribe, "withCustomValidationRenderElement" + (state.troopError ? " invalid" : "")) +
        '<div class="customValidationRenderElement troopError ' + (state.troopError ? "show" : "") + '">' + esc(self.L("error_no_troops")) + "</div></div>" +
        '<div class="deactivateSlot"><label class="checkbox"><input type="checkbox" name="isActive" value="true" class="' + (state.active === undefined ? "indeterminate" : "") + '"' + (state.active ? "" : " checked") + ">" + self.glyph("checkbox", "checkbox") +
        '<div class="label">' + esc(self.L("deactivateSlot")) + "</div></label></div>" +
        (typeof state.error === "string" ? '<div class="error">' + state.error + "</div>" : "") +
        '<div class="actionButtons">' + self.button({ cls: "cancel", appearance: "grey", html: esc(self.L("cancel_with_capital_c")) }) +
        (edit ? self.button({ cls: "delete", icon: true, html: self.layered("delete") }) : "") +
        self.button({ cls: "save", type: "submit", disabled: disabled, html: esc(self.L("save")) }) + "</div>";
      void source;
      bind();
      if (!bulk) form.querySelector(".targetSelectionResultWrapper").innerHTML = resultHtml();
      self.refreshTips();
    };

    var update = function () {
      var holder = form.querySelector(".targetSelectionResultWrapper");
      if (holder) holder.innerHTML = resultHtml();
    };

    var checkTroops = function (quiet) {
      var indeterminate = UNITS.some(function (unit) { return state.troops[unit] === undefined; });
      var valid = indeterminate || !noTroops();
      if (!quiet) {
        state.troopError = !valid;
        var wrapper = form.querySelector(".troopSelection");
        if (wrapper) {
          wrapper.querySelectorAll("label.input").forEach(function (label) { label.classList.toggle("invalid", state.troopError); });
          wrapper.querySelector(".troopError").classList.toggle("show", state.troopError);
        }
      }
      return valid;
    };

    var refreshSave = function () {
      var save = form.querySelector(".save");
      var indeterminate = UNITS.some(function (unit) { return state.troops[unit] === undefined; });
      var blocked = state.listId === undefined || !!state.listError || !(edit || duplicate || (coordsKnown() && (indeterminate || !noTroops()))) || state.troopError;
      save.classList.toggle("disabled", !!blocked);
    };

    /** Reads the slot limit of the chosen list and fills the list's default troops. */
    var chooseList = function (id) {
      state.listId = id;
      state.listError = undefined;
      state.notice = undefined;
      var chosen = listOf(id);
      if (chosen) {
        if (!state.touched && !options.selectedTroop) state.troops = Object.assign({}, chosen.defaultTroop);
        if (create && chosen.slotsAmount >= limit) state.listError = self.L("errorRaidListSlotLimit");
        else if ((edit || duplicate) && chosen.slotsAmount + ids.length > limit) {
          var origin = options.abandoned ? abandonedList : lists.filter(function (l) { return l.id === (options.list && options.list.id); })[0];
          var present = chosen.slots.filter(function (slot) {
            return origin && origin.slots.some(function (o) { return o.target.id === slot.target.id; });
          }).length;
          var fresh = ids.length - present;
          if (chosen.slotsAmount + fresh > limit) state.notice = self.L("noticeRaidListSlotLimitBulk", { MAX_AMOUNT: String(limit - chosen.slotsAmount + present), LIMIT: String(limit) });
        }
      }
      draw();
      measure();
    };

    /** The travel time to the target for the troops chosen (not for a bulk change). */
    var measure = function () {
      var chosen = listOf(state.listId);
      if (bulk || !chosen || state.target.mapId === undefined) return;
      var troops = {};
      UNITS.forEach(function (unit) { troops[unit] = state.troops[unit] || 0; });
      var key = JSON.stringify([chosen.ownerVillage.id, state.target.mapId, troops]);
      if (key === state.key) return;
      state.key = key;
      if (UNITS.every(function (unit) { return !troops[unit]; })) return;
      self.api("troop/distanceSpeedAndDuration", "POST", { origin: chosen.ownerVillage.id, from: chosen.ownerVillage.id, to: state.target.mapId, troops: troops }, function (response) {
        state.duration = response.data.duration;
        state.distance = Math.round(10 * (response.data.distanceWithoutBonuses + Number.EPSILON)) / 10;
        update();
      }, function () {
        state.duration = undefined;
        state.distance = undefined;
        update();
      });
    };

    var selectTarget = function (target) {
      state.player = target.uid ? { uid: target.uid, name: target.playerName } : undefined;
      state.alliance = { id: target.aid, name: target.allianceName };
      state.target = { x: target.x, y: target.y, mapId: target.mapId };
      state.lastLookup = { x: target.x, y: target.y };
      state.targetError = undefined;
      var inputs = form.querySelectorAll('input[name="x"], input[name="y"], input[name="villageName"]');
      inputs.forEach(function (input) {
        if (input.name === "x") input.value = target.x;
        if (input.name === "y") input.value = target.y;
        if (input.name === "villageName") input.value = target.name || "";
        input.dispatchEvent(new Event("change"));
        handler.validate(jQuery(input).parent("label"));
      });
      update();
      refreshSave();
      measure();
    };

    var lookup = function () {
      var x = state.target.x;
      var y = state.target.y;
      if (x === undefined || y === undefined || (x === state.lastLookup.x && y === state.lastLookup.y)) return;
      state.lastLookup = { x: x, y: y };
      state.player = undefined;
      state.alliance = undefined;
      state.duration = undefined;
      state.distance = undefined;
      state.targetError = undefined;
      self.api("village/target/" + x + "/" + y, "GET", undefined, function (response) {
        selectTarget(response);
      }, function (error) {
        state.targetError = error && error.message;
        state.target.mapId = undefined;
        update();
        refreshSave();
      });
    };

    var bind = function () {
      handler = self.formHandler(form);
      var listSelect = form.querySelector('select[name="listId"]');
      listSelect.addEventListener("change", function () {
        var id = parseInt(listSelect.value, 10);
        chooseList(isNaN(id) ? undefined : id);
      });
      form.querySelector(".createList").addEventListener("click", function (event) {
        if (event.currentTarget.classList.contains("disabled")) return;
        self.openListDialog({
          type: "create",
          onSaveSuccess: function (id) {
            lists = self.own.farmLists.slice();
            if (id) chooseList(id);
          },
        });
      });
      form.querySelectorAll("input.unitAmount").forEach(function (input) {
        input.addEventListener("input", function () {
          state.troops[input.name] = self.clampTroop(input);
          state.touched = true;
          checkTroops(true);
          refreshSave();
        });
        input.addEventListener("blur", measure);
      });
      var active = form.querySelector('input[name="isActive"]');
      active.addEventListener("change", function () {
        state.active = !active.checked;
        active.classList.remove("indeterminate");
      });
      var buttons = form.querySelector(".actionButtons");
      buttons.querySelector(".cancel").addEventListener("click", dialog.close);
      var remove = buttons.querySelector(".delete");
      if (remove) {
        remove.addEventListener("click", function () {
          self.deleteSlots(options.list, ids, options.abandoned);
          dialog.close();
        });
      }
      buttons.querySelector(".save").addEventListener("click", function (event) {
        event.preventDefault();
        if (event.currentTarget.classList.contains("disabled")) return;
        var valid = checkTroops(false);
        if (!handler.valid() || !valid) return;
        self.saveSlots(options, state, ids, dialog, function (message) {
          state.error = message;
          draw();
        });
      });
      if (!bulk) {
        var x = form.querySelector('input[name="x"]');
        var y = form.querySelector('input[name="y"]');
        [x, y].forEach(function (input) {
          input.addEventListener("input", function () {
            var value = parseInt(input.value, 10);
            state.target[input.name] = isNaN(value) ? undefined : value;
            state.target.mapId = undefined;
            refreshSave();
          });
          input.addEventListener("blur", lookup);
        });
        self.bindNameSearch(form.querySelector('input[name="villageName"]'), form, selectTarget, state, function () {
          state.target = {};
          state.player = state.alliance = undefined;
          x.value = "";
          y.value = "";
          update();
          refreshSave();
        });
      }
    };

    draw();
    if (options.target) {
      state.lastLookup = {};
      lookup();
    }
    if (state.listId !== undefined) chooseList(state.listId);
    return dialog;
  };

  /** Sends a slot dialog's targets; a target that is on the list already asks before it overwrites it. */
  FarmListPage.prototype.saveSlots = function (options, state, ids, dialog, fail) {
    var self = this;
    var slots = [];
    var edit = options.type === "edit";
    var duplicate = options.type === "duplicate";
    var base = { listId: state.listId, x: state.target.x, y: state.target.y, units: state.troops, active: state.active, abandoned: false };
    if (options.type === "create") slots.push(base);
    if (edit) {
      base.abandoned = !!options.abandoned;
      ids.forEach(function (id) { slots.push(Object.assign({ id: id }, base)); });
    }
    if (duplicate) {
      var from = options.list;
      ids.forEach(function (id) {
        var slot = from.slots.filter(function (s) { return s.id === id; })[0];
        var units = {};
        UNITS.forEach(function (unit) {
          units[unit] = state.troops[unit] !== undefined ? state.troops[unit] : slot.troop[unit];
        });
        slots.push({ listId: state.listId === undefined ? slot.id : state.listId, x: state.target.x !== undefined ? state.target.x : slot.target.x, y: state.target.y !== undefined ? state.target.y : slot.target.y, active: !!state.active && slot.isActive, units: units });
      });
    }
    var method = edit ? "PUT" : "POST";
    var done = function (response) {
      self.selected[options.list ? options.list.id : state.listId] = [];
      self.applyResponse(response);
      if (self.onSlotSaved) self.onSlotSaved();
      dialog.close();
    };
    this.api("farm-list/slot", method, { slots: slots }, done, function (error) {
      if (error && error.error === "raidList.targetExists") {
        self.confirm({
          message: self.L("errorVillageAlreadyInRaidListQuestion"), confirmText: self.L("ok"), cancelText: self.L("cancel_with_capital_c"), appearance: "green",
          onConfirm: function () {
            self.api("farm-list/slot?force", method, { slots: slots }, done, function (forced) { fail(forced && forced.message); });
          },
        });
      } else fail(error && error.message);
    });
  };

  /** Travel time as hh:mm:ss. */
  FarmListPage.prototype.duration = function (seconds) {
    return pad(Math.floor(seconds / 3600)) + ":" + pad(Math.floor(seconds / 60) % 60) + ":" + pad(Math.floor(seconds % 60));
  };

  /** The village-name search of the target selection: results with coordinates and owner, arrows and enter choose one. */
  FarmListPage.prototype.bindNameSearch = function (input, form, onSelect, state, onClear) {
    var self = this;
    var label = input.closest("label");
    var results = [];
    var focused;
    var timer;
    var last = "";
    var render = function () {
      var node = label.querySelector(".results");
      if (node) node.remove();
      if (results.length === 0) return;
      node = document.createElement("div");
      node.className = "results";
      node.innerHTML = results.map(function (row, index) {
        return (
          '<div class="autoCompleteResult typeVillage ' + (index === focused ? "focused" : "") + '" data-index="' + index + '"><div class="villageName">' + esc(row.name) + '</div><div class="coordinatesWrapper">' +
          self.coordinates(row.x, row.y, true) + '</div><div class="player">' + esc(row.playerName || "") + "</div></div>"
        );
      }).join("");
      label.appendChild(node);
      node.querySelectorAll(".autoCompleteResult").forEach(function (item) {
        item.addEventListener("mouseover", function () { focused = parseInt(item.getAttribute("data-index"), 10); markFocus(); });
        item.addEventListener("click", choose);
      });
    };
    var markFocus = function () {
      label.querySelectorAll(".autoCompleteResult").forEach(function (item, index) { item.classList.toggle("focused", index === focused); });
    };
    var choose = function () {
      if (focused === undefined) return;
      var row = results[focused];
      results = [];
      focused = undefined;
      render();
      input.value = row.name;
      last = row.name;
      onSelect(row);
    };
    var search = function (term) {
      self.api("autocomplete/villagename", "POST", { search: term, limit: 20, context: "villagename" }, function (rows) {
        var exact = rows.filter(function (row) { return row.name.toLowerCase() === term.toLowerCase(); });
        results = rows;
        focused = exact.length === 1 && rows.length === 1 ? 0 : undefined;
        if (focused === 0) {
          choose();
          return;
        }
        render();
      }, function () {
        results = [];
        render();
      });
    };
    input.addEventListener("keydown", function (event) {
      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        if (results.length === 0) return;
        focused = focused === undefined ? (event.key === "ArrowDown" ? 0 : results.length - 1) : (focused + (event.key === "ArrowDown" ? 1 : -1) + results.length) % results.length;
        markFocus();
      } else if (event.key === "Enter") {
        event.preventDefault();
        choose();
      } else if (event.key === "Escape") {
        results = [];
        render();
      } else if (event.key === "Tab") {
        results = [];
        render();
      }
    });
    input.addEventListener("input", function () {
      clearTimeout(timer);
      if (input.value.trim() === "") onClear();
      timer = setTimeout(function () {
        var term = input.value.trim();
        if (term.length >= 2 && term !== last) {
          last = term;
          search(term);
        } else if (term.length < 2) {
          results = [];
          render();
        }
      }, 333);
    });
  };

  FarmListPage.prototype.listById = function (id) {
    return this.own.farmLists.concat(this.own.abandonedFarmLists).filter(function (list) { return list.id === id; })[0];
  };

  /**
   * Opens the target dialog from anywhere in the game (the map, a report, the profile): options are the reference's
   * { type: "create" | "edit", listId, coordinates: { x, y }, editSlotIds, onSuccess }. On the farm list page the
   * page itself is used, elsewhere the page data is fetched first.
   */
  function openSlotDialog(options) {
    var open = function (page) {
      var list = options.listId === undefined ? undefined : page.listById(options.listId);
      if (options.type === "edit" && list) {
        page.onSlotSaved = options.onSuccess;
        page.editSlots(list, options.editSlotIds, "edit", false);
        return;
      }
      page.onSlotSaved = options.onSuccess;
      page.openSlotDialog({ type: "create", list: list, target: options.coordinates ? { x: options.coordinates.x, y: options.coordinates.y } : undefined, abandoned: false });
    };
    if (Travian.Game.FarmList.current) {
      open(Travian.Game.FarmList.current);
      return;
    }
    Travian.api("farm-list", { action: "farmList", success: function (response) {
      var config = Object.assign({ viewData: response.viewData }, response.config);
      open(new FarmListPage(config));
    } }, "GET");
  }

  window.Travian = window.Travian || {};
  Travian.Game = Travian.Game || {};
  Travian.Game.FarmList = {
    Model: Model,
    Page: FarmListPage,
    SLOT_DIALOG_TYPE_CREATE: "create",
    SLOT_DIALOG_TYPE_EDIT: "edit",
    openSlotDialog: openSlotDialog,
    current: null,
    render: function (config) {
      var page = new FarmListPage(config);
      Travian.Game.FarmList.current = page;
      page.render();
      return page;
    },
  };
})();
