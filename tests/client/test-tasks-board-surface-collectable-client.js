// Task board: after a collect, rows are swapped in place. When the visible page has nothing left to collect but the
// scope still has collectable tasks (on another page), the board goes back to page 1, where the server lists them
// first. While something on the page is still collectable, it stays put.
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
window.Travian = {
  Game: {},
  api: function (endpoint, options) {
    if (endpoint === "progressive-tasks/collectReward") { options.success({}); return; }
    if (endpoint === "hero/dataForHUD") { options.complete && options.complete(); return; }
    if (endpoint === "progressive-tasks/reload") { options.success({ rows: { village: window.reloadRows, general: {} } }); window.reloaded = true; }
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
        await tab.evaluate((rows) => { window.reloadRows = rows; }, scenario.reloadRows);
        await tab.click('.task[data-task-key="a"] button.collect');
        if (scenario.expectNavigation) {
            await tab.waitForURL(`${origin}/tasks?t=village&kid=1`, { timeout: 5000 });
        } else {
            await tab.waitForFunction(() => window.reloaded === true, null, { timeout: 5000 });
            await tab.waitForTimeout(300);
        }
        return visited;
    } finally {
        await browser.close();
    }
}

(async () => {
    // Collected the last collectable task on page 2 while task "z" (on page 1) is still collectable: back to page 1.
    let visited = await run({
        rows: row('a', 'achieved') + row('b', 'open'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'open'), z: row('z', 'achieved') },
        expectNavigation: true,
    });
    assert.deepEqual(visited, ['?t=village&kid=1&page=2', '?t=village&kid=1'], 'The board must go back to page 1 to surface collectable tasks.');

    // Task "b" on this page is still collectable: stay on the page.
    visited = await run({
        rows: row('a', 'achieved') + row('b', 'achieved'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'achieved'), z: row('z', 'achieved') },
        expectNavigation: false,
    });
    assert.deepEqual(visited, ['?t=village&kid=1&page=2'], 'The board must stay while the page still has a collectable task.');

    // Nothing left to collect anywhere: stay on the page.
    visited = await run({
        rows: row('a', 'achieved') + row('b', 'open'),
        reloadRows: { a: row('a', 'open'), b: row('b', 'open'), z: row('z', 'collected') },
        expectNavigation: false,
    });
    assert.deepEqual(visited, ['?t=village&kid=1&page=2'], 'The board must stay when nothing is collectable.');

    console.log('Task board surface collectable client test passed.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
