# Development

Everything here is for working on the game.

## Prerequisites

- Docker with Compose v2.
- Node.js 24 with npm, for the client tests (`cd dev/client-tests && npm ci && npx playwright install chromium` is a one-time step per machine).
- `curl` and `gpg` on the host, for the PHPStan download (`dev/phpstan/fetch.sh`).

## Working copy

Development layers `compose.dev.yaml` over the production `compose.yaml`: the checkout is bind-mounted into the containers, so PHP and static-file changes show up without a rebuild. Enable it once in `.env`:

```sh
sh config/bootstrap-env.sh --dev     # writes .env with COMPOSE_FILE=compose.yaml:compose.dev.yaml
docker compose up -d --build --wait
dev/install-world.sh                 # installs a throwaway world headlessly
```

Without `COMPOSE_FILE` Compose uses `compose.yaml` alone, which is the production configuration (see [deploy.md](deploy.md)).

Repository layout: `game/` is the only game runtime, `assets/gpack/travian/mainPage/` is the only game pack, `database/` holds the two SQL schemas (no migrations: edit `world.sql` / `account.sql` and reinstall), `services/api/` is the companion HTTP service, `tools/` holds operator CLIs, and `dev/` holds dev-only helpers.

## Checks

Run these before handing off a change. None of the dev tooling is part of a runtime image.

```sh
dev/check.sh              # compose config, php -l, every contract and runtime check, PHPStan, client tests, nginx -t
dev/check.sh --fast       # lint and PHPStan on changed files, changed contracts, no-minified and gpack guards
dev/check.sh --no-browser # skip the Playwright tests
dev/check.sh --build      # also build the production images
```

In VS Code the same commands are tasks (**Terminal > Run Task**, `.vscode/tasks.json`), including one that runs the open contract or runtime check.

It runs against the running dev stack, prints a summary table and exits non-zero on any failure. Contracts and runtime checks run one PHP process per file (60 s each, 40 per `compose exec`); a file fails on a non-zero exit, a timeout, or any PHP Warning, Notice or Deprecated. `dev/phpstan/run.sh --files <path.php> ...` analyses only those files. They use the installed world's database, so run them against a world you can spare.

`dev/install-hooks.sh` opts in to a pre-commit hook (`dev/check.sh --fast`; undo with `git config --unset core.hooksPath`). It checks the working tree, so a partly staged file is checked with its unstaged edits, and it refuses the commit while Docker is down: use `git commit --no-verify`.

