const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const root = `${__dirname}/../..`;

function element() {
  return {
    style: {},
    appendChild() {},
    insertBefore() {},
    setAttribute() {},
  };
}

const sandbox = {
  clearTimeout,
  console,
  setTimeout,
};
sandbox.window = sandbox;
sandbox.self = sandbox;
sandbox.globalThis = sandbox;
sandbox.document = {
  addEventListener() {},
  body: element(),
  createElement: element,
  head: element(),
  querySelector() {
    return null;
  },
  removeEventListener() {},
};

vm.createContext(sandbox);

for (const runtime of [
  "game/deployment/public/js/default/popper.js",
  "game/deployment/public/js/default/tippy.js",
]) {
  vm.runInContext(fs.readFileSync(`${root}/${runtime}`, "utf8"), sandbox, {
    filename: runtime,
  });
}

assert.equal(typeof sandbox.Popper, "object", "Popper must expose its browser global.");
assert.equal(typeof sandbox.tippy, "function", "Tippy must expose the global used by game-client.js.");
assert.equal(
  typeof sandbox.tippy.setDefaultProps,
  "function",
  "Tippy must expose game-client.js's initialization API.",
);
assert.equal(
  typeof sandbox.tippy.hideAll,
  "function",
  "Tippy must expose game-client.js's tooltip cleanup API.",
);

// Exercise the two global calls made by Travian.Tip.init() and
// Travian.Tip.hide(). Merely checking the exported names would miss a broken
// UMD dependency boundary that still leaves a function-shaped placeholder.
sandbox.tippy.setDefaultProps({
  allowHTML: true,
  followCursor: true,
  theme: "travian",
});
assert.equal(sandbox.tippy.defaultProps.theme, "travian");
assert.equal(sandbox.tippy.defaultProps.allowHTML, true);
assert.equal(sandbox.tippy.defaultProps.followCursor, true);
sandbox.tippy.hideAll();

console.log("Tooltip client runtime contract passed.");
