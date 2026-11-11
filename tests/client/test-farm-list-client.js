const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync(`${__dirname}/../../game/deployment/public/farm-list.js`, 'utf8');
const sandbox = {
    document: {getElementById: () => null, documentElement: {lang: 'en'}},
    Travian: {},
    Intl,
    Date,
    Number,
    Math,
};
sandbox.window = sandbox;
vm.runInNewContext(source, sandbox, {filename: 'farm-list.js'});
const {Model, Page} = sandbox.Travian.Game.FarmList;

const labels = {
    avatar_locked: 'banned', sitter_not_allowed_only_with_right: 'sitter', sendingNotAllowedOnVacationMode: 'vacation', noRallyPoint: 'Village [NAME] has no rally point.',
    hasNoActive: 'none active', startAllActive: 'start all', startSelectedActive: 'start selected', noActiveSelected: 'none selected active', startRaidList: 'Start', beingRaided: 'being raided',
    noTargetSelected: 'No target selected', selected: '[COUNT] selected', addTarget: 'Add target', lastRaid: 'Last raid', village: 'Village', population: 'Population',
    distance: 'Distance', troops: 'Troops', unoccupiedOasis: 'Unoccupied oasis', occupiedOasis: 'Occupied oasis', attacksOnTheWay: 'Attacks on the way', nextArrivalIn: 'Next arrival in',
    maximumCapacity: 'Maximum capacity', report_icon_1: 'Won as attacker without losses.', startSuccess: 'Number of raids started', startWarning: 'Number of raids not started',
    'day-1': 'yesterday', lastStartOfFarmList: 'Last time started', beginnersProtectionDescriptionShort: 'protection',
};
const troop = (t1 = 0) => ({t1, t2: 0, t3: 0, t4: 0, t5: 0, t6: 0, t7: 0, t8: 0, t9: 0, t10: 0});
const slot = (id, extra = {}) => Object.assign({
    id, target: {id, mapId: id, x: 1, y: 2, name: 'Foe <b>', type: 0, population: 55}, troop: troop(3), distance: 4.45, isActive: true,
    isRunning: false, isSpying: false, runningAttacks: 0, nextAttackAt: null, lastRaid: null, totalBooty: {booty: 0, raids: 0},
}, extra);
const now = 1794034375;
const list = {
    id: 7, name: 'Farms', slotsAmount: 2, runningRaidsAmount: 1, isExpanded: true, sortIndex: 0, lastStartedTime: 0, sortField: 'distance',
    sortDirection: 'desc', useShip: false, onlyLosses: false, ownerVillage: {id: 9, troops: {ownTroopsAtTown: {units: troop(2)}}}, defaultTroop: troop(),
    slotsStates: [{id: 1, isActive: true}, {id: 2, isActive: false}],
    slots: [
        slot(1, {isRunning: true, runningAttacks: 1, nextAttackAt: now + 60, lastRaid: {reportObjectId: 5, authKey: '|k', time: now - 5, raidedResources: {lumber: 1, clay: 2, iron: 3, crop: 4}, bootyMax: 10, icon: 1}, totalBooty: {booty: 1234, raids: 2}}),
        slot(2, {isActive: false, target: {id: 2, mapId: 2, x: 3, y: 4, name: '', type: 3, population: null}}),
    ],
};
const own = {
    isSitter: false, isInVacationMode: false, banInfo: null, accessRights: {sendRaids: true}, beginnersProtection: null, village: {id: 9, tribeId: 1},
    villages: [{id: 9, name: 'Home', hasRallyPoint: true, hasHarbour: false, tribeId: 1}], farmLists: [list], abandonedFarmLists: [],
};
const config = {
    viewData: {bootstrapData: {timestamp: now}, ownPlayer: own}, labels, glyphs: {}, race: 1, unitTitles: {1: 'Legionnaire'}, timeZone: 'UTC', timeFormat: 0,
    language: 'en-US', harbours: {useDirectPath: true}, limits: {lists: 100, slots: 100, units: 9999999}, types: {village: 0, capital: 1, oasisOccupied: 2, oasisFree: 3},
};

// Sorting: the active field flips, any other starts with the given direction.
assert.equal(Model.nextSort(list, 'distance', 'asc'), 'asc');
assert.equal(Model.nextSort({sortField: 'distance', sortDirection: 'asc'}, 'distance', 'asc'), 'desc');
assert.equal(Model.nextSort(list, 'bounty', 'desc'), 'desc');

// Selection sums.
assert.equal(JSON.stringify(Model.summedTroops(list.slots, [1, 2])), '{"t1":6}');
assert.equal(JSON.stringify(Model.summedTroops(list.slots, [2])), '{"t1":3}');

// Start button: first blocking reason wins, the count is the selection or the active targets.
const village = own.villages[0];
const start = (patch, v = village, active = 2, selected = 0) => Model.startState(Object.assign({}, own, patch), v, active, selected, labels);
assert.deepEqual({...start({})}, {disabled: false, title: 'start all', count: 2});
assert.equal(start({}, village, 2, 1).title, 'start selected');
assert.equal(start({banInfo: {lockStatus: 'LOCKED'}}).title, 'banned');
assert.equal(start({banInfo: {lockStatus: 'NOT_LOCKED'}}).disabled, false);
assert.equal(start({accessRights: {sendRaids: false}}).title, 'sitter');
assert.equal(start({isInVacationMode: true}).title, 'vacation');
assert.equal(start({}, {id: 9, name: 'Home', hasRallyPoint: false}).title, 'Village Home has no rally point.');
assert.equal(start({}, village, 0).title, 'none active');
assert.equal(start({}, village, 0, 1).title, 'none selected active');

