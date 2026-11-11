const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = `${__dirname}/../..`;
const clientFiles = [
    'game/deployment/public/game-client.js',
];

function method(source, startMarker, endMarker, replacement) {
    const start = source.indexOf(startMarker);
    const end = source.indexOf(endMarker, start);
    assert.notEqual(start, -1, `Missing ${startMarker}.`);
    assert.notEqual(end, -1, `Missing ${endMarker} after ${startMarker}.`);

    return source
        .slice(start, end)
        .replace(startMarker, replacement)
        .replace(/\}\),\s*$/, '}')
        .replace(/\},\s*$/, '}');
}

function classControl(...classes) {
    const values = new Set(classes);
    const splitClasses = (names) => names.flatMap((name) => name.split(/\s+/));

    return {
        addClass(...names) {
            splitClasses(names).forEach((name) => values.add(name));
            return this;
        },
        hasClass(name) {
            return values.has(name);
        },
        removeClass(...names) {
            splitClasses(names).forEach((name) => values.delete(name));
            return this;
        },
        toggleClass(name) {
            if (values.has(name)) {
                values.delete(name);
            } else {
                values.add(name);
            }
            return this;
        },
    };
}

function assertInventoryFailureRecovers(clientFile, source) {
    const moveItem = method(
        source,
        'Inventory.prototype.moveItem = function (item, targetPlaceId, amount) {',
        '  /* Refetch the inventory',
        'function moveItem(item, targetPlaceId, amount) {',
    );
    const toasts = [];
    const root = classControl();
    const sandbox = {
        Travian: {
            Game: {Toast: {show: (toast) => toasts.push(toast)}},
            api(route, options) {
                assert.equal(route, 'hero/v2/inventory/move-item');
                assert.deepEqual(Object.keys(options.data).sort(), ['action', 'amount', 'itemId', 'targetPlaceId']);
                options.error({message: 'Inventory update rejected.'});
                options.complete();
            },
        },
        escapeHtml: (value) => String(value),
        refreshHud() {},
    };
    sandbox.self = {busy: false, root, setItems() {}, options: {}};
    vm.runInNewContext(`${moveItem}\nmoveItem.call(self, {id: 7}, -1, 1);`, sandbox, {filename: clientFile});

    assert.equal(sandbox.self.busy, false, `${clientFile} must unlock the inventory after a rejected move.`);
    assert.equal(root.hasClass('loading'), false, `${clientFile} must clear the loading state after a rejected move.`);
    assert.equal(toasts.length, 1, `${clientFile} must show a rejected inventory move.`);
}

function assertGenderFailureRecovers() {
    const file = 'game/deployment/public/hero-v2-appearance.js';
    const source = fs.readFileSync(`${root}/${file}`, 'utf8');
    const domClasses = (...initial) => {
        const values = new Set(initial);
        return {
            add: (...names) => names.forEach((name) => values.add(name)),
            remove: (...names) => names.forEach((name) => values.delete(name)),
            contains: (name) => values.has(name),
        };
    };
    const preview = {classList: domClasses('male', 'zoomedIn')};
    const toggle = {classList: domClasses('male')};
    let dialogCount = 0;
    const enabledIdentifiers = [];
    const sandbox = {
        Travian: {
            Dialog: {
                Dialog: class {
                    setContent() {
                        return this;
                    }
                    show() {
                        dialogCount += 1;
                    }
                },
            },
            Form: {
                UnloadHelper: {
                    disableSecurity() {},
                    enableSecurity(identifier) {
                        enabledIdentifiers.push(identifier);
                        return identifier ?? 1;
                    },
                },
            },
            api(_route, options) {
                options.error({error: true, message: 'Appearance preview rejected.'});
            },
        },
        console,
    };
    sandbox.window = sandbox;
    vm.runInNewContext(source, sandbox, {filename: file});

    const editor = Object.create(sandbox.HeroAppearanceEditor.prototype);
    editor.root = {querySelectorAll: () => [preview, toggle]};
    editor.options = {command: 'heroEditor'};
    editor.attributes = {gender: 'male', hairStyle: 1003};
    editor.unloadIdentifier = 7;
    editor.sync = () => {};

    editor.switchGender('female');

    assert.equal(editor.attributes.gender, 'male', `${file} must restore the server-approved hero gender after a rejected preview.`);
    assert.equal(preview.classList.contains('male'), true, `${file} must restore the hero preview class after a rejected gender preview.`);
    assert.equal(preview.classList.contains('female'), false, `${file} must remove the rejected hero preview class.`);
    assert.equal(toggle.classList.contains('male'), true, `${file} must restore the gender button state.`);
    assert.equal(editor.attributes.hairStyle, 1003, `${file} must restore the previous face after a rejected preview.`);
    assert.equal(dialogCount, 1, `${file} must show a rejected gender-preview error.`);

    editor.request('save');

    assert.equal(editor.unloadIdentifier, 7, `${file} must restore the unsaved-change warning after a rejected save.`);
    assert.equal(enabledIdentifiers.includes(7), true, `${file} must re-enable save protection after a rejected save.`);
    assert.equal(dialogCount, 2, `${file} must show a rejected hero-save error.`);
}

