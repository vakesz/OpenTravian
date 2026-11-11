const assert = require("node:assert/strict");
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const { spawn } = require("node:child_process");
const { chromium, devices } = require("playwright");

const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
const passwordFile = process.env.E2E_PASSWORD_FILE;
if (!passwordFile || !process.env.E2E_VILLAGE_ID) {
  throw new Error("Set E2E_PASSWORD_FILE and E2E_VILLAGE_ID for an installed local test account.");
}
if (!["localhost", "127.0.0.1"].includes(new URL(baseUrl).hostname)) {
  throw new Error("This check changes the local troop-panel preference; only loopback worlds are supported.");
}

function captureDir() {
  const dir = process.env.E2E_CAPTURE_DIR || path.join(os.tmpdir(), "village-panels");
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

// Fonts and two animation frames: the panel's size depends on glyph metrics, and the
// fixed overlays only settle after the layout following them.
const settled = (page) => page.evaluate(async () => {
  await document.fonts.ready;
  await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
});

// A failed case leaves a screenshot, the DOM and the boxes of everything that can cover the
// troop panel, so a layout failure can be read without re-running the test.
async function captureFailure(page, name, error) {
  const stem = `${captureDir()}/${name.replaceAll(" ", "-")}-failure`;
  const boxes = await page.evaluate(() => {
    const box = (element) => {
      const r = element.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height, position: getComputedStyle(element).position };
    };
    return {
      viewport: { width: innerWidth, height: innerHeight, scrollY },
      selectors: Object.fromEntries([".villageInfobox.production", ".villageInfobox.units", ".buildingList"]
        .map((selector) => [selector, [...document.querySelectorAll(selector)].map(box)])),
      cells: [...document.querySelectorAll(".villageInfobox.units td.num, .villageInfobox.units td.noTroops")]
        .map((cell) => {
          const r = cell.getBoundingClientRect();
          const hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
          return { cell: box(cell), hit: hit && `${hit.tagName}.${hit.className}`, hitBox: hit && box(hit) };
        }),
    };
  }).catch((captureError) => ({ captureError: captureError.message }));
  fs.writeFileSync(`${stem}.json`, JSON.stringify(boxes, null, 2));
  fs.writeFileSync(`${stem}.html`, await page.content().catch(() => ""));
  await page.screenshot({ path: `${stem}.png`, fullPage: true }).catch(() => {});
  error.message += ` (artifacts: ${stem}.json/.html/.png)`;
}

// The queued-construction state only exists on an account with a queue, and the real player's
// village must not be touched, so a throwaway fixture account is created inside the php
// container and stays alive (and removed afterwards) until its stdin closes.
async function startQueuedFixture() {
  const child = spawn("docker", ["compose", "exec", "-T", "php", "php", "tests/support/village-fixture-session.php"], {
    cwd: path.resolve(__dirname, "../.."),
    stdio: ["pipe", "pipe", "inherit"],
  });
  const exited = new Promise((resolve) => child.once("close", resolve));
  let out = "";
  const account = await new Promise((resolve) => {
    const timer = setTimeout(() => resolve(null), 60000);
    child.once("error", () => resolve(null));
    child.stdout.on("data", (chunk) => {
      out += chunk;
      if (out.includes("\n")) {
        clearTimeout(timer);
        try { resolve(JSON.parse(out.split("\n")[0])); } catch { resolve(null); }
      }
    });
    child.once("close", () => resolve(null));
  });
  const stop = async () => {
    child.stdin.end();
    await exited;
  };
  if (!account) {
    await stop();
    console.log("queued: fixture account unavailable (needs docker compose and the php container); queue state skipped.");
    return null;
  }
  return { account, stop };
}

