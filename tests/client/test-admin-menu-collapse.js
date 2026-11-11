'use strict';
// Exercises game/deployment/public/admin-menu.js against a minimal fake DOM.
const assert = require('assert');
const path = require('path');
const {init, STORAGE_KEY} = require(path.join(__dirname, '../../game/deployment/public/admin-menu.js'));

function el(attrs = {}) {
    const listeners = {};
    return {
        attrs: {...attrs}, hidden: false, textContent: '', children: {},
        getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
        setAttribute(n, v) { this.attrs[n] = String(v); },
        addEventListener(t, f) { listeners[t] = f; },
        click() { listeners.click(); },
        querySelector(sel) { return this.children[sel] || null; },
    };
}
function build(ids, currentId, allButton = true) {
    const groups = ids.map((id) => {
        const g = el({'data-menu-group': id, ...(id === currentId ? {'data-current': '1'} : {})});
        g.children['.adminMenuToggle'] = el({'aria-expanded': 'true'});
        g.children['.adminMenuList'] = el();
        return g;
    });
    const all = allButton ? el() : null;
    if (all) all.hidden = true;
    const nav = el();
    nav.children['.adminMenuAll'] = all;
    nav.querySelectorAll = () => groups;
    return {nav, groups, all};
}
const open = (g) => g.children['.adminMenuToggle'].getAttribute('aria-expanded') === 'true';
const listHidden = (g) => g.children['.adminMenuList'].hidden;
function memoryStorage(seed) {
    const data = seed ? {[STORAGE_KEY]: JSON.stringify(seed)} : {};
    return {data, getItem: (k) => (k in data ? data[k] : null), setItem: (k, v) => { data[k] = v; }};
}

// Current category open, others closed, no saved state.
let ui = build(['a', 'b', 'c'], 'b');
let storage = memoryStorage();
init(ui.nav, storage);
assert.deepStrictEqual(ui.groups.map(open), [false, true, false]);
assert.deepStrictEqual(ui.groups.map(listHidden), [true, false, true]);
assert.strictEqual(ui.all.hidden, false);
assert.strictEqual(ui.all.textContent, 'Expand all');

// Toggle persists.
ui.groups[0].children['.adminMenuToggle'].click();
assert.ok(open(ui.groups[0]) && !listHidden(ui.groups[0]));
assert.strictEqual(JSON.parse(storage.data[STORAGE_KEY]).a, true);

// Saved state restored; current stays open even if saved closed.
ui = build(['a', 'b', 'c'], 'b');
init(ui.nav, memoryStorage({a: false, b: false, c: true}));
assert.deepStrictEqual(ui.groups.map(open), [false, true, true]);

// Expand all, then collapse all keeps the current category.
ui.all.click();
assert.ok(ui.groups.every(open));
assert.strictEqual(ui.all.textContent, 'Collapse all');
ui.all.click();
assert.deepStrictEqual(ui.groups.map(open), [false, true, false]);

// Throwing storage and corrupt saved JSON must not break the menu.
const throwing = {getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); }};
ui = build(['a', 'b'], 'a');
init(ui.nav, throwing);
ui.groups[1].children['.adminMenuToggle'].click();
assert.ok(open(ui.groups[1]));
ui = build(['a', 'b'], 'a');
init(ui.nav, {getItem: () => '{not json', setItem() {}});
assert.deepStrictEqual(ui.groups.map(open), [true, false]);

// No storage at all, and a page without the nav.
ui = build(['a'], null, false);
init(ui.nav, null);
assert.strictEqual(init(null, null), null);

console.log('Admin menu collapse test passed.');