function fakeElement(attributes = {}) {
    const listeners = {};
    const element = {
        attributes: {...attributes},
        classList: classControl(),
        style: {},
        children: {},
        disabled: false,
        textContent: '',
        value: '',
        checked: false,
        getAttribute(name) {
            return name in this.attributes ? this.attributes[name] : null;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        querySelector(selector) {
            return this.children[selector] ?? null;
        },
        addEventListener(name, handler) {
            (listeners[name] ||= []).push(handler);
        },
        removeEventListener() {},
        fire(name, event = {}) {
            (listeners[name] || []).forEach((handler) => handler({preventDefault() {}, ...event}));
        },
    };
    element.classList.toggle = function (name, force) {
        if (force === undefined ? !this.hasClass(name) : force) {
            this.addClass(name);
        } else {
            this.removeClass(name);
        }
        return this;
    };

    return element;
}

function assertAttributeFailureRecovers() {
    const source = fs.readFileSync(`${root}/game/deployment/public/hero-attributes.js`, 'utf8');
    const rootElement = fakeElement({'data-available-points': '8', 'data-resource': '0', 'data-attack-behaviour': 'fight'});
    const pointsAvailable = fakeElement();
    rootElement.children['.pointsAvailable'] = pointsAvailable;
    const rows = [['power', 2, 100, 1], ['offBonus', 0, 0, 0.2], ['defBonus', 0, 0, 0.2], ['productionPoints', 10, 0, 1]].map(
        ([name, initial, base, multiplier]) => {
            const bar = fakeElement({
                'data-initial': String(initial), 'data-base': String(base), 'data-multiplier': String(multiplier),
                'data-decimals': '0', 'data-suffix': '',
            });
            const input = fakeElement();
            input.name = name;
            input.value = String(initial);
            const total = fakeElement();
            const ratio = fakeElement();
            ratio.children.input = input;
            ratio.children['.denominator .value'] = total;
            bar.nextElementSibling = ratio;
            const parts = {
                '.filling.primary': fakeElement(), '.filling.secondary': fakeElement(), '.handle': fakeElement(),
                'button.plus': fakeElement(), 'button.minus': fakeElement(), '.slider': fakeElement(),
                '.bar': Object.assign(fakeElement(), {getBoundingClientRect: () => ({left: 0, width: 200})}),
            };
            Object.assign(bar.children, parts);

            return {bar, input, parts};
        },
    );
    rootElement.querySelectorAllResult = rows.map((row) => row.bar);
    const saveButton = fakeElement();
    saveButton.disabled = true;
    const hideSwitch = fakeElement();
    const productionButtons = [0, 1].map((resource) => fakeElement({'data-resource': String(resource)}));
    const toasts = [];
    const requests = [];
    const sandbox = {
        document: {
            readyState: 'complete',
            dir: 'ltr',
            body: {classList: classControl()},
            querySelector: (selector) => (selector.includes('.attributes') ? rootElement : hideSwitch),
            getElementById: () => saveButton,
            querySelectorAll: () => productionButtons,
        },
        window: {addEventListener() {}, removeEventListener() {}, location: {reload() {}}},
        Travian: {
            api(route, options) {
                requests.push({route, data: options.data});
                options.error({message: 'Hero attributes were rejected.'});
            },
            Game: {Toast: {show: (toast) => toasts.push(toast)}},
        },
        jQuery: () => ({text() { return {html: () => 'Hero attributes were rejected.'}; }}),
        Number, Array, Math,
    };
    rootElement.querySelectorAll = () => rootElement.querySelectorAllResult;
    vm.runInNewContext(source, sandbox, {filename: 'hero-attributes.js'});

    const [power, offBonus] = rows;
    assert.equal(saveButton.disabled, true, 'Save must stay disabled until something changes.');
    assert.equal(power.parts['button.minus'].disabled, true, 'Minus must be disabled at the saved value.');
    assert.equal(power.parts['button.plus'].disabled, false, 'Plus must be enabled while points remain.');

    power.parts['button.plus'].fire('click');
    assert.equal(power.input.value, '3');
    assert.equal(pointsAvailable.textContent, '7');
    assert.equal(power.parts['.filling.secondary'].style.width, '1%');
    assert.equal(power.parts['.handle'].style.marginInlineStart, '3%');
    assert.equal(power.parts['button.minus'].disabled, false);
    assert.equal(saveButton.disabled, false, 'Save must enable after a change.');

    power.parts['button.minus'].fire('click');
    assert.equal(saveButton.disabled, true, 'Undoing the change must disable Save again.');

    offBonus.input.value = '500';
    offBonus.input.fire('change');
    assert.equal(offBonus.input.value, '8', 'A typed value must clamp to the unspent points.');
    assert.equal(pointsAvailable.textContent, '0');
    assert.equal(power.parts['button.plus'].disabled, true, 'Plus must disable once all points are spent.');

    productionButtons[1].fire('click');
    assert.equal(productionButtons[1].classList.hasClass('orange'), true);
    assert.equal(productionButtons[1].classList.hasClass('grey'), false);

    saveButton.fire('click');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].route, 'hero/attributes');
    assert.deepEqual(
        JSON.parse(JSON.stringify(requests[0].data)),
        {resource: '1', attackBehaviour: 'fight', attributes: {power: 2, offBonus: 8, defBonus: 0, productionPoints: 10}},
    );
    assert.equal(saveButton.disabled, false, 'A rejected save must leave the form editable.');
    assert.equal(toasts.length, 1, 'A rejected save must be shown.');
}

