"use strict";

// The hero inventory's use dialog (extracted from the script and run against a minimal jQuery stand-in): an
// artwork shows its culture points and is used one at a time, a cooldown opens no dialog, other consumables with an
// amount field keep bulk use, and the Bucket's error disables its button.
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

(async () => {

const source = fs.readFileSync(`${__dirname}/../../game/deployment/public/game-client.js`, "utf8");
const start = source.indexOf("  Inventory.prototype.useDialog = function (item) {");
const end = source.indexOf("  /* ------------------------------------------------- resource transfer UI */", start);
assert.ok(start > 0 && end > start);

function run(item, amountValue) {
  const state = { dialog: null, request: null, requests: [], toast: null, handlers: {}, closed: false };
  const chain = (selector) => {
    const object = {
      length: selector.includes("input") && amountValue !== undefined ? 1 : selector.includes("input") ? 0 : 1,
      append: () => object, clone: () => object, first: () => object, html: () => "", text: () => object,
      removeAttr: () => object, removeClass: () => object,
      val: () => amountValue,
      find: (child) => chain(child),
      on: (events, handler) => { state.handlers[`${selector}|${events.split(" ")[0]}`] = handler; return object; },
    };
    return object;
  };
  const context = {
    Inventory: function () {},
    SLOT_PLACE: { bag: -6 },
    escapeHtml: (text) => String(text).replace(/</g, "&lt;"),
    refreshHud: () => {},
    Travian: {
      Dialog: { Dialog: function (options) {
        this.options = options;
        this.setContent = (content) => { this.content = content; };
        this.show = () => {};
        this.close = () => { state.closed = true; };
        state.dialog = this;
      } },
      Game: { Toast: { show: (options) => { state.toast = options; } } },
      api: (route, options) => { state.request = { route, options }; state.requests.push({ route, options }); },
    },
    jQuery: (selector) => chain(typeof selector === "string" ? selector : "<element>"),
  };
  vm.runInNewContext(source.slice(start, end), context);
  const inventory = new context.Inventory();
  inventory.setItems = () => {};
  inventory.options = { villageId: 123, text: { culturePointsGainFromItems: "CP gained", buttonCancel: "Cancel" } };
  inventory.useDialog(item);
  return { state, inventory };
}

const descriptor = (overrides) => ({ description: "d", confirm: "Use", input: false, label: "Number of items", impacts: [], equip: false, healthDelta: 0, error: null, ...overrides });

// Artwork: culture points shown, no amount field, one item per use.
let result = run({ id: 1, name: "Artwork", amount: 10, dialog: descriptor(), artwork: { cp: 800, availableAt: 0, cooldownText: "Not yet" } });
assert.ok(result.state.dialog.content.includes("CP gained"));
assert.ok(result.state.dialog.content.includes(">800<"));
assert.ok(!result.state.dialog.content.includes("consumableHeroItem"));
result.state.handlers["button.green|click"]();
assert.equal(result.state.request.route, "hero/v2/inventory/use-item");
assert.equal(result.state.request.options.data.amount, 1, "Artwork stacks must submit one item.");

// Cooldown: a toast, no dialog, no request.
result = run({ id: 1, name: "Artwork", amount: 10, dialog: descriptor(), artwork: { cp: 800, availableAt: Math.floor(Date.now() / 1000) + 3600, cooldownText: "Not yet" } });
assert.equal(result.state.toast.text, "Not yet");
assert.equal(result.state.dialog, null, "Cooldown must not open a confirmation.");
assert.equal(result.state.request, null);

// A consumable with an amount field keeps bulk use.
result = run({ id: 2, name: "Scroll", amount: 10, dialog: descriptor({ input: true }) }, "10");
assert.ok(result.state.dialog.content.includes("consumableHeroItem"));
result.state.handlers["button.green|click"]();
assert.equal(result.state.request.options.data.amount, 10, "Other consumables must retain bulk use.");

// Impact rows: a count row follows the amount field, a value row does not.
result = run({ id: 4, name: "Scroll", amount: 4, dialog: descriptor({ input: true, impacts: [
  { kind: "value", label: "Experience gained", value: 100 },
  { kind: "experience", label: "Hero experience after usage:", from: 1000, perItem: 110 },
] }) }, "2");
assert.ok(result.state.dialog.content.includes('class="value">100<'));
assert.ok(result.state.dialog.content.includes('class="to">1440<'), "the experience row shows the amount of the whole stack at first");

// A hurt hero: the ointments that heal are used, the rest is equipped once the first request answered.
result = run({ id: 5, name: "Ointment", amount: 8, dialog: descriptor({ input: true, healthDelta: 5 }) }, "8");
result.state.handlers["button.green|click"]();
assert.equal(result.state.requests.length, 1);
assert.equal(result.state.requests[0].route, "hero/v2/inventory/use-item");
assert.equal(result.state.requests[0].options.data.amount, 5, "Only the ointments that heal are used.");
result.state.requests[0].options.success({ inventorySize: 16, items: [] });
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(result.state.requests.length, 2);
assert.equal(result.state.requests[1].route, "hero/v2/inventory/move-item");
assert.equal(result.state.requests[1].options.data.amount, 3, "The rest is equipped.");
assert.equal(result.state.requests[1].options.data.targetPlaceId, -6, "Equipping targets the bag slot.");

// A healthy hero equips them all; bandages equip through the same request.
result = run({ id: 6, name: "Ointment", amount: 8, dialog: descriptor({ input: true, equip: true }) }, "8");
result.state.handlers["button.green|click"]();
assert.equal(result.state.requests.length, 1);
assert.equal(result.state.requests[0].route, "hero/v2/inventory/move-item");
assert.equal(result.state.requests[0].options.data.amount, 8);

// The Bucket's error shows and keeps the confirm button from sending anything.
result = run({ id: 3, name: "Bucket", amount: 1, dialog: descriptor({ confirm: "Consume", error: "Alive" }) });
assert.ok(result.state.dialog.content.includes('class="error">Alive'));
assert.ok(result.state.dialog.content.includes("disabled"));
result.state.handlers["button.green|click"]();
assert.equal(result.state.request, null, "A disabled confirm must not use the item.");

console.log("Hero use dialog: artwork, cooldown, bulk use, ointment use-then-equip and the Bucket error passed.");
})();
