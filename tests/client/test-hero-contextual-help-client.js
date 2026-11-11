// renders #contextualHelpButton on Hero Attributes and Adventures only, not on Inventory or Appearance
// (compared at 1920x1080). Locally the control opens the in-game manual overview. Checked on the rendered pages
// of a throwaway fixture player; needs docker compose and a local world (see dev/client-tests/run.sh).
const assert = require('node:assert/strict');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {chromium} = require('playwright');

const baseUrl = process.env.E2E_BASE_URL || 'http://localhost:8080';
if (!['localhost', '127.0.0.1'].includes(new URL(baseUrl).hostname)) {
    throw new Error('This check logs in a fixture player; only loopback worlds are supported.');
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
        console.log('Hero contextual help client skipped: the fixture account is unavailable (needs docker compose and the php container).');
        return;
    }
    const browser = await chromium.launch({headless: true});
    try {
        const page = await (await browser.newContext({viewport: {width: 1920, height: 1080}})).newPage();
        await page.goto(baseUrl);
        await page.locator('input[name="name"]').fill(fixture.account.name);
        await page.locator('input[name="password"]').fill(fixture.account.password);
        await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);

        const expected = {'/hero/attributes': 1, '/hero/adventures': 1, '/hero/inventory': 0, '/hero/appearance': 0};
        for (const [route, count] of Object.entries(expected)) {
            const response = await page.goto(baseUrl + route);
            // The tooltip script moves the title attribute away after load, so read it from the served markup.
            const served = await response.text();
            const button = page.locator('#contextualHelpButton');
            assert.equal(await button.count(), count, `${route}: contextual help button count`);
            if (count === 1) {
                assert.match(served, /id="contextualHelpButton"[^>]*title="\s*Show contextual help on this page"/, `${route}: the reference title`);
                assert.match(await button.getAttribute('onclick'), /Travian\.Game\.Manual\.open\(\)/, `${route}: opens the manual overview`);
                assert.ok(await button.isVisible(), `${route}: the button is visible`);
            }
        }
        console.log('Hero contextual help client passed.');
    } finally {
        await browser.close();
        await fixture.stop();
    }
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
