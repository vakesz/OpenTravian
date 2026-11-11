# Install

## Prerequisites

| Requirement | Minimum | Notes |
| --- | --- | --- |
| Docker Engine | 24 | Docker Desktop and OrbStack work; see [troubleshooting.md](troubleshooting.md) for OrbStack |
| Docker Compose plugin | 2.18 | `docker compose version`; older versions fail on nested variable defaults |
| CPU | linux/amd64 or linux/arm64 | Published images are multi-arch (Apple Silicon needs no emulation) |
| Memory | 2 GB free for Docker | MariaDB's buffer pool defaults to 256 MiB. Redis caps its cache at 200 MiB |
| Free ports | 8080 (local) or 80 and 443 (with `compose.tls.yaml`) | `APP_PORT` changes the local port |

## Quick start

```sh
git clone https://github.com/vakesz/OpenTravian.git && cd OpenTravian
sh config/bootstrap-env.sh
COMPOSE_FILE=compose.yaml:compose.build.yaml docker compose up -d --build --wait
docker compose exec -u www-data php php tools/preflight.php
```

To pull the published images instead of building, use plain `docker compose up -d --wait` (see [Deploy](deploy.md#published-images)). The preflight is the first step after `docker compose up`. It checks the PHP version and extensions, the writable state directory, private state files, free disk space, and, once a world is installed, the database, the core tables, Redis, the engine heartbeat, SMTP and the world speed. It prints `OK`, `INFO`, `WARN` or `FAIL` per check and exits 1 on any `FAIL`; before the setup wizard has run it checks only the first group and says so. The control panel's **Engine health** page shows the same checks next to the engine heartbeat and the work queues.

`bootstrap-env.sh` writes `.env` with freshly generated secrets (`openssl rand -hex 32`) and your user and group ids. It never overwrites a value that is already set, so it is safe to run again. It prints the setup token; then open [http://localhost:8080/setup/](http://localhost:8080/setup/) and finish the wizard. To skip the wizard, set `TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD` in `.env` before `docker compose up` ([headless install](#headless-install)).

## Setup wizard

The wizard has two modes and a review step before it installs.

- **Quick install** asks for the database login, the world name, a speed preset (1x, 2x, 3x, 5x or 10x), how many Natar farm villages to seed (none, 40, 150 or 400), the Multihunter password and an optional starter player. Everything else uses the defaults. The default playable tribes are Romans, Teutons, Gauls, Egyptians, Huns and Spartans. Vikings start off.
- **Advanced install** also takes any farm village count from 0 to 1000, and exposes the world ID and URLs, the two database names, a free numeric speed from 1x through 1,000,000,000x, starting gold, the map size (100, 200 or 400), every playable tribe, overrides for each speed-profile value (see [world-speed.md](world-speed.md)) and a set of game rules (vacation days, starting adventures, refer-a-friend limit, alliance size, automatic registration close, vouchers, Season Pass, World Wonder plans, Palace/Residence Finish now with gold on speed worlds above 1x, automatic raids, one farm list per account, system auctions, starvation). Rule overrides are applied by `game/include/config/config.after.php`; only the whitelist in `game/include/Game/WorldOverrides.php` is accepted.

The form defaults match Compose: database host `db`, admin user `root` (password: `MARIADB_ROOT_PASSWORD` from `.env`), account database `travian_main`, world database `travian_world`, and URLs from `TRAVIAN_BASE_URL`.

If either selected database already contains tables, the wizard stops and asks whether to clear exactly those two databases. Choose **Yes** only when replacing a world you can lose; **No** returns to the form without changing data.

When it finishes, open the game URL and sign in with the starter player. The staff account is `Multihunter`: sign in through the normal login with the Multihunter password, then open `/admin.php`. The starter player is intentionally not an administrator.

## Email is optional

Support tickets, email verification, password reset and the other mail features need an SMTP server. You can install without one and add it later.

- **Without SMTP** (leave the SMTP host empty): the world installs normally. Mail is queued in `mailServer` and nothing is delivered. The Support form shows "Support delivery is not configured for this world." and refuses tickets instead of pretending they were sent.
- **With SMTP**: enter a TLS SMTP host, port, encryption (STARTTLS or SMTPS), a sender address and, if the server needs it, credentials, plus the support recipient. The password lives only in the protected `smtp.local.php` state file, never in SQL or the repository.

Multihunter can set or change both the recipient and the SMTP settings in the admin panel at any time; queued mail is delivered once SMTP is configured. A "queued" state in the UI always means queued, never delivered.

### Natar farm villages

A farm village is a Natar village (the Natars own it and it is named `Farm 001`, `Farm 002` and so on; it is flagged `isFarm`) that players raid for resources or conquer. The installer seeds them after the map exists: spread evenly over the four map quarters and over three distance bands (40, 35 and 25 percent from the inside out), never nearer to the centre than 30 fields, each of 50 to 300 inhabitants at low levels, with a small Natar garrison of Pikemen and Guardsmen (about one defender per eight inhabitants, at least five). They keep producing, up to their warehouse, are listed under Statistics, Farm villages, count for no player's rank, are skipped by the Natar AI (it never trains or attacks from a farm), and a conquered farm becomes an ordinary village. A round reset does not seed them again. **World > Seed farm villages** in the control panel (`admin.php?action=seedFarmVillages`) adds more at any time.

## Headless install

The same installer that backs the wizard runs from the command line (`tools/install.php`). Put the settings in `.env` as `TRAVIAN_INSTALL_*`. `docker compose up -d --wait` then installs the world before the web service starts; to run it on demand instead:

```sh
docker compose run --rm installer
```

Only `TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD` is required (at least 12 characters). Speed, starter player, tribes, map size, starting gold, farm villages and the SMTP block are optional; each `TRAVIAN_INSTALL_*` variable is listed in the [environment reference](configuration.md#environment-reference). Leave `TRAVIAN_INSTALL_SMTP_HOST` unset to install without email.

### World rules and speed profile

Every option of the wizard's Advanced install has a variable, so a headless install can set the same things. Leave a variable unset or empty to keep the built-in default. Rules take `1` or `0` for switches, a number within the range shown, or (Discord) an `https://` URL. Profile values use the wizard's units. An out-of-range profile value stops the install with a message naming the field; an invalid rule value is ignored, as in the wizard, and the built-in default stays.

| Variable | Setting | Accepts |
| --- | --- | --- |
| `TRAVIAN_INSTALL_RULE_VACATION_DAYS` | Vacation base days (divided by world speed; 0 disables) | 0 to 365 |
| `TRAVIAN_INSTALL_RULE_START_ADVENTURES` | Adventures available at the start | 1 to 20 |
| `TRAVIAN_INSTALL_RULE_REF_LIMIT` | Refer-a-friend reward limit | 0 to 1000 |
| `TRAVIAN_INSTALL_RULE_ALLIANCE_MEMBERS_LIMIT` | Alliance member limit | 1 to 500 |
| `TRAVIAN_INSTALL_RULE_REGISTER_AUTO_CLOSE` | Close registration automatically | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_VOUCHER_ENABLED` | Gold vouchers enabled | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_DISABLE_SEASONS` | Hide the Season Pass | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_WW_PLANS_ENABLED` | World Wonder construction plans | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_FINISH_CONSTRUCTION` | Finish Palace and Residence construction with gold (speed worlds above 1×) | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_ONE_FARM_LIST_PER_ACCOUNT` | One farm list per account | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_FAKE_AUCTIONS` | System-generated auctions | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_STARVATION` | Troops starve without crop | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_SPEED_SHORTCUTS` | Speed-world shortcuts (suggested from x100): upgrade to max level, train maximum, Smithy gold max | `1` or `0` |
| `TRAVIAN_INSTALL_RULE_UPGRADE_TO_MAX_LEVEL_GOLD` | Building: gold to upgrade instantly to max level | 1 to 10000 |
| `TRAVIAN_INSTALL_RULE_SMITHY_MAX_LEVEL_GOLD` | Smithy: gold to upgrade one unit to max level | 1 to 10000 |
| `TRAVIAN_INSTALL_RULE_SMITHY_UPGRADE_ALL_TO_MAX_GOLD` | Smithy: gold to upgrade all units to max level | 1 to 10000 |
| `TRAVIAN_INSTALL_RULE_DISCORD_URL` | Discord invite URL (https; blank hides every Discord link) | https URL or empty |
| `TRAVIAN_INSTALL_PROFILE_MOVEMENT_SPEED_INCREASE` | Troop and hero map movement (x) | 1 to 100000 |
| `TRAVIAN_INSTALL_PROFILE_STORAGE_MULTIPLIER` | Warehouse and granary capacity (x) | 1 to 1000000 |
| `TRAVIAN_INSTALL_PROFILE_CRANNY_MULTIPLIER` | Cranny capacity (x) | 1 to 1000000 |
| `TRAVIAN_INSTALL_PROFILE_TRAP_MULTIPLIER` | Trapper capacity (x) | 1 to 1000000 |
| `TRAVIAN_INSTALL_PROFILE_CAGE_MULTIPLIER` | Cage capacity (x) | 1 to 1000000 |
| `TRAVIAN_INSTALL_PROFILE_PROTECTION_TIME` | Beginner protection (hours) | 1 to 8760 |
| `TRAVIAN_INSTALL_PROFILE_OASIS_INITIAL_PROTECTION_TIME` | Oasis animal protection at world start (hours) | 1 to 8760 |
| `TRAVIAN_INSTALL_PROFILE_DAILY_QUEST_INTERVAL` | Daily quest cycle (hours) | 1 to 168 |
| `TRAVIAN_INSTALL_PROFILE_HERO_REGENERATION_MULTIPLIER` | Hero regeneration (x) | 1 to 1000000 |
| `TRAVIAN_INSTALL_PROFILE_OASIS_ANIMAL_CAP` | Oasis animal cap (animals) | 1 to 100000 |
| `TRAVIAN_INSTALL_PROFILE_OASIS_RESPAWN_INTERVAL` | Oasis respawn interval (minutes) | 1 to 43200 |
| `TRAVIAN_INSTALL_PROFILE_ROUND_LENGTH` | Round length (World Wonder finish) (days) | 1 to 3650 |

The administrative database password comes from `MARIADB_ROOT_PASSWORD`; world and database names, the base URL and the support recipient come from the usual `TRAVIAN_*` variables ([configuration.md](configuration.md)). The installer exits 0 when a world is already installed, so it is safe to run on every deploy. It refuses to touch non-empty databases unless you pass `--reset` (`docker compose run --rm installer install --reset`). Only the `installer` service receives the database administrator password; `php` and `engine` never do.

## Reinstalling

Schema changes are made only in `database/account.sql` and `database/world.sql`, with no migration path, so a world installed from an older schema needs a reinstall. This is the single reinstall procedure; other pages link here.

1. Take a backup if the world matters ([operate.md](operate.md#backups)).
2. `docker compose down -v` permanently deletes both `mariadb_data` and `travian_state`, including the in-volume backups. Keep an off-volume copy first.
3. `docker compose up -d --wait`, then run the wizard or `docker compose run --rm installer` again.

To replace only the world and keep the rest of the stack, rerun the wizard or `docker compose run --rm installer install --reset`; it clears exactly the two configured databases after confirmation.