function assertRewardCollectionStaysInTheDialog(clientFile, source) {
    const collectReward = method(
        source,
        'collectReward: function (button) {',
        '\n  }))',
        'function collectReward(button) {',
    );
    const run = (outcome) => {
        const log = [];
        const timers = [];
        const texts = {h1: {text: null, set(value) { this.text = value; }}, 'h1 + div': {text: null, set(value) { this.text = value; }}};
        const collection = (name, attributes = {}) => {
            const control = {
                addClass(value) { log.push([name, 'addClass', value]); return control; },
                removeClass(value) { log.push([name, 'removeClass', value]); return control; },
                prop(property, value) { log.push([name, 'prop', property, value]); return control; },
                attr(attribute) { return attributes[attribute]; },
                each(callback) { Object.values(texts).forEach((entry) => callback.call(entry)); return control; },
                text(value) { this.set(value); return control; },
            };
            return control;
        };
        const screen = {
            find(selector) {
                return selector === '.rewardImage' ? collection('image', {class: 'rewardImage reward50 achieved'}) : collection(selector);
            },
        };
        const button = collection('button');
        button.closest = () => screen;
        const sandbox = {
            parseInt,
            window: {setTimeout(callback) { timers.push(callback); }},
            jQuery: (target) => (typeof target === 'string' ? collection(target) : {attr: () => 'Reward already claimed!', text(value) { target.set(value); }}),
            Travian: {
                api(route, options) {
                    log.push(['api', route, JSON.parse(JSON.stringify(options.data))]);
                    if (outcome.error) {
                        options.error({message: 'refused'});
                    } else {
                        options.success({claimable: outcome.claimable});
                    }
                },
            },
        };
        vm.runInNewContext(collectReward, sandbox, {filename: clientFile});
        const quest = {overview: 0, claimedMilestone: null, openOverview() { this.overview += 1; }};
        sandbox.collectReward.call(quest, button);

        return {log, timers, quest, texts};
    };

    const refused = run({error: true});
    assert.deepEqual(refused.log[1], ['api', 'daily-quest/award', {action: 'dailyQuest', questId: 'DailyQuestsReward_02'}], `${clientFile} must claim through daily-quest/award.`);
    assert.equal(refused.quest.overview, 1, `${clientFile} must go back to the overview when a claim is refused, as the reference does.`);
    assert.equal(refused.quest.claimedMilestone, null);

    const last = run({claimable: false});
    assert.equal(last.quest.overview, 0, `${clientFile} must keep the dialog on the reward screen after a claim.`);
    assert.equal(last.quest.claimedMilestone, 2);
    assert.deepEqual(last.log.filter((entry) => entry[1] === 'addClass').map((entry) => entry.join(':')), ['button:addClass:collected', '.iconValueBox:addClass:animated', 'a.dailyQuests .indicator, label.mobileMenuButton .indicator:addClass:hidden']);
    assert.equal(last.timers.length, 1, `${clientFile} must settle the button two seconds after the claim.`);
    last.timers[0]();
    assert.ok(last.log.some((entry) => entry[0] === 'button' && entry[1] === 'removeClass' && entry[2] === 'collectable'));
    assert.equal(last.texts.h1.text, 'Reward already claimed!', `${clientFile} must turn the screen into "already claimed" after the claim.`);

    const more = run({claimable: true});
    assert.equal(more.log.some((entry) => entry[2] === 'hidden'), false, `${clientFile} must keep the header indicator while a milestone is left.`);
}

