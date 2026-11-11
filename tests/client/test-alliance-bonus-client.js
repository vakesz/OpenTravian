const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = `${__dirname}/../..`;
const clientFiles = [
    'game/deployment/public/game-client.js',
];

/** The reference's alliance bonus page script, cut out of the client bundle and run against stubs. */
function loadAllianceBonus(clientFile, values) {
    const source = fs.readFileSync(`${root}/${clientFile}`, 'utf8');
    const start = source.indexOf('(Travian.Game.AllianceBonus = {');
    const end = source.indexOf('\n  (Travian.Game.', start + 1);
    assert.notEqual(start, -1, `${clientFile} must define Travian.Game.AllianceBonus.`);
    assert.notEqual(end, -1, `${clientFile} must end Travian.Game.AllianceBonus before the next handler.`);
    const calls = [];
    // The bundle runs in its own realm, so recorded values are copied before they are compared.
    const element = (selector) => ({
        val: () => values[selector],
        html: () => '',
        data: () => element(selector),
        removeClass: () => element(selector),
        addClass: () => element(selector),
        attr: () => undefined,
        hide: () => element(selector),
        show: () => element(selector),
    });
    const Travian = {
        Game: {},
        i18n: {Number: (value) => String(value)},
        api: (route, options) => calls.push({route, data: JSON.parse(JSON.stringify(options.data))}),
    };
    const sandbox = {Travian, jQuery: element};
    vm.runInNewContext(`${source.slice(start, end).replace(/,\s*$/, '')};`, sandbox, {filename: clientFile});
    return {bonus: Travian.Game.AllianceBonus, calls};
}

function assertContributionRouting(clientFile) {
    const values = {
        '#donate1': '300', '#donate2': '0', '#donate3': '0', '#donate4': '0',
        '#scale1': '100', '#scale2': '0', '#scale3': '0', '#scale4': '0',
    };

    // within the daily limit the donation goes straight to alliance/bonus/contribute
    let {bonus, calls} = loadAllianceBonus(clientFile, values);
    bonus.init(0, 1000);
    bonus.bonusSelected = 'CPProduction';
    bonus.contribute(false);
    assert.deepEqual(calls.map((call) => call.route), ['alliance/bonus/contribute'], `${clientFile} must contribute within the limit.`);
    assert.deepEqual(calls[0].data, {bonus: 'CPProduction', resources: {lumber: 300, clay: 0, iron: 0, crop: 0}});

    // over the daily limit it first opens the scale-down form instead
    ({bonus, calls} = loadAllianceBonus(clientFile, values));
    bonus.init(900, 1000);
    bonus.bonusSelected = 'CPProduction';
    bonus.contribute(false);
    assert.deepEqual(calls.map((call) => call.route), ['alliance/bonus/form/scale-down'], `${clientFile} must offer the scale-down form over the limit.`);
    assert.equal(calls[0].data.gold, false);

    // the scaled-down form posts its own amounts
    ({bonus, calls} = loadAllianceBonus(clientFile, values));
    bonus.init(900, 1000);
    bonus.bonusSelected = 'CPProduction';
    bonus.contribute(true);
    assert.deepEqual(calls[0].data.resources, {lumber: 100, clay: 0, iron: 0, crop: 0}, `${clientFile} must send the scaled amounts.`);

    // the tripled dialog is fetched with the donation form values
    ({bonus, calls} = loadAllianceBonus(clientFile, values));
    bonus.bonusSelected = 'CPProduction';
    bonus.showContributeForGoldDialog();
    assert.equal(calls[0].route, 'alliance/bonus/form/gold', `${clientFile} must fetch the tripled dialog.`);
}

clientFiles.forEach(assertContributionRouting);

console.log('Alliance bonus client contract passed.');
