"use strict";

// Keyboard behaviour of the public pages: every public dialog keeps Tab and Shift+Tab inside it
// (visible, reachable controls only), Escape closes it and returns focus to its opener, the home
// news card shows a focus ring, and the registration email field is linked to its error message.
// Needs no login; run with dev/client-tests/run.sh test-public-a11y.

const assert = require("node:assert/strict");
const { chromium } = require("playwright");

const baseUrl = process.env.E2E_BASE_URL || "http://localhost:8080";
const active = (page) => page.evaluate(() => {
  const element = document.activeElement;
  return {
    inside: Boolean(element.closest('.homepageDialog:not([hidden])')),
    label: `${element.tagName.toLowerCase()}${element.id ? `#${element.id}` : ''}${element.name ? `[${element.name}]` : ''}.${String(element.className).split(' ')[0]}"${(element.getAttribute('aria-label') || element.value || element.textContent || '').trim().slice(0, 14)}"`,
  };
});

// Presses Tab (or Shift+Tab) until a stop repeats; every stop must be inside the dialog.
async function tabsStayInside(page, name, shift) {
  const seen = [];
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press(shift ? "Shift+Tab" : "Tab");
    const stop = await active(page);
    assert.ok(stop.inside, `${name}: ${shift ? "Shift+Tab" : "Tab"} left the dialog for ${stop.label}`);
    // The cycle closes when a stop repeats: focus wrapped without ever leaving the dialog.
    if (seen.includes(stop.label)) {
      return seen;
    }
    seen.push(stop.label);
  }
  throw new Error(`${name}: focus never wrapped within 40 presses (${seen.slice(0, 6).join(", ")}...)`);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const dialogs = [
      { name: "registration", path: "/homepage", open: (page) => page.locator("button.homepageLobbyCta").click(), opener: "button.homepageLobbyCta", dialog: "#homepageRegistrationDialog" },
      { name: "language", path: "/homepage", open: (page) => page.locator("button.homepageLanguage").click(), opener: "button.homepageLanguage", dialog: "#homepageLanguageDialog" },
      { name: "menu", path: "/homepage", open: (page) => page.locator("button.homepageMenuButton").click(), opener: "button.homepageMenuButton", dialog: "#homepageNavDialog" },
    ];
    for (const { name, path, open, opener, dialog } of dialogs) {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(baseUrl + path, { waitUntil: "networkidle" });
      await page.locator(opener).focus();
      await open(page);
      await page.locator(dialog).waitFor({ state: "visible" });
      const forward = await tabsStayInside(page, name, false);
      // The stops a keyboard can reach follow from the dialog's state: an open registration has its form, a closed one
      // holds only the close control, and every other dialog has several.
      const reachable = await page.evaluate((selector) => {
        const radios = new Set();
        return [...document.querySelector(selector).querySelectorAll("a[href], button, input, select, textarea, [tabindex]")].filter((control) => {
          if (control.disabled || control.tabIndex < 0 || control.type === "hidden" || control.closest("[hidden]")) {
            return false;
          }
          const box = control.getBoundingClientRect();
          if (getComputedStyle(control).visibility === "hidden" || box.width === 0 || box.height === 0) {
            return false;
          }
          if (control.type === "radio") {
            if (radios.has(control.name)) {
              return false;
            }
            radios.add(control.name);
          }
          return true;
        }).length;
      }, dialog);
      assert.equal(forward.length, reachable, `${name}: Tab must visit exactly the ${reachable} reachable controls (visited ${forward.join(", ")})`);
      if (name === "registration") {
        const registrationOpen = await page.locator(`${dialog} #registration_email`).count();
        assert.ok(registrationOpen ? forward.length >= 2 : forward.length === 1 && forward[0].includes("homepageDialogClose"),
          `${name}: ${registrationOpen ? "an open registration needs its form fields and the close control" : "a closed registration holds only the close control"} (visited ${forward.join(", ")})`);
      } else {
          assert.ok(forward.length >= 2, `${name}: expected at least two focus stops`);
        }
        // Wrapping backwards from the first stop must land on a stop inside the dialog too.
      await tabsStayInside(page, name, true);
      await page.keyboard.press("Escape");
      await page.locator(dialog).waitFor({ state: "hidden" });
      assert.equal(await page.evaluate((selector) => document.activeElement.matches(selector), opener), true, `${name}: Escape must return focus to ${opener}`);
      await context.close();
      console.log(`ok ${name} dialog: focus contained, Escape restores focus`);
    }

    // Consent dialog opens from the menu.
    {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(baseUrl + "/homepage", { waitUntil: "networkidle" });
      await page.locator("button.homepageMenuButton").click();
      await page.locator("button[data-homepage-toggle='homepageConsentDialog']").click();
      await page.locator("#homepageConsentDialog").waitFor({ state: "visible" });
      await tabsStayInside(page, "consent", false);
      await tabsStayInside(page, "consent", true);
      await context.close();
      console.log("ok consent dialog: focus contained");
    }

    // Calendar details dialog (only present while the local world is listed as upcoming or open).
    {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(baseUrl + "/homepage/calendar#calendarGameworldDetails", { waitUntil: "networkidle" });
      if (await page.locator("#homepageCalendarDetailsDialog:not([hidden])").count()) {
        await tabsStayInside(page, "calendar", false);
        await tabsStayInside(page, "calendar", true);
        console.log("ok calendar dialog: focus contained");
      } else {
        console.log("skip calendar dialog: the local world is not listed in the calendar");
      }
      await context.close();
    }

    // Home news card: keyboard focus on its stretched link rings the card.
    {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(baseUrl + "/homepage", { waitUntil: "networkidle" });
      await page.locator(".homepageNewsCard .linkToFullArticle a").first().focus();
      await page.keyboard.press("Shift+Tab");
      await page.keyboard.press("Tab");
      await page.waitForTimeout(500);
      const shadow = await page.evaluate(() => getComputedStyle(document.querySelector(".homepageNewsCard .newsPreview")).boxShadow);
      assert.ok(shadow.includes("rgba(0, 102, 255"), `the focused news card must show the focus ring, got ${shadow}`);
      await context.close();
      console.log("ok home news card: focus ring");
    }

    // Registration email field: invalid state is programmatic and tied to the visible message.
    {
      const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
      const page = await context.newPage();
      await page.goto(baseUrl + "/homepage#registration", { waitUntil: "networkidle" });
      const email = page.locator("#registration_email");
      if (await email.count()) {
        await email.fill("not-an-email");
        assert.equal(await email.getAttribute("aria-invalid"), "true");
        const describedBy = await email.getAttribute("aria-describedby");
        assert.equal(describedBy, "registrationEmailRuleInvalid");
        assert.equal(await page.locator(`#${describedBy}`).count(), 1, "the described message must exist");
        await email.fill("player@example.com");
        assert.equal(await email.getAttribute("aria-invalid"), null);
        assert.equal(await email.getAttribute("aria-describedby"), null);
        console.log("ok registration email: aria-invalid and aria-describedby follow the validation state");
      } else {
        assert.ok(await page.locator("#homepageRegistrationDialog .registerNote").count(), "a closed registration must say so");
        console.log("skip registration email: registration is closed in this world");
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
