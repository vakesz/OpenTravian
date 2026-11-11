/*
 * Hero attributes page (/hero/attributes): point allocation, production focus,
 * hide switch and save, on the reference `.heroAttributes .attributes` markup.
 *
 * Plain DOM code (no jQuery) so it behaves identically on jQuery 3 and 4.
 * It posts {resource, attackBehaviour, attributes: {power, offBonus,
 * defBonus, productionPoints}} to hero/attributes; the server recomputes the
 * point budget, the values here only drive the preview.
 */
(function () {
  "use strict";

  var MAX_POINTS = 100;

  function reviveTimerText(seconds) {
    var remaining = Math.max(0, Math.floor(seconds));
    var hours = Math.floor(remaining / 3600);
    var minutes = Math.floor((remaining % 3600) / 60);
    var remainder = remaining % 60;
    return [hours, minutes, remainder]
      .map(function (part) { return String(part).padStart(2, "0"); })
      .join(":");
  }

  function initializeReviveTimer() {
    var card = document.querySelector("#content.heroV2Attributes .heroRevive[data-revive-end-time]");
    var timer = card && card.querySelector(".timerReact");
    var endsAt = card && Number(card.getAttribute("data-revive-end-time"));
    if (!timer || !Number.isFinite(endsAt) || endsAt <= 0) {
      return;
    }

    var refresh = function () {
      var remaining = Math.max(0, endsAt - Math.floor(Date.now() / 1000));
      timer.textContent = reviveTimerText(remaining);
      if (remaining === 0) {
        window.clearInterval(interval);
        window.location.reload();
      }
    };
    var interval = window.setInterval(refresh, 1000);
    refresh();
  }

  function initialize() {
    var root = document.querySelector("#content.heroV2Attributes .heroAttributes .attributes");
    if (!root || root.getAttribute("data-initialized") === "1") {
      return;
    }
    root.setAttribute("data-initialized", "1");

    var available = Number(root.getAttribute("data-available-points") || 0);
    var originalResource = Number(root.getAttribute("data-resource") || 0);
    var originalBehaviour = root.getAttribute("data-attack-behaviour") || "fight";
    var selectedResource = originalResource;
    var saveButton = document.getElementById("savePoints");
    var hideSwitch = document.querySelector("#content.heroV2Attributes .heroHideSwitch input[name=attackBehaviour]");
    var pointsAvailable = root.querySelector(".pointsAvailable");
    var productionButtons = document.querySelectorAll("#content.heroV2Attributes .changeProduction .pickProductionType");

    var rows = Array.prototype.map.call(root.querySelectorAll(".progressBar[data-initial]"), function (bar) {
      var ratio = bar.nextElementSibling;
      var input = ratio.querySelector("input");
      return {
        bar: bar,
        input: input,
        key: input.name,
        initial: Number(bar.getAttribute("data-initial")),
        base: Number(bar.getAttribute("data-base")),
        multiplier: Number(bar.getAttribute("data-multiplier")),
        decimals: Number(bar.getAttribute("data-decimals")),
        suffix: bar.getAttribute("data-suffix") || "",
        value: Number(input.value),
        primary: bar.querySelector(".filling.primary"),
        secondary: bar.querySelector(".filling.secondary"),
        handle: bar.querySelector(".handle"),
        plus: bar.querySelector("button.plus"),
        minus: bar.querySelector("button.minus"),
        total: ratio.querySelector(".denominator .value"),
      };
    });

    function allocated() {
      return rows.reduce(function (sum, row) {
        return sum + Math.max(0, row.value - row.initial);
      }, 0);
    }

    function format(row) {
      var number = (row.base + row.value * row.multiplier).toLocaleString("en-US", {
        minimumFractionDigits: row.decimals,
        maximumFractionDigits: row.decimals,
      });
      // Percentages are bidi-isolated exactly as the reference renders them: LRO LRO n PDF % PDF.
      return row.suffix === "%"
        ? "‭‭" + number + "‬%‬"
        : number + row.suffix;
    }

    function behaviour() {
      return hideSwitch && hideSwitch.checked ? "hide" : "fight";
    }

    function update() {
      var remaining = Math.max(0, available - allocated());
      pointsAvailable.textContent = String(remaining);
      rows.forEach(function (row) {
        row.input.value = String(row.value);
        row.primary.style.width = Math.min(MAX_POINTS, row.initial) + "%";
        row.secondary.style.width = Math.max(0, Math.min(MAX_POINTS, row.value) - row.initial) + "%";
        if (row.handle) {
          row.handle.style.marginInlineStart = Math.min(MAX_POINTS, row.value) + "%";
        }
        row.total.textContent = format(row);
        row.input.disabled = available === 0 || row.initial >= MAX_POINTS;
        if (row.plus) {
          row.plus.disabled = remaining === 0 || row.value >= MAX_POINTS;
        }
        if (row.minus) {
          row.minus.disabled = row.value <= row.initial;
        }
      });
      saveButton.disabled = !(allocated() > 0 || selectedResource !== originalResource || behaviour() !== originalBehaviour);
    }

    // Keep a row between its saved value and what the unspent points allow.
    function setValue(row, wanted) {
      var spentElsewhere = allocated() - Math.max(0, row.value - row.initial);
      var ceiling = Math.min(MAX_POINTS, row.initial + Math.max(0, available - spentElsewhere));
      var value = Number(wanted);
      row.value = Math.min(Math.max(Number.isFinite(value) ? Math.round(value) : row.initial, row.initial), ceiling);
      update();
    }

    rows.forEach(function (row) {
      if (row.plus) {
        row.plus.addEventListener("click", function () {
          if (!row.plus.disabled) {
            setValue(row, row.value + 1);
          }
        });
      }
      if (row.minus) {
        row.minus.addEventListener("click", function () {
          if (!row.minus.disabled) {
            setValue(row, row.value - 1);
          }
        });
      }
      row.input.addEventListener("input", function () {
        row.input.value = row.input.value.replace(/[^0-9]/g, "");
      });
      row.input.addEventListener("change", function () {
        setValue(row, row.input.value);
      });
      row.input.addEventListener("keydown", function (event) {
        if (event.key === "Enter") {
          event.preventDefault();
          setValue(row, row.input.value);
        }
      });

      var slider = row.bar.querySelector(".slider");
      if (!slider) {
        return;
      }
      var move = function (event) {
        var box = row.bar.querySelector(".bar").getBoundingClientRect();
        var point = event.touches ? event.touches[0] : event;
        var ratio = (point.clientX - box.left) / box.width;
        if (document.dir === "rtl" || document.body.classList.contains("rtl")) {
          ratio = 1 - ratio;
        }
        setValue(row, Math.min(Math.max(ratio, 0), 1) * MAX_POINTS);
      };
      var stop = function () {
        ["mousemove", "touchmove"].forEach(function (name) {
          window.removeEventListener(name, move);
        });
        ["mouseup", "touchend"].forEach(function (name) {
          window.removeEventListener(name, stop);
        });
      };
      var start = function (event) {
        if (event.type === "mousedown" && event.button !== 0) {
          return;
        }
        event.preventDefault();
        move(event);
        window.addEventListener("mousemove", move);
        window.addEventListener("touchmove", move, { passive: false });
        window.addEventListener("mouseup", stop);
        window.addEventListener("touchend", stop);
      };
      slider.addEventListener("mousedown", start);
      slider.addEventListener("touchstart", start, { passive: false });
    });

    Array.prototype.forEach.call(productionButtons, function (button) {
      button.addEventListener("click", function () {
        var resource = Number(button.getAttribute("data-resource"));
        if (resource === selectedResource) {
          return;
        }
        selectedResource = resource;
        Array.prototype.forEach.call(productionButtons, function (other) {
          var active = other === button;
          other.classList.toggle("active", active);
          other.classList.toggle("orange", active);
          other.classList.toggle("grey", !active);
        });
        update();
      });
    });

    if (hideSwitch) {
      hideSwitch.addEventListener("change", update);
    }

    saveButton.addEventListener("click", function () {
      if (saveButton.disabled) {
        return;
      }
      var attributes = {};
      rows.forEach(function (row) {
        attributes[row.key] = row.value;
      });
      saveButton.disabled = true;
      Travian.api("hero/attributes", {
        data: { resource: String(selectedResource), attackBehaviour: behaviour(), attributes: attributes },
        success: function () {
          window.location.reload();
        },
        error: function (response) {
          update();
          Travian.Game.Toast.show({ type: "error", text: jQuery("<div>").text(response.message || "Request failed.").html() });
        },
      });
    });

    update();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initialize();
      initializeReviveTimer();
    }, { once: true });
  } else {
    initialize();
    initializeReviveTimer();
  }
})();
