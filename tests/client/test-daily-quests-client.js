// The daily-quest dialog in a real browser (1920x1080) for a throwaway fixture player with 61 points: the 25 and 50
// milestones can be collected, the 75 and 100 ones are locked. The dialog must stay open while collecting (no page
// navigation), the Collect button must be visible and inside the dialog, every chest must open its screen, and the
// states must follow the claim: Collected with the checkmark, "already claimed" after two seconds, the header indicator
// gone once nothing is left, the chest completed with its check after Back.
//
// Needs docker compose (the fixture account is created in the php container and removed when the test ends) and a
// local world; see dev/client-tests/run.sh for the Playwright setup.
const assert = require('node:assert/strict');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {chromium} = require('playwright');

const baseUrl = process.env.E2E_BASE_URL || 'http://localhost:8080';
if (!['localhost', '127.0.0.1'].includes(new URL(baseUrl).hostname)) {
    throw new Error('This check collects fixture rewards; only loopback worlds are supported.');
}

async function startFixture() {
    const child = spawn('docker', ['compose', 'exec', '-T', 'php', 'php', 'tests/support/village-fixture-session.php', 'dailyquests'], {
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
        console.log('Daily quests client skipped: the fixture account is unavailable (needs docker compose and the php container).');
        return;
    }
    const browser = await chromium.launch({headless: true});
    try {
        const page = await (await browser.newContext({viewport: {width: 1920, height: 1080}})).newPage();
        await page.goto(baseUrl);
        await page.locator('input[name="name"]').fill(fixture.account.name);
        await page.locator('input[name="password"]').fill(fixture.account.password);
        await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);
        await page.goto(`${baseUrl}/dorf1.php`);
        const navigations = [];
        page.on('framenavigated', (frame) => frame === page.mainFrame() && navigations.push(frame.url()));
        const awards = [];
        page.on('request', (request) => request.url().endsWith('/api/v1/daily-quest/award') && awards.push(JSON.parse(request.postData() || '{}')));

        const chest = (n) => page.locator('#dailyQuests .dailyProgress .reward').nth(n - 1);
        const imageState = async (n) => (await chest(n).locator('.rewardImage').getAttribute('class')).split(/\s+/).find((c) => ['locked', 'achieved', 'completed'].includes(c));
        const indicator = page.locator('a.dailyQuests .indicator').first();
        // Back reads the overview again; its screen is the first one and active only once the new markup is in.
        const overviewShown = () => page.waitForFunction(() => document.querySelector('#dailyQuests > .screen:first-child')?.classList.contains('active'));
        const screenActive = (index) => page.locator('#dailyQuests > .screen').nth(index).evaluate((screen) => screen.classList.contains('active'));

        assert.equal(await indicator.isVisible(), true, 'The header indicator must show while a milestone can be collected.');
        await page.locator('a.dailyQuests').first().click();
        await page.locator('#dailyQuests .dailyProgress').waitFor();
        assert.deepEqual([await imageState(1), await imageState(2), await imageState(3), await imageState(4)], ['achieved', 'achieved', 'locked', 'locked']);
        assert.equal(await page.locator('#dailyQuests .collectRewards').isEnabled(), true);

        // A locked chest opens its screen: heading, sentence, a disabled Collect button.
        await chest(3).click();
        await page.locator('#dailyQuestsRewardScreen').waitFor();
        assert.equal(await screenActive(1), true);
        assert.equal(await page.locator('#dailyQuestsRewardScreen h1').innerText(), 'Keep collecting points!');
        assert.equal(await page.locator('#dailyQuestsRewardScreen .collect').isDisabled(), true);
        await page.locator('#dailyQuestsRewardScreen .backButton').click();
        await overviewShown();
        assert.equal(await screenActive(0), true, 'Back must show the overview again.');

        // Collect rewards opens the first collectable milestone, and its button is on screen, inside the dialog.
        await page.locator('#dailyQuests .collectRewards').click();
        await page.locator('#dailyQuestsRewardScreen .collect.collectable').waitFor();
        assert.equal(await page.locator('#dailyQuestsRewardScreen h1').innerText(), 'Congratulations! You have collected enough points to get a reward!');
        const button = page.locator('#dailyQuestsRewardScreen .collect');
        await page.waitForTimeout(800); // the screens swap by a 250 ms + 250 ms height and opacity transition
        const box = await button.boundingBox();
        const dialog = await page.locator('.dailyQuestsDialog').boundingBox();
        assert.ok(box && dialog && box.y >= dialog.y && box.y + box.height <= dialog.y + dialog.height, `The Collect button must sit inside the dialog (button ${JSON.stringify(box)}, dialog ${JSON.stringify(dialog)}).`);
        assert.equal(await button.isEnabled(), true);
        const hit = await page.evaluate(({x, y}) => document.elementFromPoint(x, y).closest('button')?.className ?? null, {x: box.x + box.width / 2, y: box.y + box.height / 2});
        assert.match(hit ?? '', /collect/, 'Nothing may cover the Collect button.');

        await button.click();
        await page.locator('#dailyQuestsRewardScreen .collect.collected').waitFor();
        assert.deepEqual(awards, [{action: 'dailyQuest', questId: 'DailyQuestsReward_01'}], 'The claim must use the reference endpoint and payload.');
        assert.equal(await page.locator('#dailyQuestsRewardScreen .collectedCheckmark').count(), 1);
        assert.equal(await page.locator('#dailyQuestsRewardScreen .iconValueBox.animated').count(), 1, 'The reward box must animate after the claim.');
        assert.equal(await indicator.evaluate((element) => element.classList.contains('hidden')), false, 'The indicator stays while the 50 milestone can still be collected.');
        await page.waitForFunction(() => document.querySelector('#dailyQuestsRewardScreen h1').textContent === 'Reward already claimed!', null, {timeout: 5000});
        assert.equal(await button.isDisabled(), true, 'After two seconds the button is settled.');
        assert.equal(navigations.length, 0, `Collecting must not reload the page (navigated to ${navigations}).`);

        // Back: the chest is completed and carries the drawn check; the next milestone is collectable.
        await page.locator('#dailyQuestsRewardScreen .backButton').click();
        await overviewShown();
        assert.equal(await imageState(1), 'completed');
        assert.equal(await chest(1).locator('svg.check').count(), 1);
        assert.equal(await page.locator('#dailyQuests .collectRewards').isEnabled(), true);

        // The second milestone empties the list: the indicator goes, Collect rewards is disabled again.
        await page.locator('#dailyQuests .collectRewards').click();
        await page.locator('#dailyQuestsRewardScreen .collect.collectable').click();
        await page.locator('#dailyQuestsRewardScreen .collect.collected').waitFor();
        assert.deepEqual(awards.map((award) => award.questId), ['DailyQuestsReward_01', 'DailyQuestsReward_02']);
        // The reference hides it with a 250 ms animation that ends fully transparent.
        await page.waitForFunction(() => {
            const element = document.querySelector('a.dailyQuests .indicator');
            return element.classList.contains('hidden') && getComputedStyle(element).opacity === '0';
        }, null, {timeout: 5000});
        await page.locator('#dailyQuestsRewardScreen .backButton').click();
        await overviewShown();
        assert.deepEqual([await imageState(1), await imageState(2), await imageState(3), await imageState(4)], ['completed', 'completed', 'locked', 'locked']);
        assert.equal(await page.locator('#dailyQuests .collectRewards').isDisabled(), true);

        // A collected chest opens "already claimed", with a disabled button.
        await chest(1).click();
        await page.locator('#dailyQuestsRewardScreen').waitFor();
        assert.equal(await page.locator('#dailyQuestsRewardScreen h1').innerText(), 'Reward already claimed!');
        assert.equal(await page.locator('#dailyQuestsRewardScreen .collect.collected').isDisabled(), true);

        // A quest row opens its details screen: the name, the sentence, the tip only where the reference has one.
        await page.locator('#dailyQuestsRewardScreen .backButton').click();
        await overviewShown();
        const detailsText = async (selector) => page.locator(`#dailyQuestsDetailsScreen ${selector}`).innerText();
        await page.locator('#dailyQuestDailyQuest_11').click();
        await page.locator('#dailyQuestsDetailsScreen').waitFor();
        assert.equal(await screenActive(2), true, 'A quest row must show the details screen.');
        assert.equal(await detailsText('h1'), 'Contribute 1000 resources to an alliance bonus');
        assert.match(await detailsText('.description'), /^Contribute 1000 resources to any alliance bonus\./);
        assert.equal(await page.locator('#dailyQuestsDetailsScreen .additionalInformation').count(), 0, 'Quest 11 has no tip without a next threshold.');
        assert.equal(await page.locator('#dailyQuestsDetailsScreen .pill').count(), 6);
        await page.locator('#dailyQuestsDetailsScreen .backButton').click();
        await overviewShown();
        await page.locator('#dailyQuestDailyQuest_02').click();
        await page.locator('#dailyQuestsDetailsScreen.DailyQuest_02').waitFor();
        assert.equal(await detailsText('h1'), 'Raid an unoccupied oasis');
        assert.match(await detailsText('.additionalInformation'), /^Tip: For safety, you can still use cages/);
        await page.waitForTimeout(800); // the screens swap by a 250 ms + 250 ms height and opacity transition
        const details = await page.locator('#dailyQuestsDetailsScreen').boundingBox();
        const dialogBox = await page.locator('.dailyQuestsDialog').boundingBox();
        assert.ok(details && dialogBox && details.y >= dialogBox.y && details.y + details.height <= dialogBox.y + dialogBox.height, 'The details screen must sit inside the dialog.');
        await page.locator('#dailyQuestsDetailsScreen .backButton').click();
        await overviewShown();
        assert.equal(navigations.length, 0, 'The dialog must never have reloaded the page.');
        console.log('Daily quests client passed.');
    } finally {
        await browser.close();
        await fixture.stop();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
