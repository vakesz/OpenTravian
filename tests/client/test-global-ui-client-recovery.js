const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const root = `${__dirname}/../..`;
const clientFiles = [
  "game/deployment/public/game-client.js",
];

function method(source, startMarker, endMarker, replacement) {
  const start = source.indexOf(startMarker);
  const end = source.indexOf(endMarker, start);
  assert.notEqual(start, -1, `Missing ${startMarker}.`);
  assert.notEqual(end, -1, `Missing ${endMarker} after ${startMarker}.`);

  return source
    .slice(start, end)
    .replace(startMarker, replacement)
    .replace(/\},\s*$/, "}");
}

function assertGoldRefreshFailureRecovers(clientFile, source) {
  const updateGold = method(
    source,
    "updateGold: function (e) {",
    "\n    updateShopNotification:",
    "function updateGold(e) {",
  );
  const sandbox = {
    Travian: {
      Game: { Layout: { goldIsUpdating: false } },
      api(_route, options) {
        options.error({
          error: true,
          message: "Gold balance request rejected.",
        });
      },
    },
    jQuery() {
      return {
        first() {
          return this;
        },
        html() {
          return "0";
        },
      };
    },
  };

  vm.runInNewContext(updateGold, sandbox, { filename: clientFile });
  sandbox.updateGold();

  assert.equal(
    sandbox.Travian.Game.Layout.goldIsUpdating,
    false,
    `${clientFile} must release the gold refresh lock after a rejected request.`,
  );
}

function assertApiErrorsAlwaysHaveAMessage(clientFile, source) {
  const api = method(
    source,
    "api: function (e, t, i, n) {",
    "\n  getDirection:",
    "function api(e, t, i, n) {",
  );
  let received;
  const sandbox = {
    Travian: {
      Defaults: {},
      emptyFunction() {},
    },
    jQuery: {
      ajax(options) {
        options.error({
          responseJSON: { error: { errorMsg: "Request denied." } },
        });
      },
    },
  };

  vm.runInNewContext(api, sandbox, { filename: clientFile });
  sandbox.api("route", {
    error(response) {
      received = response;
    },
  });

  assert.equal(
    received.message,
    "Request denied.",
    `${clientFile} must expose standalone API error text to client dialogs.`,
  );

  sandbox.jQuery.ajax = (options) => options.error({});
  sandbox.api("route", {
    error(response) {
      received = response;
    },
  });
  assert.equal(
    received.message,
    "Request failed.",
    `${clientFile} must give client dialogs a network-failure fallback message.`,
  );
}

function assertAddressBookFailureIsVisible(clientFile, source) {
  const call = method(
    source,
    "call: function (e, t) {",
    "\n    add: function (e) {",
    "function call(e, t) {",
  );
  const dialogs = [];
  class Dialog {
    constructor(options) {
      this.options = options;
      dialogs.push(this);
    }

    setContent(content) {
      this.content = content;
      return this;
    }

    show() {
      this.shown = true;
      return this;
    }
  }
  const addressBookDialog = {
    disableCount: 0,
    enableCount: 0,
    disableForm() {
      this.disableCount += 1;
    },
    enableForm() {
      this.enableCount += 1;
    },
  };
  const sandbox = {
    Travian: {
      Dialog: { Dialog },
      api(_route, options) {
        options.error({
          error: true,
          message: "Address-book update rejected.",
        });
        options.complete();
      },
    },
  };

  vm.runInNewContext(call, sandbox, { filename: clientFile });
  sandbox.call.call(
    { entrypoint: "addressbook", dialog: addressBookDialog },
    "/delete",
    17,
  );

  assert.equal(
    addressBookDialog.disableCount,
    1,
    `${clientFile} must disable the address-book form while saving.`,
  );
  assert.equal(
    addressBookDialog.enableCount,
    1,
    `${clientFile} must re-enable the address-book form after a rejected save.`,
  );
  assert.equal(
    dialogs.length,
    1,
    `${clientFile} must create an error dialog for a rejected address-book update.`,
  );
  assert.equal(
    dialogs[0].content,
    "Address-book update rejected.",
    `${clientFile} must show the address-book failure message.`,
  );
  assert.equal(
    dialogs[0].shown,
    true,
    `${clientFile} must show the address-book failure dialog.`,
  );
}

for (const clientFile of clientFiles) {
  const source = fs.readFileSync(`${root}/${clientFile}`, "utf8");
  assertGoldRefreshFailureRecovers(clientFile, source);
  assertApiErrorsAlwaysHaveAMessage(clientFile, source);
  assertAddressBookFailureIsVisible(clientFile, source);
}

console.log("Global UI client recovery contract passed.");
