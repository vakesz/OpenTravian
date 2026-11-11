// Starting an adventure in a real browser (1920x1080) for a throwaway fixture player whose hero is at home with three
// adventures listed. Like the reference: one click on Explore sends the hero (a PUT then a POST to /api/v1/troop/send,
// no page navigation, no start page in between), the list is replaced by the "on its way" block, and that block's
// Continue button has the reference size (37px high with a 32px label, centred) and leaves for the village.
//
// Needs docker compose (the fixture account is created in the php container and removed when the test ends) and a
// local world; see dev/client-tests/run.sh for the Playwright setup.
const assert = require('node:assert/strict');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {chromium} = require('playwright');

const baseUrl = process.env.E2E_BASE_URL || 'http://localhost:8080';
if (!['localhost', '127.0.0.1'].includes(new URL(baseUrl).hostname)) {
    throw new Error('This check sends a fixture hero; only loopback worlds are supported.');
}

async function startFixture() {
    const child = spawn('docker', ['compose', 'exec', '-T', 'php', 'php', 'tests/support/village-fixture-session.php', 'adventures'], {
        cwd: path.resolve(__dirname, '../..'),
        stdio: ['pipe', 'pipe', 'inherit'],
    });
    const exited = new Promise((resolve) => child.once('close', resolve));
    let out = '';
    const account = await new Promise((resolve) => {
        const timer = setTimeout(() => resolve(null), 60000);
        child.once('error', () => resolve(null));
        child.stdout.on('data', (chunk) => {
            out += chunk;
            if (out.includes('\n')) {
                clearTimeout(timer);
                try { resolve(JSON.parse(out.split('\n')[0])); } catch { resolve(null); }
            }
        });
        child.once('close', () => resolve(null));
    });
    const stop = async () => {
        child.stdin.end();
        await exited;
    };
    if (!account) {
        await stop();
        return null;
    }
    return {account, stop};
}

(async () => {
    const fixture = await startFixture();
    if (!fixture) {
        console.log('Hero adventure start client skipped: the fixture account is unavailable (needs docker compose and the php container).');
        return;
    }
    const browser = await chromium.launch({headless: true});
    try {
        const page = await (await browser.newContext({viewport: {width: 1920, height: 1080}})).newPage();
        await page.goto(baseUrl);
        await page.locator('input[name="name"]').fill(fixture.account.name);
        await page.locator('input[name="password"]').fill(fixture.account.password);
        await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);
        await page.goto(`${baseUrl}/hero/adventures`);

        const rows = page.locator('table.adventureList tbody tr');
        assert.equal(await rows.count(), 3, 'the fixture lists three adventures');
        const explore = rows.nth(1).locator('td.button button');
        assert.equal(await explore.count(), 1, 'every row has one Explore button');
        assert.equal(await page.locator('a[href*="start_adventure"]').count(), 0, 'there is no start page to link to');

        const navigations = [];
        page.on('framenavigated', (frame) => frame === page.mainFrame() && navigations.push(frame.url()));
        const sends = [];
        page.on('request', (request) => request.url().endsWith('/api/v1/troop/send') && sends.push({method: request.method(), body: JSON.parse(request.postData() || '{}')}));

        // One click leaves the list: the hero is sent and the list is replaced in place.
        await explore.click();
        await page.locator('#heroAdventure .adventureStarted').waitFor();
        assert.deepEqual(sends.map((send) => send.method), ['PUT', 'POST'], 'one click signs and sends');
        for (const send of sends) {
            assert.equal(send.body.action, 'troopsSend');
            assert.equal(send.body.eventType, 50);
            assert.deepEqual(send.body.troops, [{t11: 1}]);
            assert.ok(Number.isInteger(send.body.target.adventureId));
        }
        assert.deepEqual(navigations, [], 'starting the adventure must not navigate');
        assert.equal(await page.locator('table.adventureList').count(), 0, 'the list is gone');
        assert.match(await page.locator('.adventureStarted .heroState').innerText(), /on its way to an adventure/i);
        assert.equal(await page.locator('.adventureStarted .heroState .timer').count(), 1, 'the arrival counts down');

        // The reference's Continue button: 37px high, a 32px label, 25px above and 10px below, centred in the block.
        const geometry = await page.locator('.adventureStarted button.continue').evaluate((button) => {
            const rect = button.getBoundingClientRect();
            const label = button.firstElementChild.getBoundingClientRect();
            const block = button.parentElement.getBoundingClientRect();
            const style = getComputedStyle(button);
            return {
                width: rect.width, height: rect.height, labelHeight: label.height, marginTop: style.marginTop, marginBottom: style.marginBottom,
                display: style.display, centred: Math.abs((rect.left + rect.width / 2) - (block.left + block.width / 2)),
                tag: button.tagName, padding: style.padding,
            };
        });
        assert.equal(geometry.tag, 'BUTTON');
        assert.ok(Math.abs(geometry.width - 110.33) < 3, `Continue is about 110px wide (${geometry.width})`);
        assert.equal(geometry.height, 37, 'Continue is 37px high');
        assert.equal(geometry.labelHeight, 32, 'the Continue label is 32px high');
        assert.equal(geometry.marginTop, '25px');
        assert.equal(geometry.marginBottom, '10px');
        assert.equal(geometry.display, 'block');
        assert.equal(geometry.padding, '1px 6px');
        assert.ok(geometry.centred < 1, `Continue must be centred (off by ${geometry.centred}px)`);

        await Promise.all([page.waitForNavigation(), page.locator('.adventureStarted button.continue').click()]);
        assert.match(page.url(), /\/dorf[12]\.php$/, 'Continue returns to the village');

        // A reload shows the reference's state line (on its way, with the arrival) and the list with dimmed buttons.
        await page.goto(`${baseUrl}/hero/adventures`);
        assert.equal(await page.locator('#heroAdventure .adventureStarted').count(), 0, 'a reload no longer shows the started block');
        assert.match(await page.locator('#heroAdventure .heroState').innerText(), /Your hero is on its way to an adventure\.\s*Arrival in .* at \d\d:\d\d/);
        assert.equal(await page.locator('#heroAdventure table.adventureList td.button button:not(.disabled)').count(), 0, 'every Explore button is dimmed while the hero is away');
        console.log('Hero adventure start client passed.');
    } finally {
        await browser.close();
        await fixture.stop();
    }
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
