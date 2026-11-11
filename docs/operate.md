# Operate

## Health and logs

All services report health in `docker compose ps`. nginx waits for PHP-FPM's private ping endpoint before it accepts traffic, and the engine's health check reads its Redis heartbeat without loading the game. The public `/healthz` endpoint reports nginx readiness only; FPM's ping and status routes are never exposed.

```sh
docker compose ps
docker compose logs -f php engine web
```

Application errors go to `error_log.log` in the state volume and to the container logs. Container logs rotate (size-capped) so they cannot fill the disk.

PHP-FPM defaults to eight game workers (256 MB and 60 seconds each). `admin.php` and `setup.php` use a second pool: one worker, 1536 MB, one hour. Tune the `TRAVIAN_FPM_*` values in `.env` only after inspecting the private status endpoint:

```sh
docker compose exec -T php sh -c \
  'SCRIPT_NAME=/fpm-status SCRIPT_FILENAME=/fpm-status REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000'
```

## Backups

Backups run on a schedule: the engine writes a complete gzip-compressed snapshot of both databases to `/var/lib/travian/backups/` every day (`TRAVIAN_BACKUP_ENABLED=0` turns the schedule off). Admin backups (`admin.php?action=backups`) use the same format, and restoring one there restores the world and the account database together, after a safety backup, like `tools/backup.php`. Each backup is two files: `backup-<ms>-<random>.sql.gz` (the world, which the admin page lists) and `backup-<ms>-<random>-account.sql.gz`.

