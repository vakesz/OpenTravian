"use strict";

// The out-of-game support window: its title is drawn in the header bar next to the close button, the selects read
// "Choose", and the window's height counts in the page, so the footer sits below it whether the delivery message and the
// field errors make it taller or not. Runs at the desktop sizes and a phone width; needs no login.

const assert = require("node:assert/strict");
const { chromium } = require("playwright");

const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
const windows = [[1920, 1080], [2000, 1020], [390, 844]];

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const [width, height] of windows) {
      for (const errors of [false, true]) {
        const context = await browser.newContext({ viewport: { width, height }, isMobile: width < 500 });
        const page = await context.newPage();
        await page.goto(`${baseUrl}/support/ticket`, { waitUntil: "networkidle" });
        if (errors) {
          // The submit button is disabled while delivery is unconfigured; submit the form to list every field error.
          await Promise.all([page.waitForNavigation(), page.evaluate(() => document.querySelector("#support").requestSubmit())]);
        }
        const label = `${width}x${height}${errors ? " with errors" : ""}`;
        const layout = await page.evaluate(() => {
          const rect = (element) => {
            const { x, y, width, bottom, right } = element.getBoundingClientRect();
            return { x, y, width, bottom, right };
          };
          const title = document.querySelector("h1.titleInHeader");
          const footerLinks = [...document.querySelectorAll("#pageLinks a")].filter((link) => link.getBoundingClientRect().height > 0);
          return {
            box: rect(document.querySelector("#contentOuterContainer")),
            title: title && getComputedStyle(title).display !== "none" ? rect(title) : null,
            titleText: title ? title.textContent.trim() : "",
            footerTop: footerLinks.length ? Math.min(...footerLinks.map((link) => link.getBoundingClientRect().y)) : null,
            options: [...document.querySelectorAll("#support_gameworld option:first-child, #support_type option:first-child")].map((option) => option.textContent.trim()),
            cards: document.querySelectorAll(".supportCardHeading").length,
            scrollX: document.documentElement.scrollWidth - innerWidth,
          };
        });
        assert.equal(layout.titleText, "Support", `${label}: the title is in the header`);
        assert.ok(layout.title, `${label}: the title must be visible`);
        assert.ok(layout.title.y >= layout.box.y && layout.title.bottom <= layout.box.y + 80, `${label}: the title sits in the header bar (${JSON.stringify(layout.title)})`);
        assert.deepEqual(layout.options, ["Choose", "Choose"], `${label}: both selects say Choose`);
        assert.equal(layout.cards, 0, `${label}: the card repeats no heading`);
        if (layout.footerTop !== null) {
          assert.ok(layout.footerTop >= layout.box.bottom, `${label}: the footer (${layout.footerTop}) must sit below the window (${layout.box.bottom})`);
        }
        await context.close();
        console.log(`ok ${label}`);
      }
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
