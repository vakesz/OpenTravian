# Tools

- `backup.php`: `backup`, `list [--dir=PATH]`, `verify [<backup-name>] [--dir=PATH]` and `restore <backup-name> --yes [--world-only] [--no-state] [--dir=PATH]` for the world and account databases through the application's own connections, with the retention, export and state-archive settings described in [operate.md](../docs/operate.md#backups). `--dir` reads backups from another directory such as the host export. Restore needs the engine stopped, takes a safety backup first (protecting the chosen backup from retention) and restores the encrypted state archive when the backup has one.
- `rotate-db-password.php`: rotates the password of the dedicated application database user. Pass the database administrator password in `TRAVIAN_DB_ADMIN_PASSWORD` and confirm with `--yes`. It journals the new password in a 0600 file first, alters the account on every host, rewrites `connection.local.php` and `globalConfig.php` and checks the login, so an interrupted run is finished by running the same command again. Restart the engine afterwards.
- `metrics-token.php`: creates (or with `--rotate` replaces) the bearer token that guards the internal Prometheus `/metrics` endpoint (`metrics-token.local` in the state volume, mode 0600); it prints the file path, never the value. See [operate.md](../docs/operate.md#metrics).
- `translations.php`: `export <locale> [--format=csv|po] [--out=FILE] [--bom]` lists every en-US string with the locale's current value and its source (client bundle, capture or hand-written); `import <locale> <file> [--apply] [--create-hand-file] [--strict]` reads an edited file back into the hand catalogue `Translation/<locale>.php` after checking placeholders, never writes bundle, capture, computed or list entries and refuses en-US; `status <locale>` counts strings by source. See [translations.md](../docs/translations.md).
- `preflight.php`: the operator preflight (PHP, state directory, disk, and with an installed world the database, Redis, engine heartbeat, SMTP and speed). Prints one line per check and exits 1 on a failure; run it with `docker compose exec -u www-data php php tools/preflight.php` right after `docker compose up`. The control panel's Engine health page shows the same results.
- `set-world-speed.php`: changes an installed local world's speed atomically across its generated connection file and `gameServers` row, then runs the safe village/hero reconciliation. Use `--speed=<1..1000000000> --from-speed=<current installed speed>` for a dry run first, then repeat it with `--apply`. It preserves the generated state file's owner and mode, protects concurrent changes with a state-volume lock, and refuses every mismatched source speed before it can rescale hero experience.
- `reconcile-world-speed-profile.php`: recalculates persisted `vdata.maxstore`, `maxcrop`, hourly production, and upkeep from each village's actual buildings. Use it to repair a legacy profile or as the internal second phase of `set-world-speed.php`; do not re-run it with an old `--from-speed` after a successful speed change because that would rescale hero experience again.
- Dev-only helpers (PHPStan, Playwright client tests, the public homepage catalog generator) live in `dev/`, not here; see [development](../docs/development.md) and "Public homepage, registration and help" in [configuration](../docs/configuration.md#public-homepage-registration-and-help). They are not part of any runtime image.
- Test code lives in [`tests/`](../tests/README.md), separated into contracts, runtime checks, client checks, and fixtures.

## World maintenance

- `regenerate-waters.php` stays here because it changes an installed world.
- `regenerate-waters.php` (`--replace`, `--seed`, `--apply --undo=FILE`, `--revert=FILE`) gives an installed world solid seas, rivers and large lakes (6 to 8 percent of the map) or re-renders its water: it keeps every village, owned oasis, the start area and Wonder sites, keeps an existing harbor village on its shore, clears the forest, sprite sheets, free oases and unclaimed village sites under the new water, restores dried-up water as village sites and flushes the map caches; the dry run reports every count. `flush-map-cache.php` clears the cached map block and mark images, the stored zoom-3 block rows and bumps the other block versions once after the map size or art changes.

## Quarantined engine tasks

Quarantined engine tasks (rows that failed 5 times) are moved out of their queue table into `scheduled_task_failures` with `quarantined_at` set and the full row as JSON in `payload`. List them:

```sh
docker compose exec db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" travian_world -e "SELECT task_table, task_id, last_error, quarantined_at FROM scheduled_task_failures WHERE quarantined_at IS NOT NULL"'
```

The control panel's Engine health page lists them with the error and time and has a Requeue button (a CSRF-protected, audit-logged POST that calls the same `TransactionalTask::requeue()`). After fixing the cause, or from the command line, restore a task with the game bootstrap loaded (tables: `building_upgrade`, `demolition`, `movement`, `research`, `send`; replace `movement` and `123` with the table and id from the listing):

```sh
docker compose exec -u www-data php php -r 'require "/travian/game/deployment/include/env.php"; require "/travian/game/include/bootstrap.php"; var_dump(\Core\Jobs\TransactionalTask::requeue("movement", 123));'
```

`requeue()` re-inserts the row and deletes the ledger entry. It returns `false` if nothing is quarantined under that id and throws if the row id already exists.
