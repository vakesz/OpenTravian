# Tests

Tests are kept outside `tools/`, which contains only operator and asset-maintenance commands.

- `contracts/`: PHP source and markup contracts. Run one with `docker compose run --rm --no-deps -T php php tests/contracts/<name>.php`.
- `runtime/`: PHP checks that exercise the game bootstrap or runtime behavior. Run one with `docker compose run --rm --no-deps -T php php tests/runtime/<name>.php`.
- `client/`: Node-based checks for browser JavaScript. Run all with `dev/client-tests/run.sh` (required for the Playwright test; see [Client tests](../docs/development.md#client-tests-playwright-dev-only) in the README), or one pure-Node check with `node tests/client/<name>.js`.
- `visual/`: screenshot baselines of the key routes at 1920×1080 and their runner. Opt-in: `dev/visual-tests/run.sh` (see [Visual regression](../docs/development.md#visual-regression-screenshots-opt-in-dev-only)); `dev/check.sh --visual` runs it with the other checks.
- `support/`: helpers shared by tests, for example `village-fixture.php`, which clones a throwaway player and village and removes it at shutdown. Tests that spend, build or queue must use it, never a real player's village.
- `fixtures/`: checked-in inputs used by the tests; never write generated output here without updating the associated contract.

PHPStan covers `tests/` too: `dev/phpstan/run.sh tests` (see [Static analysis](../docs/development.md#static-analysis-phpstan-dev-only)).

The backup restore runtime test (`tests/runtime/test-backup-restore-runtime.php`) restores real dumps into throwaway databases, which needs a database user that may CREATE DATABASE: pass `-e TRAVIAN_TEST_ADMIN_PASSWORD=<the administrator password>` (and `TRAVIAN_TEST_ADMIN_USER`, default `root`) to `docker compose exec`. Without it the test prints a SKIPPED banner on stderr and exits 0.

Use the narrowest relevant check while developing. The repository guidance names required contracts for Gpack and behavior changes.
