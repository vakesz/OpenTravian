"use strict";

// The public homepage's hero stage covers its box in every window shape: it is at least as wide and as tall as the hero,
// centred, and anchored to the top, like the reference's. A window wider than 16:9 must crop the bottom of the artwork
// rather than leave bands at the sides (that is what the stage did when it fitted its height). The Play now button keeps the
// reference's 456x130 frame around a 440x112 face, and a long translation shrinks to one line inside it. Needs no login.

const assert = require("node:assert/strict");
const { chromium } = require("playwright");

const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
const windows = [[1920, 1080], [2000, 1020], [2560, 1300], [1440, 900], [1280, 800], [390, 844]];

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const [width, height] of windows) {
      const context = await browser.newContext({ viewport: { width, height } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/homepage`, { waitUntil: "networkidle" });
      const box = await page.evaluate(() => {
        const rect = (selector) => {
          const { x, y, width, height } = document.querySelector(selector).getBoundingClientRect();
          return { x, y, width, height };
        };
        return { stage: rect(".homepageStageSvg"), hero: rect(".homepageHero") };
      });
      const label = `${width}x${height}`;
      assert.ok(box.stage.x <= box.hero.x + 1, `${label}: the stage must reach the left edge (left ${box.stage.x})`);
      assert.ok(box.stage.x + box.stage.width >= box.hero.x + box.hero.width - 1, `${label}: the stage must reach the right edge`);
      assert.ok(box.stage.y <= box.hero.y + 1, `${label}: the stage starts at the top`);
      assert.ok(box.stage.y + box.stage.height >= box.hero.y + box.hero.height - 1, `${label}: the stage must reach the bottom`);
      assert.ok(Math.abs(box.stage.width / box.stage.height - 2688 / 1512) < 0.01, `${label}: the stage keeps the artwork's proportions`);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), 0, `${label}: no horizontal scroll`);
      await context.close();
      console.log(`ok ${label}: the stage covers the hero`);
    }

    for (const locale of ["en-US", "de-DE", "fr-FR", "bg-BG", "id-ID", "ar-AE"]) {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(`${baseUrl}/homepage?homepageLocale=${locale}`, { waitUntil: "networkidle" });
      await page.evaluate(() => document.fonts.ready);
      await page.waitForFunction(() => {
        const face = document.querySelector(".homepageLobbyCta").firstElementChild;
        const range = document.createRange();
        range.selectNodeContents(face);
        return range.getBoundingClientRect().width <= face.clientWidth - 79;
      }, null, { timeout: 5000 }).catch(() => {});
      const cta = await page.evaluate(() => {
        const button = document.querySelector(".homepageLobbyCta");
        const face = button.firstElementChild;
        const range = document.createRange();
        range.selectNodeContents(face);
        const size = (rect) => [Math.round(rect.width), Math.round(rect.height)];
        const padding = parseFloat(getComputedStyle(face).paddingLeft) * 2;
        return { button: size(button.getBoundingClientRect()), face: size(face.getBoundingClientRect()), text: range.getBoundingClientRect(), padding };
      });
      assert.deepEqual(cta.button, [456, 130], `${locale}: the button frame`);
      assert.deepEqual(cta.face, [440, 112], `${locale}: the button face`);
      assert.ok(cta.text.height < 80, `${locale}: the label stays on one line`);
      assert.ok(cta.text.width <= cta.face[0] - cta.padding + 1, `${locale}: the label fits inside the face ${JSON.stringify(cta)}`);
      await context.close();
      console.log(`ok ${locale}: the Play now button keeps its size and the label fits`);
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
