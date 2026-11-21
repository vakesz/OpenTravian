// Task board: after a collect, rows are swapped in place. When the visible page has nothing left to collect but the
// scope still has collectable tasks (on another page), the board goes back to page 1, where the server lists them
// first. While something on the page is still collectable, it stays put. A task whose every level is collected hands
// its slot to the next unfinished task (server order) that is not on the page yet, one new task per finished item.
//
// Runs tasks-board.js and jQuery in Chromium against a stub page; Travian.api is faked, so no game server is needed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');

const root = `${__dirname}/../..`;
const jquery = fs.readFileSync(`${root}/game/deployment/public/js/default/jquery-4.0.0.js`, 'utf8');
const board = fs.readFileSync(`${root}/game/deployment/public/tasks-board.js`, 'utf8');
const origin = 'http://tasks.test';

const row = (key, state) => `<div class="task ${state} group buildingTask " data-task-key="${key}" data-quest-type="building"`
    + ` data-scope="village" data-target-level="2"><div class="iconValueBox"></div>`
    + `<button type="button" class="collect"${state === 'achieved' ? '' : ' disabled'}>Collect</button></div>`;

const page = (rows) => `<!doctype html><html><body><div id="content" class="tasks tasksVillage"><div id="tasks">
<div class="taskOverview">${rows}
<div class="pagination">
<button type="button" class="page" data-task-page-url="/tasks?t=village&amp;kid=1"></button>
<button type="button" class="page selected" disabled data-task-page-url="/tasks?t=village&amp;kid=1&amp;page=2"></button>
</div></div><div class="taskDetailTemplates"></div></div></div>
<script>${jquery}</script>
<script>
window.reloadRows = {};
window.reloadTasks = [];
window.Travian = {
  Game: {},
  api: function (endpoint, options) {
    if (endpoint === "progressive-tasks/collectReward") { options.success({}); return; }
    if (endpoint === "hero/dataForHUD") { options.complete && options.complete(); return; }
    if (endpoint === "progressive-tasks/reload") { options.success({ rows: { village: window.reloadRows, general: {} }, tasksData: { activeVillageTasks: window.reloadTasks, generalTasks: [] } }); window.reloaded = true; }
  },
};
</script>
<script>${board}</script></body></html>`;

async function run(scenario) {
    const browser = await chromium.launch();
    try {
        const context = await browser.newContext();
        const tab = await context.newPage();
        const visited = [];
        await tab.route(`${origin}/**`, (route) => {
            visited.push(new URL(route.request().url()).search);
            return route.fulfill({ contentType: 'text/html', body: page(scenario.rows) });
        });
        await tab.goto(`${origin}/tasks?t=village&kid=1&page=2`);
        await tab.evaluate(([rows, tasks]) => { window.reloadRows = rows; window.reloadTasks = tasks; },
            [scenario.reloadRows, scenario.reloadTasks || []]);
        await tab.click('.task[data-task-key="a"] button.collect');
        if (scenario.expectNavigation) {
            await tab.waitForURL(`${origin}/tasks?t=village&kid=1`, { timeout: 5000 });
        } else {
            await tab.waitForFunction(() => window.reloaded === true, null, { timeout: 5000 });
            await tab.waitForTimeout(300);
        }
        const cards = scenario.expectNavigation ? [] : await tab.evaluate(() => Array.from(
            document.querySelectorAll('#tasks .taskOverview > .task'),
            (card) => `${card.getAttribute('data-task-key')}:${card.classList.contains('achieved') ? 'achieved' : 'other'}`,
        ));
        return { visited, cards };
    } finally {
        await browser.close();
    }
}

(async () => {
    // Collected the last collectable task on page 2 while task "z" (on page 1) is still collectable: back to page 1.
    let { visited } = await run({
        rows: row('a', 'achieved') + row('b', 'open'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'open'), z: row('z', 'achieved') },
        expectNavigation: true,
    });
    assert.deepEqual(visited, ['?t=village&kid=1&page=2', '?t=village&kid=1'], 'The board must go back to page 1 to surface collectable tasks.');

    // Task "b" on this page is still collectable: stay on the page.
    ({ visited } = await run({
        rows: row('a', 'achieved') + row('b', 'achieved'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'achieved'), z: row('z', 'achieved') },
        expectNavigation: false,
    }));
    assert.deepEqual(visited, ['?t=village&kid=1&page=2'], 'The board must stay while the page still has a collectable task.');

    // Nothing left to collect anywhere: stay on the page.
    ({ visited } = await run({
        rows: row('a', 'achieved') + row('b', 'open'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'open'), z: row('z', 'collected') },
        expectNavigation: false,
    }));
    assert.deepEqual(visited, ['?t=village&kid=1&page=2'], 'The board must stay when nothing is collectable.');

    // Per-item replacement. `task(key, collected...)` is one reload tasksData entry; levels are flagged collected.
    const task = (key, ...collected) => ({ taskIndex: key, levels: collected.map((wasCollected) => ({ wasCollected })) });
    let cards;

    // "a" is now fully collected while "b" is still collectable: "a" gives its slot to "c", the first unfinished
    // task not on the page, at once; no page change.
    ({ visited, cards } = await run({
        rows: row('a', 'achieved') + row('b', 'achieved'),
        reloadRows: { b: row('b', 'achieved'), c: row('c', 'achieved'), d: row('d', 'open'), a: row('a', 'collected') },
        reloadTasks: [task('b', true, false), task('c', false), task('d', false), task('a', true, true)],
        expectNavigation: false,
    }));
    assert.deepEqual(visited, ['?t=village&kid=1&page=2'], 'Replacing a finished task must not change the page.');
    assert.deepEqual(cards, ['b:achieved', 'c:achieved'], 'A fully collected task must be replaced by the next task.');

    // "a" still has an uncollected level: it keeps its slot.
    ({ cards } = await run({
        rows: row('a', 'achieved') + row('b', 'achieved'),
        reloadRows: { b: row('b', 'achieved'), c: row('c', 'achieved'), a: row('a', 'open') },
        reloadTasks: [task('b', false), task('c', false), task('a', true, false)],
        expectNavigation: false,
    }));
    assert.deepEqual(cards, ['b:achieved', 'a:other'], 'A task with levels left must keep its slot.');

    // "a" is fully collected but every other task is on the page or finished too: it stays as Collected.
    ({ cards } = await run({
        rows: row('a', 'achieved') + row('b', 'achieved'),
        reloadRows: { b: row('b', 'achieved'), a: row('a', 'collected'), z: row('z', 'collected') },
        reloadTasks: [task('b', false), task('a', true), task('z', true)],
        expectNavigation: false,
    }));
    assert.deepEqual(cards, ['b:achieved', 'a:other'], 'Without a replacement the finished task must stay.');

    console.log('Task board surface collectable client test passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