function assertAdventureDurationFailureIsVisible(clientFile, source) {
    const adventureList = method(
        source,
        '(Travian.AdventureList = function () {',
        '\n  (Travian.Game.HeroAdventure = {',
        'function AdventureList() {',
    );
    let dialogCount = 0;
    const durationCalculations = classControl('hide');
    const adventureListForm = {
        find() {
            return [{value: 'adventure-1'}];
        },
    };
    const sandbox = {
        Travian: {
            Dialog: {
                Dialog: class {
                    setContent(message) {
                        assert.equal(message, 'Adventure duration lookup rejected.');
                        return this;
                    }
                    show() {
                        dialogCount += 1;
                    }
                },
            },
            api(route, options) {
                assert.equal(route, 'adventure/calculate-duration');
                assert.equal(typeof options.error, 'function');
                options.error({message: 'Adventure duration lookup rejected.'});
            },
        },
        jQuery(selector) {
            if (selector === '#durationCalculations') {
                return durationCalculations;
            }
            if (selector === '#adventureListForm') {
                return adventureListForm;
            }
            if (selector === '#changeVillage') {
                return {val: () => '4'};
            }
            throw new Error(`Unexpected selector: ${selector}`);
        },
    };
    sandbox.jQuery.map = (items, callback) => items.map(callback);

    vm.runInNewContext(adventureList, sandbox, {filename: clientFile});
    const controller = new sandbox.AdventureList();
    controller.openDurationsCalulator();

    assert.equal(dialogCount, 1, `${clientFile} must show a rejected adventure-duration lookup.`);
}

for (const clientFile of clientFiles) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    assertInventoryFailureRecovers(clientFile, source);
    assertRewardCollectionStaysInTheDialog(clientFile, source);
    assertAdventureDurationFailureIsVisible(clientFile, source);
}

assertGenderFailureRecovers();
assertAttributeFailureRecovers();

console.log('Hero failure recovery contract passed.');
