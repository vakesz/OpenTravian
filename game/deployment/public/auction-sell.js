/*
 * Selling from the Sell page, as in the reference: a click on an inventory item opens the "Sell item" dialog (item,
 * effects, selling prices and chart, a batch size for stackable items), and "Sell item" posts the sale. A stackable
 * item the player has too few of, or a failed sale, shows a toast instead.
 */
(function () {
  "use strict";

  if (window.AuctionSell) {
    return;
  }
  window.AuctionSell = true;

  function toast(type, text) {
    Travian.Game.Toast.show({ type: type, text: text });
  }

  function openDialog(itemId) {
    Travian.api(
      "auction/sell-dialog?itemId=" + encodeURIComponent(itemId),
      {
        success: function (answer) {
          show(itemId, answer);
        },
        error: function (answer) {
          toast("error", answer && answer.message ? answer.message : "");
        },
      },
      "get"
    );
  }

  // Like the reference after a sale: the inventory tiles and the "Your auctions" list update in place.
  function refreshAfterSale() {
    Travian.api(
      "auction/items?view=sell",
      {
        success: function (answer) {
          var holder = document.createElement("template");
          holder.innerHTML = answer.html;
          var fresh = holder.content.getElementById("itemsToSale");
          var current = document.getElementById("itemsToSale");
          if (fresh && current) {
            current.innerHTML = fresh.innerHTML;
          }
        },
      },
      "get"
    );
    var reload = document.querySelector('[data-auction-reload][data-auction-list="sell"]');
    if (reload) {
      reload.click();
    }
  }

  // Puts the item up for auction; the dialog closes and the page refreshes in place.
  function sellNow(dialog, itemId, amount) {
    Travian.api("auction/sell-item", {
      data: { id: itemId, amount: amount },
      success: function () {
        dialog.close();
        refreshAfterSale();
      },
      error: function (response) {
        toast("error", response && response.message ? response.message : "");
        dialog.close();
      },
    });
  }

  // The reference explains the auction house's fee before a sale, until the player ticks "Don't show again".
  function confirmFee(answer, button, proceed) {
    if (!answer.fee || answer.skipFee) {
      proceed();
      return;
    }
    var fee = new Travian.Dialog.Dialog({
      version: 2,
      cssClass: "plain confirmAuctionFeeDialog",
      buttonOk: false,
      buttonCancel: false,
      preventFormSubmit: true,
      enableBackground: true,
      relativeTo: jQuery(button),
    });
    fee.setContent(answer.feeHtml);
    fee.show();
    var root = jQuery(".confirmAuctionFeeDialog");
    root.find("button.cancel").on("click", function (event) {
      event.preventDefault();
      fee.close();
    });
    root.find("button.sell").on("click", function (event) {
      event.preventDefault();
      if (root.find("input[name=skipHint]").prop("checked")) {
        Travian.Game.Preferences.set("confirmedAuctionFeeAt", String(Math.floor(Date.now() / 1000)));
      }
      fee.close();
      proceed();
    });
  }

  function show(itemId, answer) {
    var dialog = new Travian.Dialog.Dialog({
      version: 2,
      cssClass: "basic sellItemDialog",
      buttonOk: false,
      buttonCancel: false,
      preventFormSubmit: true,
      enableBackground: true,
    });
    dialog.setContent(answer.html);
    dialog.show();

    var root = jQuery(".sellItemDialog");
    var select = root.find("select[name=batchSize]");
    var field = root.find("label.batchSize");
    var sell = root.find("button.sell");
    root.find("button.cancel").on("click", function (event) {
      event.preventDefault();
      dialog.close();
    });
    // The label sits on the field once a size is chosen; selling without one marks the field invalid.
    var pin = function () {
      field.find(".label").toggleClass("pinned", select.val() !== "");
    };
    pin();
    select.on("change", function () {
      sell.toggleClass("disabled", select.val() === "");
      pin();
    });
    sell.on("click", function (event) {
      event.preventDefault();
      if (select.length > 0 && select.val() === "") {
        field.addClass("invalid");
        field.find(".validation").addClass("show");
        return;
      }
      field.removeClass("invalid");
      field.find(".validation").removeClass("show");
      var amount = select.length > 0 ? parseInt(select.val(), 10) : 1;
      confirmFee(answer, sell[0], function () {
        sellNow(dialog, itemId, amount);
      });
    });
  }

  // Cancelling a fresh sale needs no confirmation: a toast reports it and the lists refresh.
  document.addEventListener("click", function (event) {
    var cancel = event.target.closest && event.target.closest("button[data-auction-cancel]");
    if (!cancel) {
      return;
    }
    Travian.api("auction/cancel", {
      data: { id: cancel.getAttribute("data-auction-cancel") },
      success: function () {
        toast("success", cancel.getAttribute("data-cancelled"));
        refreshAfterSale();
      },
      error: function (response) {
        toast("error", response && response.message ? response.message : "");
        refreshAfterSale();
      },
    });
  });

  document.addEventListener("click", function (event) {
    var tile = event.target.closest && event.target.closest("#itemsToSale .heroItem[data-amount]");
    if (!tile) {
      return;
    }
    var amount = parseInt(tile.getAttribute("data-amount"), 10);
    var minimum = parseInt(tile.getAttribute("data-min-amount"), 10);
    if (amount < minimum) {
      toast("warning", tile.parentElement.getAttribute("data-not-enough").replace("[MIN_AMOUNT]", String(minimum)));
      return;
    }
    openDialog(tile.id.replace("item_", ""));
  });
})();