Retention keeps the newest `TRAVIAN_BACKUP_KEEP_LAST` backups plus the newest one of each of the last `TRAVIAN_BACKUP_KEEP_DAILY` days. Backups are on by default, keeping the newest 3 plus one per day for 7 days; the variables are in [configuration.md](configuration.md#environment-reference).

The state volume is lost by `docker compose down -v`, so also export backups to the host. Set `TRAVIAN_BACKUP_EXPORT_DIR=/exports` and bind-mount a host directory there (for example a volume `/srv/travian-backups:/exports` on the `engine` service in a `compose.override.yaml`); every backup is then copied there under the same retention. Each file is copied under a temporary name and renamed when complete, the world dump last, and a failed copy removes what it copied, so the export never holds a partial or half a backup. A failed export is logged, and the Engine health page and the preflight warn when the export directory is missing or behind.

Run, list or verify backups by hand:

```sh
docker compose exec -u www-data php php tools/backup.php backup
docker compose exec -u www-data php php tools/backup.php list
docker compose exec -u www-data php php tools/backup.php verify [<backup-name>]
```

`verify` checks that each dump file is an intact gzip archive that ends with the line `mariadb-dump` writes when it finishes (a dump cut short never reaches it), and exits 1 on any damaged file. Without a name it checks every backup. `list` shows each backup's size.

### Restore

```sh
docker compose stop engine
docker compose exec -u www-data php php tools/backup.php restore <backup-name> --yes [--world-only] [--no-state]
docker compose start engine
```

Restore takes a safety backup first, then replaces the world and account databases (`--world-only` leaves the account database alone). It refuses to run without `--yes` or while the engine is running (a heartbeat no older than 90 seconds; the Redis key of a stopped engine lingers for an hour but is stale after a minute and a half).

Every dump, scheduled, admin or by hand, comes from the one implementation in `DB::dumpTo()`: one consistent snapshot (a single transaction, so the engine can keep running), rows in primary-key order, binary columns as hex, and a temporary file that is renamed into place only when the dump is complete.

### Encrypted state archive

The databases do not hold everything a world needs: the SMTP, payment, social sign-in and CAPTCHA credentials and the administrator two-factor enrolment live in generated state files (`*.local.*` in the state volume). Set `TRAVIAN_BACKUP_STATE_PASSPHRASE` (off by default) and every backup also writes `backup-<ms>-<random>-state.enc`: those files sealed with libsodium (the key is derived from the passphrase with Argon2id, the payload is authenticated, so a wrong passphrase or any change is rejected). `connection.local.php` is left out, because it holds the database login of one database instance and an install creates it again. The passphrase is read from the environment only; it is never written to disk or to a log. Keep a copy of it somewhere else: without it the archive cannot be opened, and the databases still restore with `--no-state`.

The archive is exported, listed (`+state`), verified (`verify` authenticates it when the passphrase is set) and pruned together with its backup. `restore` opens it first and stops early if the passphrase does not match, restores the databases, then writes the state files back with mode 0600; `--no-state` restores the databases only.

### Restore from the export directory

`docker compose down -v` deletes the state volume together with the in-volume backups, and with it the generated configuration the tools read. To recover, reinstall a world with the same settings ([install.md](install.md#reinstalling)), stop the engine, then restore from the host export directory:

```sh
docker compose stop engine
docker compose exec -u www-data php php tools/backup.php list --dir=/exports
docker compose exec -u www-data php php tools/backup.php restore <backup-name> --yes --dir=/exports
docker compose start engine
```

With `TRAVIAN_BACKUP_STATE_PASSPHRASE` set (the same value the backup was made with), this also brings back the credentials and two-factor enrolment of the lost state volume.

## Control panel accounts and roles

Support (user 0) and Multihunter (user 2) are built in owners. **Security > Admin accounts** (`admin.php?action=adminAccounts`, owners only) adds more:

- An extra administrator is an ordinary `users` row with no village, hidden from the statistics, plus a row in `admin_accounts` (`world.sql`) that holds the role. They sign in with their name on the normal game login and land in the control panel. A round reset keeps these accounts, in both modes, and never treats them as players.
- A new account gets a generated temporary password, shown once to the creator. The account can do nothing but open **Change password** until it has replaced it.
- Two roles. An **owner** can do everything. A **moderator** can do everything except Admin accounts, Payments, the gold code generators and Round reset. `AdminRoles::MINIMUM` (`game/include/Game/Admin/AdminRoles.php`) is the one table: the lowest role per page, one line each, and a page that is not listed needs an owner. A moderator does not see the owner-only pages in the menu, and opening one by URL shows "Not allowed" and is audit-logged as `access-denied`. The in-game admin tools (signing in as a player, the staff message to everybody, the Detector) are not part of the table: every administrator has them.
- Disable signs the account out on its next request and refuses its sign-in; Enable restores it; Remove deletes it. Nobody can change themselves, Support, Multihunter or the last enabled owner. Every change is a POST with the CSRF token and a confirmation, and is recorded in the audit log (never the password).
- Two-factor sign-in follows the global setting (Security > Two-factor sign-in): the same gate covers every administrator, there is no per-account switch.
- A world installed before the table existed must be reinstalled like any schema change; without the table only Support and Multihunter can use the panel.

## Control panel settings

Three control panel pages edit settings that used to need a redeploy:

- **World rules** (`admin.php?action=worldRules`, Advanced view) edits the rules chosen at install time. They are stored in the `overrides` key of `connection.local.php`; the website applies a change on the next request and the engine after a restart. The Discord URL and the Palace/Residence gold completion stay in their own Info and settings forms.
- **Operations settings** (`admin.php?action=operationsSettings`) edits the daily backup switch, backup retention, the engine catch-up thresholds (Advanced) and how long audit log entries are kept (365 days by default; `0` keeps them forever). Values are saved in `operations.local.json` in the state directory. A value that was never saved there still comes from its `TRAVIAN_BACKUP_*` or `TRAVIAN_CATCHUP_*` variable, then the default, so existing deployments are unchanged. The engine reads the file on its next round.
- **Engine health** (Advanced view) also lists the generated state files with their modes, flags credential files that other accounts can read, and says whether Prometheus metrics are on.

## Control panel views

The control panel (`/admin.php`) has two views, switched with **Simple | Advanced** in its header. Simple lists what most operators need: the player list, the ban list and player reports, the world's settings and truce, engine health, backups, a test email, two-factor sign-in, the audit log and changing the password. Advanced lists everything, in the same groups: the other player and moderation tools, world tools, round reset, fixes and cleanup, the newsletter and messages, the integrations (legal pages, CAPTCHA, Discord webhook) and gold. The choice is remembered in a cookie of that browser and is only about what is shown: every page opens by its URL in both views, and the page you are on is always in the sidebar. The panel follows the operating system's light or dark colour scheme, like the setup wizard.

## Rotating the database password

The game connects as a dedicated database user (`travian`, or `TRAVIAN_APP_DB_USER`), never as `root`; its password is generated at install and stored only in `connection.local.php` and `globalConfig.php` in the state volume. To change it, run the rotation with the database administrator password (the running services do not hold it), then restart the engine, which keeps its connections while it runs:

```sh
set -a; . ./.env; set +a
docker compose exec -u www-data -e TRAVIAN_DB_ADMIN_PASSWORD="$MARIADB_ROOT_PASSWORD" php php tools/rotate-db-password.php --yes
docker compose restart engine
```

A new random password goes into a 0600 journal first, the account is altered on every host, both files are rewritten and the login is checked before the journal is removed; if the run is interrupted, run the same command again. Web workers use the new password from their next request. The Engine health page and `tools/preflight.php` check that the user has no more access than the game needs.

## Rotating the Redis password

Redis holds only caches, login throttles and the engine heartbeat, so nothing is lost when it restarts. To rotate `REDIS_PASSWORD`, replace the value in `.env` (or delete the line and run `sh config/bootstrap-env.sh`), then recreate the services that use it:

```sh
docker compose up -d --force-recreate redis php engine
```

## Metrics

The stack can expose Prometheus metrics for the engine and the world. They are off until you create a token, and they are never served on the public port: nginx answers 404 for `/metrics` on port 80, and the endpoint lives on a second listener, port 9145 of the `web` service, that is exposed to the Compose network only (never published). Each request also needs a bearer token from protected state, so a scraper that can reach the port still gets nothing without it.

Create the token (the tool prints the file it wrote, never the value; `--rotate` replaces it):

```sh
docker compose exec -u www-data php php tools/metrics-token.php
```

The token is the file `metrics-token.local` in the state volume (mode 0600). Hand it to Prometheus as a credentials file:

```sh
docker compose exec php cat /var/lib/travian/metrics-token.local > travian-metrics-token
```

```yaml
# prometheus.yml, for a Prometheus attached to the stack's frontend Compose network (`<project>_frontend`)
scrape_configs:
  - job_name: travian
    metrics_path: /metrics
    authorization:
      type: Bearer
      credentials_file: /etc/prometheus/travian-metrics-token
    static_configs:
      - targets: ['web:9145']
```

For a Prometheus on the host, publish the port on the loopback address only, in a `compose.override.yaml`, and scrape `127.0.0.1:9145` (the token is still required):

```yaml
services:
  web:
    ports:
      - "127.0.0.1:9145:9145"
```

What is exported (aggregate numbers only; no player, village or alliance names or ids, no addresses, no query text):

| Metric | Meaning |
| --- | --- |
| `travian_engine_up` | 1 when the engine heartbeat is fresh |
| `travian_engine_heartbeat_age_seconds`, `travian_engine_started_timestamp_seconds` | Heartbeat age and engine start |
| `travian_engine_last_round_end_timestamp_seconds`, `travian_engine_last_round_duration_seconds` | The last automation job run |
| `travian_queue_overdue{queue}` | Jobs over 60 seconds late: `construction`, `research`, `movement`, `trade` |
| `travian_quarantined_tasks` | Quarantined engine tasks |
| `travian_mail_queue{status="queued"}` | Mails waiting to be sent |
| `travian_players_online`, `travian_players_total` | Accounts active in the last 10 minutes (the control panel's definition) and all accounts |
| `travian_last_backup_age_seconds` | Seconds since the last backup |
| `travian_db_slow_queries_total` | Slow queries counted by the database since it started |
| `travian_http_requests_total{class}` | Game requests by status class (`2xx` to `5xx`); one Redis increment per request, reset when Redis restarts |

A scrape needs a fresh nginx configuration, so recreate the web service (`docker compose up -d web`) after updating.

## Versions and upgrades

Releases are git tags (`vX.Y.Z`) and the published image tags (`X.Y.Z`, `X.Y` and `latest`).

- **Patch** (`1.0.x`): fixes only. Upgrade in place.
- **Minor** (`1.x.0`): new features. Upgrade in place.
- **Major** (`x.0.0`), and any release that changes `database/account.sql` or `database/world.sql`: the database schema changed. There are no migrations (schema changes are made only in those two files), so a world from an older schema must be reinstalled; see [install.md](install.md#reinstalling).

To upgrade a published-image deployment:

```sh
# Back up first (see Backups), then:
sed -i 's/^TRAVIAN_VERSION=.*/TRAVIAN_VERSION=1.1.0/' .env
docker compose pull
docker compose up -d --wait
```

Check `docker compose ps` and sign in before deleting the backup you took. To roll back, set the previous version and run the same two commands; if the release changed the schema, restore the backup you took first.

## Reset

To throw a world away and start over, follow [Reinstalling](install.md#reinstalling). `docker compose down -v` deletes the databases and the state volume, so export a backup first.

## Catching up after engine downtime

The engine writes its heartbeat to Redis and, every 15 seconds, to `config.engineBeat` in the world database. When it starts and the last stored beat is more than `TRAVIAN_CATCHUP_AFTER_SECONDS` (default 300) in the past, because it was stopped, crashed, or the world was restored from a backup, it runs a catch-up pass before any worker starts:

- Every overdue construction, demolition, research, merchant trip, troop movement and training row is completed in one global order by due time, so a battle never resolves before a building that was due earlier. The due times are the original ones: nothing is shifted, and beginner protection, vacation and round timers keep running on the real clock.
- The pass runs at most `TRAVIAN_CATCHUP_RATE_PER_SECOND` rows a second (default 50) for at most `TRAVIAN_CATCHUP_MAX_SECONDS` (default 600). Whatever is still overdue then is left to the normal workers, which work through it in their bounded batches.
- Each row goes through the same processor the workers use, inside the same transaction, so a row is never completed twice, even when a web request or another process takes it at the same moment. A row that fails is retried and quarantined like any other (see Engine health).
- Reports, event mail and webhooks for the completed rows are sent as usual, under their normal caps and queues.
- Engine health shows `Running (last beat Ns ago, Catching up (N))` with the number of overdue rows left while the pass runs. The container health check stays healthy, because the pass keeps beating. A maintenance pause or a stop signal ends the pass early.

A world that never ran, or one that was away for less than the threshold, does not run the pass.

## Reset the round

When a round has finished (a World Wonder reached level 100, or the Natars finished it), an administrator starts the next round in the same world, without a reinstall, from **World > Round reset** in the control panel (`admin.php?action=roundReset`). Nothing changes until the administrator picks a mode, types the world title and submits the form.

The reset runs in this order, and the page reports the first step that refuses:

1. It refuses unless the round has finished and its end-of-round bonuses were settled, and while another reset runs.
2. It pauses the engine and takes a backup of the world and account databases. That backup is exempt from backup retention, so the restore point is still there when you look for it.
3. It archives the round into the `rounds` and `round_standings` tables of the world database: the winner, the summary row, every player and alliance ranked by population, and the player and alliance medals. They are plain SQL rows, so a later hall-of-fame page can read them.
4. It holds the world in recovery mode while it empties the round tables (the one list is `RoundReset::WIPED_TABLES`; every table in `world.sql` is classified there and a contract keeps it that way), generates a fresh map with the installer's own seeding, recreates Support, the Natars and Multihunter (Multihunter keeps its password), and opens the world again in the account database.
5. **Keep accounts** gives every player back the same id, name, password, email, tribe and settings (language, time zone, display options, sitters, linked social accounts, bans), with a fresh start village at a random free place. Gold, hero, medals, villages, statistics, profile text, Plus and boosts start over. **Remove accounts** deletes every player account; players register again. Bots are never kept.
6. It tells the engine to restart, which reads the new round's start time.

If a step after the archive fails, the world stays paused in recovery mode and the page says so; restore the backup taken in step 2 (see [Restore](#restore)). The restore returns the world to the moment of that backup, before the archive was written, so run the reset again afterwards. Every reset writes `round.archive` and `round.reset` to the audit log.

Rounds never reset on their own: a finished round stays frozen, with the world readable, until an administrator resets it.

## Change the world speed

`tools/set-world-speed.php` changes an installed world's speed atomically across its connection file and `gameServers` row, then reconciles villages and heroes. Dry-run first:

```sh
docker compose exec -u www-data php php tools/set-world-speed.php --speed=<1..1000000000> --from-speed=<current speed>
docker compose exec -u www-data php php tools/set-world-speed.php --speed=<1..1000000000> --from-speed=<current speed> --apply
```

It refuses a mismatched `--from-speed`. See [world-speed.md](world-speed.md) for what the speed changes.

A faster speed has a shorter round, and the engine ends the round, and deletes every queue, on its first cycle after the round's end has passed. The tool therefore refuses (the dry run too) when the world is already as old as the new speed's round (the Natar Wonder finish of an official speed, otherwise about 350 days divided by the speed; at 100x that is 3.5 days). Add `--reset-start` to move the round start to now in the same change: the world's `config.startTime` and `installationTime` and the account database's `gameServers.startTime`, from which the artefact, plan, Wonder and end-of-round timers are derived, and it clears the flags that say those steps already happened (`ArtifactsReleased`, `WWPlansReleased`, `WWAlertSent`) and the restore delay (`delayTime`), so the new round replays them: the artefacts are released again at the new release time and replace the current ones. Restart the engine afterwards so it reads them. Player accounts, heroes, villages and queues are unchanged; the in-flight timers are rescaled as before.

## Other tools

Scoped migrations for older worlds, the support-ticket and mail-queue schema repairs, and quarantined-task recovery are documented in the [tools guide](../tools/README.md).

## Gold purchases

### Refunds, disputes and flagged accounts

A refund or chargeback arrives as a signed webhook. A refund deducts `ceil(gold x refunded / paid)` in total across all its partial refunds, never more than the order's gold (a dispute takes the whole order); the order becomes `partial`, `refunded` or `disputed`, and the account appears under **Flagged accounts** in `admin.php?action=payments` with the gold removed. Review it, act as your rules require, then press **Clear**. A deduction can leave `bought_gold` negative. Orders, provider events and flags are the payment record: **Orders** and **Provider events** on the same page show what was received and why an event was rejected (unknown order, amount or currency mismatch, order never paid). Unpaid checkouts older than 2.5 days are deleted automatically.

If gold must be restored (for example a refund was mistaken), use the admin gift-gold tools; the system never re-credits an order that was reversed.

### Testing without real money

1. In admin set **Mode** to *Test*, enter sandbox keys, activate a package and enable purchases.
2. Stripe: install the Stripe CLI, run `stripe listen --forward-to http://localhost:8080/payments/stripe/webhook`, and enter the `whsec_...` it prints as the webhook signing secret. Pay with test card `4242 4242 4242 4242`. Test a refund by refunding that test payment in the Stripe dashboard.
3. PayPal: create a sandbox app and a sandbox buyer account, expose the world through a tunnel, register `<tunnel URL>/payments/paypal/webhook` in the sandbox app and enter its webhook ID. The tunnel is the only step that needs a public URL; remove it afterwards.
4. Without any provider account, `docker compose exec -T php php tests/runtime/test-payment-credit-runtime.php` forges signed events and exercises credit, replay, mismatch, concurrent delivery, refund and secret handling.

### Operator responsibilities

Selling gold makes you the merchant. You are responsible for your terms of sale, withdrawal and refund policy, tax and invoicing, age and consumer-protection rules in the countries you sell to, and your payment provider's acceptable-use terms. This project provides software only and no legal text; show your own terms to players before they pay.