`dev/install-world.sh` installs a world headlessly through the CLI installer (`tools/install.php`, see [install.md](install.md#headless-install)) with the starter player `player` (its password is generated into `dev/client-tests/.password.local`), no SMTP, and writes the missing `dev/client-tests` settings. CI (`.github/workflows/ci.yml`) runs `config/bootstrap-env.sh --dev`, starts the stack, runs it, then `dev/check.sh --build`; no secrets are used.

PHP syntax (every file except vendored trees):

```sh
docker run --rm -v "$PWD:/work:ro" php:8.5-cli sh -c 'find /work -type f -name "*.php" ! -path "*/vendor/*" -print0 | xargs -0 -n1 php -l'
docker compose config --quiet
```

Render and test the nginx template the way the image does (the `web` service sets `TRAVIAN_TRUSTED_PROXY`):

```sh
docker run --rm -e TRAVIAN_TRUSTED_PROXY=127.0.0.1 -e NGINX_ENVSUBST_FILTER='^TRAVIAN_' -v "$PWD/docker/nginx/default.conf.template:/etc/nginx/templates/default.conf.template:ro" nginx:stable-alpine nginx -t
```

Readable assets, minified images (no minified file may be committed; `tests/contracts/test-no-minified-assets-contract.php` enforces it, and also rejects `*.min.*` and `*_compressed*` file names):

- The repository stores CSS, JS and SVG in readable form, including vendored libraries (`game/deployment/public/js/default/VENDOR_PROVENANCE.md`). Minified CSS, JS and SVG are formatted with the pinned `dev/asset-format/format.mjs` (`npm ci` there first) and checked with `verify-equivalence.mjs <original> <formatted>`; never hand-edit them.
- Development bind-mounts the checkout, so the readable files are served directly. The production web image (`docker/nginx/Dockerfile`, builder stage pinned by digest, tool versions pinned in `docker/assets/package-lock.json`) minifies CSS/JS (esbuild, legal comments kept), optimises SVG (svgo), losslessly recompresses PNG/JPEG (oxipng, jpegtran; WebP/GIF skipped) and writes `.gz` siblings for nginx `gzip_static`. Build it with `COMPOSE_FILE=compose.yaml:compose.build.yaml docker compose build web`.
- Cache-busting query strings are content hashes (`asset_content_version()` in `game/include/functions.general.php`).
- Refreshing image-build pins (`docker/nginx/Dockerfile`, `docker/assets/package*.json`): the builder base is pinned by digest (`docker buildx imagetools inspect node:24-alpine` prints the current index digest), npm tools by exact version plus lockfile (`npm install --save-exact` in `docker/assets`, versions at least two weeks old), and oxipng by GitHub release URL plus per-architecture SHA-256 (`shasum -a 256` the `oxipng-<ver>-{x86_64,aarch64}-unknown-linux-musl.tar.gz` assets). jpegtran is the one Alpine package pin (`libjpeg-turbo-utils=<ver>-rN`); if the mirror drops that revision, the build fails at `apk add`. Look up the current revision with `docker run --rm node:24-alpine sh -c 'apk update >/dev/null && apk policy libjpeg-turbo-utils'`, update the pin, rebuild, and re-run the pixel-identity check (decode before and after, compare) before committing.

Gpack asset contracts (run after touching assets, stylesheets, templates or client-side asset references):

```sh
docker compose exec -T php php tests/contracts/test-gpack-css-assets-contract.php
docker compose exec -T php php tests/contracts/test-gpack-static-asset-contract.php
```

Contracts and runtime checks are standalone scripts and must run one per process; see [tests/README.md](../tests/README.md):

```sh
docker compose run --rm --no-deps -T php php tests/contracts/<name>.php
docker compose run --rm --no-deps -T php php tests/runtime/<name>.php
```

### Client tests (Playwright, dev only)

`tests/client/*.js` are Node checks; `test-mobile-village-panels.js`, `test-public-a11y.js` (public-page keyboard behaviour, no login) and `test-auction-lists-client.js` (the auction lists update in place; it creates a throwaway player with auction data through `tests/support/village-fixture-session.php auctions`) need a browser. Its pinned Playwright lives in `dev/client-tests/` (`package.json` + `package-lock.json`). `node_modules`, `.env.local` and `.password.local` are gitignored, `dev/` is in `.dockerignore`, and Renovate tracks the npm manifest in the "dev tooling" group. The browser binary is installed per machine, not committed.

```sh
cd dev/client-tests && npm ci && npx playwright install chromium   # once
cp .env.example .env.local                                           # then set E2E_VILLAGE_ID (an owned village id)
printf '%s' '<password>' > .password.local                      # password for E2E_USER
dev/client-tests/run.sh                          # every tests/client/test-*.js
dev/client-tests/run.sh test-mobile-village-panels   # selected tests
```

`.env.local` keys: `E2E_BASE_URL` (default `http://localhost:8080`, loopback only), `E2E_USER`, `E2E_PASSWORD_FILE`, `E2E_VILLAGE_ID`. The panel test logs in, toggles the local troop-panel preference and restores the original value afterwards. It also logs in as a throwaway fixture player with one queued construction (created and removed by `tests/support/village-fixture-session.php` through `docker compose exec`; skipped with a notice when Docker is unavailable). Optional: `E2E_REQUIRE_TROOPS=1`, `E2E_CAPTURE_DIR=<dir>` for screenshots. A failing case always writes `<case>-failure.png/.html/.json` (element boxes and what covers each troop cell) to `E2E_CAPTURE_DIR` or the system temp directory.

### Visual regression screenshots (opt-in, dev only)

`tests/visual/` compares 1920×1080 screenshots of the key local routes (dorf1, dorf2, a building, reports, messages, the map, hero, statistics, alliance, options, the homepage and the registration overlay) with baselines that you generate locally in `tests/visual/baselines/<name>.png` (untracked; the first `--update` creates them). It uses the Playwright and `pngjs` pins in `dev/client-tests/` (same one-time setup as the client tests). `tests/` and `dev/` are in `.dockerignore`, so neither the runner nor the baselines reach an image.

```sh
dev/visual-tests/run.sh                 # compare every route
dev/visual-tests/run.sh map hero        # only these routes (names in tests/visual/routes.json)
dev/visual-tests/run.sh --update        # rewrite the baselines after an intended visual change
dev/check.sh --visual                   # the same compare as an extra step of the full check
```

The state is deterministic: a throwaway `zzfxvisual` player (created and removed by `tests/support/village-fixture-session.php visual` through `docker compose exec`) with full stores, an alliance `zzfx visual [zzvis]` and no queued construction; the page clock is fixed, animations are off, the theme is light and the timezone UTC. Dynamic regions (timers, the server clock, the resource bar, row dates) are hidden before each capture; the selectors are the `mask` lists in `routes.json`. A route fails when more than `maxDiffRatio` (0.1%) of its pixels differ by more than `pixelTolerance` (8 of 255 per channel); both can be overridden per route. A failure writes `<name>-actual.png` and `<name>-diff.png` (changed pixels red) to the gitignored `tests/visual/out/`. A route without a baseline is reported as `no baseline ... run --update` and does not fail.

To update: change the UI, run `dev/visual-tests/run.sh --update`, open the changed PNGs to review them. Baselines come from one machine's Chromium text rendering, so regenerate them on the machine that compares if a clean tree fails everywhere by a few pixels. To add a route, append it to `routes.json` and run `--update <name>`; if a run is flaky, find the moving element in `out/<name>-diff.png` and add its selector to `mask`.

### Request timing footer (dev only)

The dev compose layer sets `TRAVIAN_DEV_TOOLS=1` on the `php` service (nothing in production does, and it is the only development switch). It also turns OPcache timestamp checks back on, because this layer bind-mounts the checkout; the published image leaves them off. It makes a request timing badge available, but the badge is off until a browser opts in: open any page with `?devFooter=1` (a cookie remembers it) and `?devFooter=0` turns it off. While on, every response carries a `Server-Timing` header and each HTML page gets one small `position:fixed` badge at the bottom left (time, query count and query time, peak memory) added after the page is built, outside every template, with `pointer-events:none`, so it cannot change the layout. With it off, which is the default even in dev, pages are exactly what the game renders, so screenshots and DOM fingerprints are unaffected.

### Static analysis (PHPStan, dev only)

PHPStan is a pinned PHAR kept under `dev/phpstan/`. It is gitignored, listed in `.dockerignore`, and outside every Dockerfile `COPY`, so it never reaches the production image. The version is in `dev/phpstan/VERSION` and its SHA-256 in `dev/phpstan/phpstan.phar.sha256`; Renovate tracks the version, and a bump must also update the checksum.

```sh
dev/phpstan/fetch.sh                   # once; verifies SHA-256 and the GPG signature (needs curl and gpg on the host)
dev/phpstan/run.sh                     # all targets: game, api, tests
dev/phpstan/run.sh game                # one target (game, api or tests)
PHPSTAN_LEVEL=3 dev/phpstan/run.sh game     # try a higher level
dev/phpstan/run.sh game -- --error-format=raw   # extra PHPStan arguments after --
```

`game/` and `services/api/` are separate runtimes that share the `Core` namespace, so each target has its own config (`game.neon`, `api.neon`, `tests.neon`) on top of `common.neon`. PHPStan runs at level 0, the lowest level: it finds syntax-level and undefined-symbol mistakes, not type errors; raising it is open work. `baseline.neon` only silences the template `Variable $x might not be defined` family under `game/include/resources/Templates/`; do not add other errors to it.

## Local differences

Contract tests check these rules.

- Documented local difference (village center, Harbor): the level badge sits on the sprite roof, and its slot stacks above the wall slot so the whole sprite is clickable. That placement is one rule in `lang/overrides.css`. The pack draws the sprite once under `.behindContent .harbour`.
- Video rewards are intentionally absent. Daily quests award 25, 50, 75, and 100 points, and the other quests add up to 100 points, with no Watch-video control and no video-bonus path (adventure duration and danger, resource production boost, build, smithy and academy speed-ups, daily video quests, and the build `vft` token).
- **Train maximum**, on every unit row of a troop building, queues the whole affordable amount and charges it like a typed order. It exists only while the `speedShortcuts` world rule is on.
- **Smithy gold max.** **Upgrade to max level** (default 20 gold, `smithyMaxLevelGold`) sets that unit's smithy level to 20 and clears its queued research. **Upgrade all to max level** (default 100 gold, `smithyUpgradeAllToMaxGold`) does the same for every unit. Both charge gold only. Prices and behaviour are in [Speed-world shortcuts](world-speed.md#speed-world-shortcuts).
- World stats (`/homepage/world`) is a local-only public page reached by direct URL. No footer or menu links to it.
