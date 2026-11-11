"use strict";

// Visual regression: screenshots of the key local routes at 1920x1080 against committed baselines.
//
//   dev/visual-tests/run.sh [--update] [name ...]
//
// A throwaway fixture player (tests/support/village-fixture-session.php visual) gives a deterministic
// world state; dynamic regions (timers, clocks, resources) are hidden before each capture, see
// routes.json. Baselines live in tests/visual/baselines; a missing baseline is reported, not a failure.

const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require("playwright");
const { PNG } = require("pngjs");
const { startFixture, loginState } = require("../client/support/fixture-session.js");

const root = path.resolve(__dirname, "../..");
const baselineDir = path.join(__dirname, "baselines");
const outDir = path.join(__dirname, "out");
const config = JSON.parse(fs.readFileSync(path.join(__dirname, "routes.json"), "utf8"));
const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
const args = process.argv.slice(2);
const update = args.includes("--update");
const selected = args.filter((arg) => !arg.startsWith("--"));
const frozenTime = new Date("2026-01-01T12:00:00Z");

if (!["localhost", "127.0.0.1"].includes(new URL(baseUrl).hostname)) {
  throw new Error("Visual tests create a fixture player; only loopback worlds are supported.");
}
const routes = config.routes.filter((route) => selected.length === 0 || selected.includes(route.name));
if (routes.length === 0) {
  throw new Error(`No route matches: ${selected.join(", ")}`);
}

async function startVisualFixture() {
  const fixture = await startFixture("visual");
  if (!fixture) {
    throw new Error("The fixture player could not be created (needs docker compose and the php container).");
  }
  return fixture;
}

// Fonts, two animation frames and no animation or caret: the same page renders the same pixels.
const STABILISE_CSS = `*, *::before, *::after { animation: none !important; transition: none !important; caret-color: transparent !important; }`;

async function capture(browser, storageState, route) {
  const context = await browser.newContext({
    viewport: config.viewport,
    deviceScaleFactor: 1,
    colorScheme: "light",
    reducedMotion: "reduce",
    locale: "en-US",
    timezoneId: "UTC",
    storageState: route.auth ? storageState : undefined,
  });
  try {
    const page = await context.newPage();
    await page.clock.setFixedTime(frozenTime);
    await page.goto(baseUrl + route.path, { waitUntil: "networkidle" });
    if (route.waitFor) {
      await page.locator(route.waitFor).first().waitFor({ timeout: 10000 });
    }
    const masks = [...config.mask, ...(route.mask || [])];
    await page.addStyleTag({ content: `${STABILISE_CSS}\n${masks.join(", ")} { visibility: hidden !important; }` });
    await page.evaluate(async () => {
      await document.fonts.ready;
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    });
    return await page.screenshot({ type: "png" });
  } finally {
    await context.close();
  }
}

// Share of pixels whose channels differ by more than the tolerance, plus a diff image (changed pixels red).
function compare(expectedBuffer, actualBuffer) {
  const expected = PNG.sync.read(expectedBuffer);
  const actual = PNG.sync.read(actualBuffer);
  if (expected.width !== actual.width || expected.height !== actual.height) {
    return { ratio: 1, note: `size ${actual.width}x${actual.height}, baseline ${expected.width}x${expected.height}` };
  }
  const diff = new PNG({ width: expected.width, height: expected.height });
  let changed = 0;
  for (let i = 0; i < expected.data.length; i += 4) {
    const delta = Math.max(
      Math.abs(expected.data[i] - actual.data[i]),
      Math.abs(expected.data[i + 1] - actual.data[i + 1]),
      Math.abs(expected.data[i + 2] - actual.data[i + 2]),
    );
    const differs = delta > config.pixelTolerance;
    changed += differs ? 1 : 0;
    diff.data.set(differs ? [255, 0, 0, 255] : [actual.data[i], actual.data[i + 1], actual.data[i + 2], 80], i);
  }
  return { ratio: changed / (expected.width * expected.height), diffImage: PNG.sync.write(diff) };
}

(async () => {
  fs.mkdirSync(baselineDir, { recursive: true });
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  let fixture = null;
  let failed = 0;
  let missing = 0;
  try {
    fixture = await startVisualFixture();
    const storageState = await loginState(browser, baseUrl, fixture.account);
    for (const route of routes) {
      const actual = await capture(browser, storageState, route);
      const baselineFile = path.join(baselineDir, `${route.name}.png`);
      if (update) {
        fs.writeFileSync(baselineFile, actual);
        console.log(`updated ${route.name}`);
        continue;
      }
      if (!fs.existsSync(baselineFile)) {
        fs.writeFileSync(path.join(outDir, `${route.name}-actual.png`), actual);
        console.log(`no baseline for ${route.name}, run dev/visual-tests/run.sh --update (capture: tests/visual/out/${route.name}-actual.png)`);
        missing++;
        continue;
      }
      const result = compare(fs.readFileSync(baselineFile), actual);
      const limit = route.maxDiffRatio ?? config.maxDiffRatio;
      if (result.ratio > limit) {
        fs.writeFileSync(path.join(outDir, `${route.name}-actual.png`), actual);
        if (result.diffImage) {
          fs.writeFileSync(path.join(outDir, `${route.name}-diff.png`), result.diffImage);
        }
        console.log(`FAIL ${route.name}: ${(result.ratio * 100).toFixed(3)}% of pixels differ (limit ${(limit * 100).toFixed(3)}%)${result.note ? `, ${result.note}` : ""}; see tests/visual/out/${route.name}-diff.png`);
        failed++;
      } else {
        console.log(`ok ${route.name} (${(result.ratio * 100).toFixed(3)}% differ)`);
      }
    }
  } finally {
    await browser.close();
    if (fixture) {
      await fixture.stop();
    }
  }
  if (missing > 0) {
    console.log(`${missing} route(s) without a baseline were captured but not compared.`);
  }
  process.exit(failed > 0 ? 1 : 0);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