// Log in once and reuse the session in every case: six rapid logins would only
// test the login throttle, and a missing form fails here with the URL and a dump.
async function loginState(browser, username, password) {
  const context = await browser.newContext();
  try {
    const page = await context.newPage();
    await page.goto(baseUrl);
    try {
      await page.locator('input[name="name"]').waitFor({ timeout: 15000 });
    } catch (error) {
      const stem = `${captureDir()}/login-form-missing`;
      fs.writeFileSync(`${stem}.html`, await page.content());
      await page.screenshot({ path: `${stem}.png` }).catch(() => {});
      throw new Error(`login form missing at ${page.url()} (artifacts: ${stem}.html/.png)`);
    }
    await page.locator('input[name="name"]').fill(username);
    await page.locator('input[name="password"]').fill(password);
    await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);
    return await context.storageState();
  } finally {
    await context.close();
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  let queued = null;
  try {
    const username = process.env.E2E_USERNAME || "player";
    const accounts = [{
      label: "",
      storageState: await loginState(browser, username, fs.readFileSync(passwordFile, "utf8").trim()),
      villageId: process.env.E2E_VILLAGE_ID,
    }];
    queued = await startQueuedFixture();
    if (queued) {
      accounts.push({
        label: "queued ",
        storageState: await loginState(browser, queued.account.name, queued.account.password),
        villageId: String(queued.account.kid),
      });
    }
    const cases = [
      ["iPhone", { ...devices["iPhone 13"], viewport: { width: 375, height: 812 } }],
      ["short Android", { ...devices["Pixel 7"], viewport: { width: 375, height: 667 } }],
      ["desktop", { viewport: { width: 1920, height: 1080 } }],
    ];
    for (const { label, storageState, villageId } of accounts) {
      for (const [name, options, rtl] of cases.flatMap(([name, options]) => [
        [label + name, options, false], [`${label}${name} RTL`, options, true],
      ])) {
        const context = await browser.newContext({ ...options, storageState });
        if (rtl) {
          // Exercise the real RTL pack without changing the account's language.
          await context.route("**/css_ltr/**", (route) =>
            route.continue({ url: route.request().url().replace("/css_ltr/", "/css_rtl/") }));
          await context.addInitScript(() => {
            const observer = new MutationObserver(() => {
              if (document.body) {
                document.body.classList.replace("ltr", "rtl");
                observer.disconnect();
              }
            });
            observer.observe(document, { childList: true, subtree: true });
          });
        }
        const page = await context.newPage();
        const errors = [];
        page.on("pageerror", (error) => errors.push(error.message));
        try {
          await page.goto(`${baseUrl}/dorf1.php?newdid=${encodeURIComponent(villageId)}`);
          await page.waitForLoadState("networkidle");
          const production = page.locator(".villageInfobox.production");
          assert(await production.isVisible(), `${name}: production panel is hidden`);
          assert.equal(await production.locator("tbody td.num").count(), 4);
          for (const cell of await production.locator("tbody td.num").allTextContents()) {
            assert(/\d/.test(cell), `${name}: missing production value`);
          }
          const insideViewport = async (locator) => locator.evaluate((element) => {
            const r = element.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && r.x >= 0 && r.y >= 0 &&
              r.right <= innerWidth && r.bottom <= innerHeight;
          });
          assert(await insideViewport(production), `${name}: production is outside the viewport`);
          // The fixed building queue is centred with a mirrored transform in RTL; it must stay
        // fully on screen, whichever account state (queued or idle) the village is in.
        const queue = page.locator(".buildingList");
        if (label && await queue.count() === 0) {
          assert.fail(`${name}: the fixture village has no queued construction`);
        }
        if (await queue.count() > 0) {
          await settled(page);
          assert(await insideViewport(queue.first(), "x"), `${name}: building queue is outside the viewport`);
        }
        const units = page.locator(".villageInfobox.units");
          if (process.env.E2E_REQUIRE_TROOPS === "1") {
            assert(await units.locator("td.num").count() > 0, `${name}: choose an owned village with troops`);
          }
          const toggle = page.locator("label.showTroopsToggleMobile");
          if (await toggle.isVisible()) {
            const checkbox = page.locator("#showTroopsToggleMobile");
            const original = await checkbox.isChecked();
            const setOpen = async (open) => {
              if (await checkbox.isChecked() !== open) {
                await Promise.all([
                  page.waitForResponse((response) =>
                    response.url().includes("preferences") && response.request().method() === "POST"),
                  toggle.tap(),
                ]);
              }
            };
            try {
              await setOpen(true);
              await settled(page);
              assert(await units.isVisible(), `${name}: troop panel did not open`);
              assert(await insideViewport(units), `${name}: troops are outside the viewport`);
              const size = await units.boundingBox();
              const row = await units.locator("tbody tr").first().boundingBox();
              assert(size.height >= row.height, `${name}: troop counts are clipped`);
              const values = units.locator("td.num, td.noTroops");
              assert(await values.count() > 0, `${name}: no troop counts or empty-state message`);
              for (const cell of await values.all()) {
                // Centre the cell: the queue and footer overlays are fixed to the viewport edges.
                await cell.evaluate((element) => element.scrollIntoView({ block: "center", inline: "center" }));
                await settled(page);
                assert(await cell.evaluate((element) => {
                  const r = element.getBoundingClientRect();
                  return element.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2));
                }), `${name}: troop count or empty-state message is covered or clipped`);
              }
              for (const cell of await production.locator("td.num").all()) {
                assert(await cell.evaluate((element) => {
                  const r = element.getBoundingClientRect();
                  return element.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2));
                }), `${name}: production value is covered by another control`);
              }
              if (process.env.E2E_CAPTURE_DIR) {
                fs.mkdirSync(process.env.E2E_CAPTURE_DIR, { recursive: true });
                await page.screenshot({
                  path: `${process.env.E2E_CAPTURE_DIR}/${name.replaceAll(" ", "-")}.png`,
                });
              }
              await page.reload();
              await page.waitForLoadState("networkidle");
              await settled(page);
              assert(await checkbox.isChecked(), `${name}: open troop panel is lost on reload`);
              assert(await units.isVisible(), `${name}: remembered troop panel is hidden`);
              await setOpen(false);
              assert(!(await units.isVisible()), `${name}: troop panel did not close`);
            } finally {
              await setOpen(original);
            }
          } else {
            assert(await units.isVisible(), "Desktop troop table must remain visible");
          }
          assert.deepEqual(errors, [], `${name}: browser exceptions`);
          console.log(`${name}: four production values and troop visibility/toggle checks passed.`);
        } catch (error) {
          await captureFailure(page, name, error);
          throw error;
        } finally {
          await context.close();
        }
      }
    }
  } finally {
    await browser.close();
    await queued?.stop();
  }
})().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
