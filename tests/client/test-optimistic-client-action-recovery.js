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
        .replace(/\},\s*\}\),\s*$/, '}')
        .replace(/\}\),\s*$/, '}')
        .replace(/\},\s*$/, '}');
}

function assertTradeRouteFailureRecovers(clientFile, source) {
    const toggleTradeRoutes = method(
        source,
        '(Travian.Game.Marketplace.toggleTradeRoutes = function (e, t) {',
        '\n  (Travian.Game.Marketplace.updateVillageListLinks = function',
        'function toggleTradeRoutes(e, t) {',
    );
    let dialogCount = 0;
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
            api(_route, options) {
                if (options.error) {
                    options.error({error: true, message: 'Trade route update rejected.'});
                }
            },
        },
    };
    const checkbox = {checked: true};

    vm.runInNewContext(toggleTradeRoutes, sandbox, {filename: clientFile});
    sandbox.toggleTradeRoutes(3, checkbox);

    assert.equal(checkbox.checked, false, `${clientFile} must restore a rejected trade-route toggle.`);
    assert.equal(dialogCount, 1, `${clientFile} must show a rejected trade-route toggle error.`);
}

function assertAttackMarkerFailureRecovers(clientFile, source) {
    const markAttackSymbol = method(
        source,
        'markAttackSymbol: function (e) {',
        '\n  (Travian.Game.RallyPoint.CoordinatesInputHelper = function',
        'function markAttackSymbol(e) {',
    );
    let className = 'markAttack markAttack2';
    let dialogCount = 0;
    const marker = {
        addClass(value) {
            className = value;
            return this;
        },
        prop(name) {
            assert.equal(name, 'class');
            return className;
        },
        removeClass() {
            className = '';
            return this;
        },
    };
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
            api(_route, options) {
                if (options.error) {
                    options.error({error: true, message: 'Attack marker update rejected.'});
                }
            },
        },
        jQuery(selector) {
            assert.equal(selector, 'img#markSymbol_24');
            return marker;
        },
    };

    vm.runInNewContext(markAttackSymbol, sandbox, {filename: clientFile});
    sandbox.markAttackSymbol(24);

    assert.equal(className, 'markAttack markAttack2', `${clientFile} must restore a rejected attack marker.`);
    assert.equal(dialogCount, 1, `${clientFile} must show a rejected attack-marker error.`);
}

function assertSpamReportFailureRecovers(clientFile, source) {
    const reportSpam = method(
        source,
        'reportSpam: function (e, t, i, n, a) {',
        '\n  (Travian.Game.Village = {',
        'function reportSpam(e, t, i, n, a) {',
    );
    const dialogs = [];
    class Dialog {
        constructor(options = {}) {
            this.options = options;
            this.enableCount = 0;
            this.shown = false;
            dialogs.push(this);
        }
        disableForm() {}
        enableForm() {
            this.enableCount += 1;
        }
        setContent() {
            return this;
        }
        show() {
            this.shown = true;
            return this;
        }
        toggleFormState() {}
    }
    const reasonSelect = {
        length: 1,
        on() {
            return this;
        },
        val() {
            return 'advertisement';
        },
    };
    const sandbox = {
        Travian: {
            Dialog: {Dialog},
            api(_route, options) {
                if (options.error) {
                    options.error({error: true, message: 'Spam report rejected.'});
                }
            },
        },
        jQuery(selector) {
            assert.equal(selector, '#spamReason');
            return reasonSelect;
        },
    };

    vm.runInNewContext(reportSpam, sandbox, {filename: clientFile});
    sandbox.reportSpam(8, 'Report spam', 'Report', 'Choose a reason', {advertisement: 'Advertisement'});
    dialogs[0].options.onOkay();

    assert.equal(dialogs[0].enableCount, 1, `${clientFile} must re-enable a rejected spam report.`);
    assert.equal(dialogs.length, 2, `${clientFile} must create an error dialog for a rejected spam report.`);
    assert.equal(dialogs[1].shown, true, `${clientFile} must show the rejected spam-report error.`);
}

for (const clientFile of clientFiles) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    assertTradeRouteFailureRecovers(clientFile, source);
    assertAttackMarkerFailureRecovers(clientFile, source);
    assertSpamReportFailureRecovers(clientFile, source);
}

console.log('Optimistic client-action recovery contract passed.');
