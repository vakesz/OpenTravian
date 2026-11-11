const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = `${__dirname}/../..`;
const clientFiles = [
    'game/deployment/public/game-client.js',
];

function control() {
    const classes = new Set();

    return {
        length: 1,
        addClass(name) {
            classes.add(name);
            return this;
        },
        hasClass(name) {
            return classes.has(name);
        },
        removeClass(name) {
            classes.delete(name);
            return this;
        },
        on() {
            return this;
        },
    };
}

function collection(elements) {
    return {
        length: elements.length,
        each(callback) {
            elements.forEach((element, index) => callback.call(element, index, element));
            return this;
        },
        filter() {
            return this;
        },
        html(value) {
            if (value !== undefined) {
                elements.forEach((element) => {
                    element.htmlValue = value;
                });
            }
            return elements[0]?.htmlValue;
        },
        val() {
            return '1';
        },
    };
}

function assertRejectedExchangeRecovers(clientFile) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    const start = source.indexOf('(Travian.Game.Hero.SilverExchange = function (e) {');
    const end = source.indexOf('\n  }),\n  (Travian.Game.Activation', start);
    assert.notEqual(start, -1, `${clientFile} must define SilverExchange.`);
    assert.notEqual(end, -1, `${clientFile} must terminate SilverExchange before Activation.`);

    for (const directionType of ['GoldToSilver', 'SilverToGold']) {
        const button = control();
        const firstInput = control();
        const secondInput = control();
        const inputs = collection([firstInput, secondInput]);
        const messageLine = {};
        const sandbox = {
            window: {},
            Object,
            Date,
            Math,
            setTimeout,
            Travian: {
                Game: {Hero: {}},
                api(_route, options) {
                    options.error({error: true, message: 'Exchange rejected by server.'});
                },
            },
        };
        sandbox.jQuery = (target) => {
            if (target === '#silverExchange .exchangeType input.text') {
                return inputs;
            }
            if (target === '#silverExchange .exchangeMessageLine') {
                return collection([messageLine]);
            }
            return target;
        };
        sandbox.jQuery.fn = {filterInputTG() {}};

        vm.runInNewContext(source.slice(start, end + 5), sandbox, {filename: clientFile});
        const exchange = new sandbox.Travian.Game.Hero.SilverExchange({
            exchangeOptions: {
                directionType,
                showExchangeTypeElement: {length: 0},
                inputElement: {length: 0},
                submitButton: button,
            },
            messages: {
                autoCorrect: {type: 'notice', message: 'Auto corrected.'},
                exchangeFailed: {type: 'error', message: 'The exchange could not be completed.'},
            },
        });

        exchange.sendAction(button);

        assert.equal(
            exchange.isWaiting,
            false,
            `${clientFile} must clear the ${directionType} waiting state after a rejected request.`,
        );
        assert.equal(
            button.hasClass('disabled'),
            false,
            `${clientFile} must re-enable the ${directionType} button after a rejected request.`,
        );
        assert.equal(
            firstInput.hasClass('disabled'),
            false,
            `${clientFile} must re-enable the first ${directionType} input after a rejected request.`,
        );
        assert.equal(
            secondInput.hasClass('disabled'),
            false,
            `${clientFile} must re-enable the second ${directionType} input after a rejected request.`,
        );
        assert.equal(
            messageLine.htmlValue,
            '<span class="error">Exchange rejected by server.</span>',
            `${clientFile} must show the ${directionType} server rejection.`,
        );
    }
}

clientFiles.forEach(assertRejectedExchangeRecovers);

console.log('Silver exchange client recovery contract passed.');
