const assert = require('node:assert/strict');
const fs = require('node:fs');

const root = `${__dirname}/../..`;
const clients = [
    'game/deployment/public/game-client.js',
];

for (const clientPath of clients) {
    const source = fs.readFileSync(`${root}/${clientPath}`, 'utf8');
    const start = source.indexOf('(Travian.Game.Marketplace.toggleTradeRoutes = function');
    const end = source.indexOf('\n  (Travian.Game.Marketplace.updateVillageListLinks', start);

    assert.notEqual(start, -1, `${clientPath} must define the native trade-route toggle handler.`);
    assert.notEqual(end, -1, `${clientPath} must terminate the toggle handler before village-list helpers.`);

    let request = null;
    let dialogMessage = null;
    const travian = {
        Game: {
            Marketplace: {},
        },
        Dialog: {
            Dialog: function Dialog() {
                this.setContent = (message) => {
                    dialogMessage = message;
                    return this;
                };
                this.show = () => this;
            },
        },
        api(route, options) {
            request = {route, options};
        },
    };

    const fragment = source.slice(start, end).replace(/,\s*$/, ';');
    Function('Travian', fragment)(travian);

    const checkbox = {checked: true};
    assert.equal(
        travian.Game.Marketplace.toggleTradeRoutes(42, checkbox),
        false,
        `${clientPath} must prevent the native checkbox navigation.`,
    );
    assert.deepEqual(
        {route: request.route, data: request.options.data},
        {route: 'trade-route/toggle', data: {routeId: 42, enabled: 1}},
        `${clientPath} must submit the route id and enabled state to the maintained endpoint.`,
    );
    request.options.error({message: 'Route update failed.'});
    assert.equal(checkbox.checked, false, `${clientPath} must restore the switch when the server rejects a toggle.`);
    assert.equal(dialogMessage, 'Route update failed.', `${clientPath} must surface the server rejection.`);

    const disabledCheckbox = {checked: false};
    travian.Game.Marketplace.toggleTradeRoutes(42, disabledCheckbox);
    assert.deepEqual(
        {route: request.route, data: request.options.data},
        {route: 'trade-route/toggle', data: {routeId: 42, enabled: 0}},
        `${clientPath} must submit a disabled state when the switch is cleared.`,
    );
    request.options.error({message: 'Route update failed.'});
    assert.equal(disabledCheckbox.checked, true, `${clientPath} must restore a rejected disabled switch.`);

    assert.doesNotMatch(
        source,
        /Marketplace\.toggleSorting|tradeRoutesOrder/,
        `${clientPath} must not retain the retired trade-route sorting client path.`,
    );
}

console.log('Marketplace trade-route client contract passed.');