// Bounty icon, placeholders and times.
const raid = (total, max) => ({raidedResources: {lumber: total, clay: 0, iron: 0, crop: 0}, bootyMax: max});
assert.equal(Model.bountyIcon(raid(0, 10)), 'bounty_empty_small');
assert.equal(Model.bountyIcon(raid(4, 10)), 'bounty_half_small');
assert.equal(Model.bountyIcon(raid(10, 10)), 'bounty_full_small');
assert.equal(Model.fill('[A] of [B]', {A: 1, B: 2}), '1 of 2');
const at = (timestamp, format, style = 0) => Model.formatTime(timestamp, format, {timeZone: 'UTC', timeFormat: style, now, labels});
assert.equal(at(now, 'dateTime'), '7.11.26, 06:52');
assert.equal(at(now, 'dateTime', 1), '11/7/26, 06:52 AM');
assert.equal(at(now, 'dateTime', 4), '7.11.2026, 06:52');
assert.equal(at(now - 60, 'dynamicDateTime'), '06:51:55');
assert.equal(at(now - 86400, 'dynamicDateTime'), 'yesterday');
assert.equal(at(now - 5 * 86400, 'dynamicDateTime'), '2.11.26');
assert.equal(at(now + 86400, 'dynamicDateTime'), '8.11.26, 06:52');

// Start targets: the selected active targets, else all active ones.
const startPage = new Page(config);
assert.equal(startPage.startTargets(list).join(), '1');
startPage.selected[7] = [2];
assert.equal(startPage.startTargets(list).join(), '');
startPage.selected[7] = [1, 2];
assert.equal(startPage.startTargets(list).join(), '1');

// Markup of an expanded list.
const page = new Page(config);
const html = page.listHtml(list, false);
assert.match(html, /<div class="dropContainer" data-sortindex="0"><div class="farmListWrapper expanded">/);
assert.match(html, /<div class="name">Farms<\/div>/);
assert.match(html, /<span>‭‭1‬\/‭2‬‬ being raided<\/span>/);
assert.match(html, /Foe &lt;b&gt;/);
assert.match(html, /Unoccupied oasis ‭<span class="coordinates coordinatesWrapper">/);
assert.match(html, /<tr class="slot disabled" data-slot="2">/);
assert.match(html, /<td class="population"><span><\/span><\/td>/);
assert.match(html, /<td class="distance"><span>4\.5<\/span><\/td>/);
assert.match(html, /<i class="attack_small" title="Attacks on the way: 1&lt;br&gt;Next arrival in: &lt;span class=&quot;timer&quot; counting=&quot;down&quot; value=&quot;60&quot; id=&quot;timer_7_1_/);
assert.match(html, /<i class="unit_small tribe1 t1" title="Legionnaire"><\/i><span class="value">3<\/span>/);
assert.match(html, /<a class="lastRaidReport " href="\/report\?id=5\|k"><i class="lastRaidState attack_won_withoutLosses_small" title="Won as attacker without losses.">/);
assert.match(html, /<div class="lastRaidBounty"><i class="bounty_full_small"/);
assert.match(html, /<div class="averageRaidBounty">[^]*<span class="value">‭1,234‬<\/span>/);
assert.match(html, /<th class="target sortable" data-field="village"/);
assert.match(html, /No target selected/);
assert.match(html, /<div class="farmListFooter">/);
assert.doesNotMatch(html, /shipUsed/);
page.selected[7] = [1];
assert.match(page.summaryHtml(list, false), /1 selected/);
assert.match(page.summaryHtml(list, false), /class="value notEnough"/);
assert.match(page.listHtml(list, false), /Start \(1\)/);

// Abandoned lists have no start button, show no distance and count their targets instead of running raids.
const lost = Object.assign({}, list, {ownerVillage: {id: 99}, isExpanded: true});
const lostHtml = page.listHtml(lost, true);
assert.doesNotMatch(lostHtml, /startFarmList/);
assert.match(lostHtml, /<td class="distance"><span>-<\/span><\/td>/);
assert.match(lostHtml, /<span>\u202D2\u202C<\/span><\/div><a class="expandCollapse">/);
assert.doesNotMatch(lostHtml, /Add target <svg>/);

// A collapsed list keeps its table but draws no targets and no footer.
const collapsed = page.listHtml(Object.assign({}, list, {isExpanded: false}), false);
assert.match(collapsed, /farmListWrapper collapsed/);
assert.doesNotMatch(collapsed, /<tr class="slot/);
assert.doesNotMatch(collapsed, /farmListFooter/);

// Arabic mirrors the slash and the outer isolate and writes Latin digits; a minus sign sits outside the isolate.
const arabic = new Page(Object.assign({}, config, {language: 'ar-AE'}));
assert.equal(arabic.ratio(1, 2), '\u202E\u202D1\u202C\\\u202D2\u202C\u202C');
assert.equal(arabic.number(-1234, true, 0), '\u202E\u2212\u202D1.234\u202C\u202C');
assert.match(arabic.coordinates(1, 2, true), /^\u202E<span class="coordinates coordinatesWrapper coordinatesAligned coordinatesrtl">/);
assert.equal(page.ratio(1, 2, true), '\u202D<span class="nominator">\u202D1\u202C</span>/<span class="denominator">\u202D2\u202C</span>\u202C');
assert.equal(page.number(-5), '\u202D\u2212\u202D5\u202C\u202C');

// A village without a rally point cannot start.
page.own = Object.assign({}, own, {villages: [{id: 9, name: 'Home', hasRallyPoint: false, tribeId: 1}]});
assert.match(page.listHtml(list, false), /startFarmList disabled/);

console.log('Farm list client passed.');
