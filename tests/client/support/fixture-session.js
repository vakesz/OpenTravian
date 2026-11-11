"use strict";

// A throwaway fixture player for browser tests (tests/support/village-fixture-session.php): it is created in the local
// world through `docker compose exec`, lives while the helper process runs and is removed when `stop()` ends it.
// `mode` selects the seeded state ("", "visual" or "auctions"; see that script).

const path = require("node:path");
const { spawn } = require("node:child_process");

const root = path.resolve(__dirname, "../../..");

async function startFixture(mode = "") {
  const args = ["compose", "exec", "-T", "php", "php", "tests/support/village-fixture-session.php"];
  if (mode !== "") {
    args.push(mode);
  }
  const child = spawn("docker", args, { cwd: root, stdio: ["pipe", "pipe", "inherit"] });
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
  const stop = async () => { child.stdin.end(); await exited; };
  if (!account) {
    await stop();
    return null;
  }
  return { account, stop };
}

// Logs in as the account and returns the browser context's storage state.
async function loginState(browser, baseUrl, account) {
  const context = await browser.newContext();
  try {
    const page = await context.newPage();
    await page.goto(baseUrl);
    await page.locator('input[name="name"]').waitFor({ timeout: 15000 });
    await page.locator('input[name="name"]').fill(account.name);
    await page.locator('input[name="password"]').fill(account.password);
    await Promise.all([page.waitForNavigation(), page.locator('button[type="submit"]').click()]);
    return await context.storageState();
  } finally {
    await context.close();
  }
}

module.exports = { startFixture, loginState };
