# Configuration

## Environment reference

Set these in `.env` (Compose reads it automatically); `config/bootstrap-env.sh` fills the Required block and the user ids. Every variable in `.env.example` has a row here, enforced by `tests/contracts/test-env-docs-contract.php`.

| Variable | Block | Default | Purpose |
| --- | --- | --- | --- |
| `MARIADB_ROOT_PASSWORD` | Required | generated | Database administrator password. Only the `installer` service receives it, to create the game's own database user |
| `REDIS_PASSWORD` | Required | generated | Password of the `redis` service's default user. `redis`, `php`, `engine` and `installer` receive it (as `REDIS_PASSWORD` or `TRAVIAN_REDIS_PASSWORD`); Redis stores only its hash and denies dangerous and administrative commands such as `CONFIG` and `FLUSHALL`. Existing installs: run `sh config/bootstrap-env.sh` once ([operate.md](operate.md#rotating-the-redis-password)) |
| `TRAVIAN_SETUP_TOKEN` | Required | generated | One-time token for the setup wizard, at least 24 characters |
| `COMPOSE_FILE` | Common | unset | Compose layers: `compose.yaml:compose.dev.yaml` for development ([development.md](development.md)), `compose.build.yaml` to build locally, `compose.tls.yaml` for HTTPS ([deploy.md](deploy.md)) |
| `TRAVIAN_VERSION` | Common | `latest` | Published image version to run ([deploy.md](deploy.md#published-images)) |
| `APP_PORT` | Common | `8080` | Host port for the local HTTP listener |
| `APP_BIND_ADDRESS` | Common | `127.0.0.1` | Host address the listener binds to; keep it on loopback behind a reverse proxy |
| `TRAVIAN_BASE_URL` | Common | `http://localhost:<APP_PORT>/` | Public URL with trailing slash, stored in the world at install and used for mail links. Derived from `TRAVIAN_DOMAIN` with `compose.tls.yaml` |
| `TRAVIAN_DOMAIN` | Common | unset | Public domain for `compose.tls.yaml` ([deploy.md](deploy.md#https-with-caddy)) |
| `TRAVIAN_TLS_INTERNAL` | Common | unset | `1` makes Caddy use its own CA instead of Let's Encrypt, for local tests such as `travian.localhost` |
| `TRAVIAN_SUPPORT_EMAIL` | Common | unset | Support recipient prefill; optional ([install.md](install.md#email-is-optional)) |
| `TRAVIAN_BACKUP_ENABLED` | Common | `1` | `0` stops the engine's daily backups; manual backups still work |
| `TRAVIAN_BACKUP_KEEP_LAST` | Common | `3` | Newest backups to keep |
| `TRAVIAN_BACKUP_KEEP_DAILY` | Common | `7` | Days to keep the newest backup of each |
| `TRAVIAN_BACKUP_EXPORT_DIR` | Common | unset | Container path, bind-mounted from the host, that receives a copy of every backup ([operate.md](operate.md#backups)) |
| `TRAVIAN_CATCHUP_AFTER_SECONDS` | Advanced | `300` | How long the engine must have been away before the catch-up pass runs on its next start ([operate.md](operate.md#catching-up-after-engine-downtime)) |
| `TRAVIAN_CATCHUP_RATE_PER_SECOND` | Advanced | `50` | Overdue rows the catch-up pass completes per second |
| `TRAVIAN_CATCHUP_MAX_SECONDS` | Advanced | `600` | Longest the catch-up pass runs; whatever is still overdue then is left to the workers |
| `TRAVIAN_BACKUP_STATE_PASSPHRASE` | Common | unset | Opt-in: every backup also carries an encrypted archive of the generated state files, sealed with this passphrase; unset leaves the archive off ([operate.md](operate.md#encrypted-state-archive)) |
| `TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD` | Common | unset | Headless install: staff account password. Setting it makes `docker compose up` install the world ([install.md](install.md#headless-install)) |
| `TRAVIAN_INSTALL_SPEED` | Common | `1` | Headless install: world speed, 1 to 1000000000 |
| `TRAVIAN_INSTALL_PLAYER_NAME` | Common | unset | Headless install: starter player name (creates the player) |
| `TRAVIAN_INSTALL_PLAYER_PASSWORD` | Common | unset | Headless install: starter player password |
| `TRAVIAN_INSTALL_PLAYER_TRIBE` | Common | `1` | Headless install: starter player tribe id |
| `TRAVIAN_INSTALL_TRIBES` | Common | `1,2,3,6,7,8` | Headless install: playable tribe ids |
| `TRAVIAN_INSTALL_MAP_SIZE` | Common | `100` | Headless install: map size (100, 200 or 400) |
| `TRAVIAN_INSTALL_STARTING_GOLD` | Common | `0` | Headless install: starting gold |
| `TRAVIAN_INSTALL_FARM_VILLAGES` | Common | `0` | Headless install: Natar farm villages to seed, 0 to 1000 (the wizard offers 0, 40, 150 and 400) |
| `TRAVIAN_INSTALL_SMTP_HOST` | Common | unset | Headless install: SMTP host; leave unset to install without email |
| `TRAVIAN_INSTALL_SMTP_PORT` | Common | `587` | Headless install: SMTP port |
| `TRAVIAN_INSTALL_SMTP_ENCRYPTION` | Common | `starttls` | Headless install: `starttls` or `smtps` |
| `TRAVIAN_INSTALL_SMTP_FROM` | Common | support email | Headless install: SMTP sender address |
| `TRAVIAN_INSTALL_SMTP_AUTH` | Common | `1` with a username | Headless install: `1` if the server requires authentication |
| `TRAVIAN_INSTALL_SMTP_USERNAME` | Common | unset | Headless install: SMTP username |
| `TRAVIAN_INSTALL_SMTP_PASSWORD` | Common | unset | Headless install: SMTP password (stored only in the protected `smtp.local.php`) |
| `TRAVIAN_INSTALL_RULE_VACATION_DAYS` | Advanced | unset | Headless install: Vacation base days (divided by world speed; 0 disables); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_START_ADVENTURES` | Advanced | unset | Headless install: Adventures available at the start; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_REF_LIMIT` | Advanced | unset | Headless install: Refer-a-friend reward limit; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_ALLIANCE_MEMBERS_LIMIT` | Advanced | unset | Headless install: Alliance member limit; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_REGISTER_AUTO_CLOSE` | Advanced | unset | Headless install: Close registration automatically; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_VOUCHER_ENABLED` | Advanced | unset | Headless install: Gold vouchers enabled; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_DISABLE_SEASONS` | Advanced | unset | Headless install: Hide the Season Pass; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_WW_PLANS_ENABLED` | Advanced | unset | Headless install: World Wonder construction plans; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_FINISH_CONSTRUCTION` | Advanced | unset | Headless install: Finish Palace and Residence construction with gold (speed worlds above 1×); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_ONE_FARM_LIST_PER_ACCOUNT` | Advanced | unset | Headless install: One farm list per account; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_FAKE_AUCTIONS` | Advanced | unset | Headless install: System-generated auctions; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_STARVATION` | Advanced | unset | Headless install: Troops starve without crop; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_SPEED_SHORTCUTS` | Advanced | unset | Headless install: Speed-world shortcuts (suggested from x100): upgrade to max level, train maximum, Smithy gold max; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_UPGRADE_TO_MAX_LEVEL_GOLD` | Advanced | unset | Headless install: Building: gold to upgrade instantly to max level; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_SMITHY_MAX_LEVEL_GOLD` | Advanced | unset | Headless install: Smithy: gold to upgrade one unit to max level; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_SMITHY_UPGRADE_ALL_TO_MAX_GOLD` | Advanced | unset | Headless install: Smithy: gold to upgrade all units to max level; accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_RULE_DISCORD_URL` | Advanced | unset | Headless install: Discord invite URL (https; blank hides every Discord link); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_MOVEMENT_SPEED_INCREASE` | Advanced | unset | Headless install: Troop and hero map movement (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_STORAGE_MULTIPLIER` | Advanced | unset | Headless install: Warehouse and granary capacity (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_CRANNY_MULTIPLIER` | Advanced | unset | Headless install: Cranny capacity (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_TRAP_MULTIPLIER` | Advanced | unset | Headless install: Trapper capacity (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_CAGE_MULTIPLIER` | Advanced | unset | Headless install: Cage capacity (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_PROTECTION_TIME` | Advanced | unset | Headless install: Beginner protection (hours); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_OASIS_INITIAL_PROTECTION_TIME` | Advanced | unset | Headless install: Oasis animal protection at world start (hours); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_DAILY_QUEST_INTERVAL` | Advanced | unset | Headless install: Daily quest cycle (hours); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_HERO_REGENERATION_MULTIPLIER` | Advanced | unset | Headless install: Hero regeneration (x); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_OASIS_ANIMAL_CAP` | Advanced | unset | Headless install: Oasis animal cap (animals); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_OASIS_RESPAWN_INTERVAL` | Advanced | unset | Headless install: Oasis respawn interval (minutes); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `TRAVIAN_INSTALL_PROFILE_ROUND_LENGTH` | Advanced | unset | Headless install: Round length (World Wonder finish) (days); accepted values in [install.md](install.md#world-rules-and-speed-profile) |
| `APP_UID` | Advanced | generated | Host user id for locally built images ([troubleshooting.md](troubleshooting.md)) |
| `APP_GID` | Advanced | generated | Host group id for locally built images |
| `TRAVIAN_TRUSTED_PROXY` | Advanced | `127.0.0.1` | Reverse proxy allowed to set `X-Forwarded-For` (address or CIDR). Set for Caddy by `compose.tls.yaml` |
| `TRAVIAN_EXTRA_HOSTS` | Advanced | unset | Extra host names nginx answers to (space-separated, wildcards allowed) |
| `TRAVIAN_CORS_ORIGIN` | Advanced | `http://localhost:<APP_PORT>` | Origin allowed to call `/api` from another origin |
| `TRAVIAN_ADMIN_TWO_FACTOR` | Advanced | unset | Emergency break-glass: `off` or `optional` lowers the stored administrator two-factor mode, never raises it ([below](#administrator-two-factor-sign-in-and-audit-log)) |
| `TRAVIAN_APP_DB_USER` | Advanced | `travian` | Name of the database user the installer creates for the game |
| `TRAVIAN_APP_IMAGE` | Advanced | `ghcr.io/vakesz/opentravian-app:<TRAVIAN_VERSION>` | Full app image reference, overriding the published one |
| `TRAVIAN_WEB_IMAGE` | Advanced | `ghcr.io/vakesz/opentravian-web:<TRAVIAN_VERSION>` | Full web image reference, overriding the published one |
| `TRAVIAN_MARIADB_NATIVE_AIO` | Advanced | `1` | Set `0` where io_uring is denied (OrbStack) |
| `TRAVIAN_MARIADB_BUFFER_POOL_SIZE` | Advanced | `256M` | InnoDB buffer pool; do not exceed the memory Docker has, and keep `TRAVIAN_DB_MEM_LIMIT` above it |
| `TRAVIAN_MARIADB_FLUSH_NEIGHBORS` | Advanced | `0` | InnoDB neighbor-page flushing. Leave `0` on SSD and virtual disks |
| `TRAVIAN_MARIADB_IO_CAPACITY` | Advanced | `1000` | InnoDB background flush rate. The server default maximum is 2000 and must stay at least this large |
| `TRAVIAN_MARIADB_SLOW_LOG` | Advanced | `0` | `1` writes slow queries to `/var/lib/mysql/slow.log` in the db container |
| `TRAVIAN_MARIADB_SLOW_LOG_SECONDS` | Advanced | `1` | Threshold for the slow-query log |
| `TRAVIAN_MARIADB_SLOW_LOG_MAX_MB` | Advanced | `50` | Size at which the db health check rotates `slow.log` to `slow.log.1` ([Slow-query log](#slow-query-log)) |
| `TRAVIAN_MARIADB_SLOW_LOG_KEEP` | Advanced | `3` | Rotated slow-query log files to keep |
| `TRAVIAN_FPM_MAX_CHILDREN` | Advanced | `8` | PHP-FPM game-worker count. Each is capped at 256 MB and 60 seconds. `admin.php` and `setup.php` use a separate one-worker pool capped at 1536 MB and one hour |
| `TRAVIAN_FPM_START_SERVERS` | Advanced | `3` | PHP-FPM workers at start |
| `TRAVIAN_FPM_MIN_SPARE_SERVERS` | Advanced | `2` | Minimum idle workers |
| `TRAVIAN_FPM_MAX_SPARE_SERVERS` | Advanced | `5` | Maximum idle workers (within max children) |
| `TRAVIAN_FPM_MAX_REQUESTS` | Advanced | `500` | Requests before a worker is recycled |
| `TRAVIAN_DB_MEM_LIMIT` | Advanced | `1g` | Memory limit of the `db` service |
| `TRAVIAN_REDIS_MEM_LIMIT` | Advanced | `256m` | Memory limit of the `redis` service. Redis itself stops at 200 MB (`docker/redis/redis.conf`); keep this limit above that |
| `TRAVIAN_PHP_MEM_LIMIT` | Advanced | `2g` | Memory limit of the `php` and `installer` services |
| `TRAVIAN_ENGINE_MEM_LIMIT` | Advanced | `1g` | Memory limit of the `engine` service |
| `TRAVIAN_WEB_MEM_LIMIT` | Advanced | `256m` | Memory limit of the `web` service |
| `TRAVIAN_CADDY_MEM_LIMIT` | Advanced | `128m` | Memory limit of the `caddy` service |
| `TRAVIAN_PIDS_LIMIT` | Advanced | `512` | Process cap applied to every service |
| `TRAVIAN_SUBNET` | Advanced | `172.29.88.0/24` | Private subnet pinned by `compose.tls.yaml`; change it if it collides with a network you use |
| `TRAVIAN_CADDY_IP` | Advanced | `172.29.88.10` | Caddy's address in that subnet (the trusted proxy) |
| `TRAVIAN_ALLOW_ADMIN_GOLD_GRANTS` | Advanced | `0` | Test worlds only: lets Multihunter grant gift gold |

Generated config, the setup lock, logs and backups live in the `travian_state` volume mounted at `/var/lib/travian` (`TRAVIAN_STATE_DIR`); there is nothing to set for them.

## Generated files

Generated state lives in the `travian_state` named volume, mounted at `/var/lib/travian` (`TRAVIAN_STATE_DIR`) in the `php` and `engine` services: `globalConfig.php`, `connection.local.php`, `smtp.local.php`, `social.local.php`, `setup.lock`, `error_log.log`, `automation.log`, `backups/`. `smtp.local.php` is mode `0600` and contains deployment-only SMTP credentials; `social.local.php` has the same mode and holds social sign-in secrets (see [Social sign-in](#social-sign-in-google-facebook-apple)). `payments.local.php` is also `0600` and holds payment provider credentials (see [Gold purchases](#gold-purchases-stripe-and-paypal)). `captcha.local.php` is `0600` too and holds the optional activation CAPTCHA keys (see [Activation CAPTCHA](#activation-captcha)). `webhook.local.php` is `0600` as well and holds the Discord world-event webhook URL and settings (see [Discord world-event webhook](#discord-world-event-webhook)). `admin-2fa.local.json` is `0600` as well and holds the administrator two-factor mode, TOTP secrets and hashed recovery codes (see [Administrator two-factor sign-in](#administrator-two-factor-sign-in-and-audit-log)). Application errors go to `error_log.log` and to the container logs (`docker compose logs php engine`). Without `TRAVIAN_STATE_DIR` (running outside Docker) the old in-tree locations under `game/deployment/include/` and `globalConfig.php` are used; they remain ignored by Git. Compose stores MariaDB data in the `mariadb_data` named volume. `.env` contains local secrets and is also ignored.

## Client IP addresses and reverse proxies

nginx answers only the host names in `TRAVIAN_ALLOWED_HOSTS` and closes the connection (444) for any other `Host` header, so a forged Host never reaches PHP or reaches a link in mail. The list is derived at container start from `TRAVIAN_BASE_URL` plus `localhost`, `127.0.0.1`, `[::1]` and `web`; add more with a space-separated `TRAVIAN_EXTRA_HOSTS` (wildcards like `*.example.com` allowed). Verification and activation links are built from the configured world URL (`gameWorldUrl`, set from `TRAVIAN_BASE_URL` at install), never from the request.

The game uses the connection's address (`REMOTE_ADDR`) for IP bans and login throttling and ignores `X-Forwarded-For` unless nginx is told to trust the sender. With plain Docker port publishing every visitor appears to come from the Docker bridge gateway, so all players share one address until a trusted reverse proxy sets `X-Forwarded-For`. When you run one, set `TRAVIAN_TRUSTED_PROXY` in `.env` to that proxy's address or CIDR (as seen from the `web` container, for example `172.16.0.0/12` or the proxy's container address). nginx then takes the client address from `X-Forwarded-For` for connections from that proxy only. Make sure the proxy overwrites, rather than appends to, any client-supplied `X-Forwarded-For`.

IP bans and the per-player address log store IPv4 and IPv6 addresses as text; an IPv4-mapped IPv6 address is treated as IPv4. Publish only an IPv4 address for the game, or ban at the reverse proxy, until the table is extended.

Session cookies are marked `Secure` for HTTPS requests and for the configured HTTPS public host when TLS terminates before the stack. A separate plain-HTTP localhost origin intentionally receives a non-Secure cookie so it remains usable alongside an HTTPS tailnet or reverse-proxy URL.

PHP sessions are stored in Redis for the same 30 days, one key per session under the prefix `travian_session:`. The entrypoint writes the password into a root-only file on the PHP container's tmpfs; it is not in the image. A player's requests stay ordered by a Redis lock. Redis does not snapshot, so a Redis restart drops live sessions; the remember-me cookie signs the player back in on the next request. Keys that already expire are evicted first if the cache reaches 200 MB. The engine pause flag has no expiry and is not evicted that way.

## Request limits, headers and logging

nginx limits POSTs to login, registration, OAuth and the support form to 20 per minute per client (burst 10, then 429), caps request bodies at 1 MB (nothing uploads files), cuts PHP requests off after 60 seconds (`admin.php` and the installer get an hour), and sends `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, a minimal `Permissions-Policy` and a Content-Security-Policy that allows only this origin (plus inline scripts and styles for now, and the Google, Facebook and Apple origins as OAuth form targets). PHP-FPM enforces the same split: game workers are 256 MB and 60 seconds, and `admin.php` and `setup.php` go to one on-demand worker at 1536 MB and one hour. A request that passes either cap stops with a PHP error instead of the container being killed. OPcache does not stat PHP files in the published image; the dev layer turns those checks back on because the checkout is bind-mounted. Files rewritten at runtime (`connection.local.php`, locale catalogues) still call `opcache_invalidate`. Container logs rotate (5 files of 10 MB per service) and `error_log.log` rotates at 10 MB (one `.1` generation). The MariaDB slow-query log is off by default ([Slow-query log](#slow-query-log)). The application runs as a dedicated `travian` database user created by the setup wizard (`root` is used only during setup; see [Application database user](#application-database-user)).

## Slow-query log

Set `TRAVIAN_MARIADB_SLOW_LOG=1` in `.env` and recreate the db container (`docker compose up -d db`) to log every query slower than `TRAVIAN_MARIADB_SLOW_LOG_SECONDS` (default 1) to `/var/lib/mysql/slow.log` inside it. Read it there:

```sh
docker compose exec db tail -n 50 /var/lib/mysql/slow.log
```

To switch it on or off in a running server without a restart (the change lasts until the container restarts, and needs the database administrator, which only the db container holds):

```sh
docker compose exec db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -e "SET GLOBAL slow_query_log=ON"'
```

The log is rotated by the db container's health check: once `slow.log` is larger than `TRAVIAN_MARIADB_SLOW_LOG_MAX_MB` (default 50) it becomes `slow.log.1`, older files move up, `TRAVIAN_MARIADB_SLOW_LOG_KEEP` (default 3) are kept, and the server opens a fresh file. The Engine health page and `tools/preflight.php` show whether the log is on, its threshold and how many slow queries the database has counted since it started.

**The slow log holds raw query text**, including whatever the game put in it: player and village names, e-mail addresses, message and search terms. It lives only in the database volume, written by MariaDB and readable only by the database user and root (mode 0660, owner `mysql`); it is never exported, backed up or shown in the control panel. Treat it like the database itself: do not paste it into a public issue without removing those values, and switch the log off again when you are done profiling.

## Application database user

The game connects as one dedicated user (`travian`, or `TRAVIAN_APP_DB_USER`), never as `root`. The installer creates it from one list (`Game\Installer\AppDatabaseGrants`): SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, LOCK TABLES, SHOW VIEW and TRIGGER, on the account and world databases only. It has no global privilege, no GRANT OPTION and no FILE, PROCESS, RELOAD or SUPER, and the Engine health page and `tools/preflight.php` read the live grants back and warn about anything else.

It deliberately holds the schema privileges (CREATE, ALTER, DROP, INDEX, TRIGGER) as well as the data ones. The same account runs the world schema patches on an upgrade, restores a backup (which drops and recreates every table), and the cleanups that recreate tables; LOCK TABLES and SHOW VIEW are there because the dumps it takes and restores contain them. Splitting a data-only runtime user from a maintenance login would need a second credential in the state volume and changes to the installer, the restore and the update paths, and the privileges would still sit on the same two databases, so one user was kept; the password can be changed at any time ([Rotating the database password](operate.md#rotating-the-database-password)).

The account host is `%`, on purpose. The database port is not published (only the Compose network reaches it) and the containers' addresses change from start to start, so a narrower host would break restarts without closing a path. Do not publish the `db` port; if you must, set a host pattern for the user yourself.

## Public homepage, registration and help

`/homepage` is the public landing page, with `/homepage/news`, dated news articles, `/homepage/calendar` (the public calendar, filled with this world's data) and `/homepage/tournament` (a permanent redirect to the dated Tournament article). Its language selector only changes the public view: it never alters a player's game language or session, and every local public link keeps the chosen locale. The page text comes from a bundled catalog, so no request leaves the server. The snapshot is edited by hand; record the uncompressed-JSON SHA-256 of a changed snapshot in `assets/gpack/travian/mainPage/ASSET_PROVENANCE.md`.

The two news articles follow the chosen public language: `game/include/resources/HomepagePublicArticleLocales.php` holds the per-language overrides (currently only German, for the Winter Time article; every other language shows the English article). It is edited by hand; record the hash of a changed file in the same provenance file.

`/homepage/world` is a world stats page reached by direct URL only (no footer or menu link). It shows aggregates of public data: world age and speed, player and alliance counts, the top ten alliances (public name and tag), the top ten players as anonymous rank and population rows, and the World Wonder status. It never exposes a player name, id, e-mail, coordinate or village, and hidden, banned and system accounts are excluded. `Game\WorldStatsSnapshot` computes it at most once every five minutes and keeps it in Redis (`Statistics:PublicWorld`); there is no setting to turn it off.

Registration is a `#registration` overlay on the landing page; `/register` (including `?uc=` invite links) redirects to `/homepage#registration`. The overlay has two steps: an email step with an optional newsletter opt-in (stored in `activation.newsletter` and carried into `users.newsletter`, with no terms checkbox), then an account-name and password step before `/activate.php` asks for tribe and start position.

Help is contextual: the `?` button at the top right of every in-game page opens the in-game manual (the building's entry in a built building's view, the overview elsewhere). There is no generic Knowledge Base.

## Languages

The game offers 37 locales (see `assets/i18n/PROVENANCE.md`) plus the existing Persian catalogue. `game/include/resources/Translation/locales.php` lists the installed locales (label, text direction, country), and `config.php` builds `availableLanguages` from it, keyed by the canonical locale code (`en-US`, `de-DE`, ...). The Options language list shows those labels. RTL locales (`ar-AE`, `he-IL`, `fa-IR`) use the `img_rtl` / `css_rtl` Gpack trees.

A locale resolves as `en-US`, then its hand catalogue (`ar-AE`, `el-GR`, `fa-IR` only), then `Translation/generated/<locale>.php`, so a string the generated overlay does not supply renders in English and only a key missing from `en-US` itself is logged. The overlays and the manifest are maintained by hand.

A language is read from the signed-in player's stored preference, then the `travian-lang` cookie, then `en-US`; only the Options form (CSRF-checked POST) changes it, and a GET never does. Session languages are stored as canonical locale codes, which replaces the old short codes (`en`, `ae`, `gr`, `ir`); there is no migration, so a world installed before this change must be reinstalled (see [Reinstalling](install.md#reinstalling)).

## Social sign-in (Google, Facebook, Apple)

The `#registration` overlay offers a provider button only when an operator has fully configured that provider. Multihunter sets them under **Social sign-in** in `admin.php?action=configurationDetails`. Client secrets and the Apple private key (`.p8`) are stored only in the protected generated `social.local.php` state file (mode `0600`, next to `smtp.local.php`; override with `TRAVIAN_SOCIAL_CONFIG`), never in SQL, `globalConfig.php`, logs, forms or the repository. Changing a client, team or key id requires re-entering its secret. The admin page shows the redirect URI to register with each provider:

- `<TRAVIAN_BASE_URL>/oauth/google/callback`
- `<TRAVIAN_BASE_URL>/oauth/facebook/callback`
- `<TRAVIAN_BASE_URL>/oauth/apple/callback`

Sign in with Apple only accepts HTTPS redirect URIs and sets a `SameSite=None; Secure` cookie, so Apple is offered only when `TRAVIAN_BASE_URL` is `https://`. Google and Facebook also work on `http://localhost:8080`. The buttons are icon-only; their accessible names are the providers' official button texts, stored as canonical `Login` keys: "Sign in with Google" (Google Identity branding guidelines), "Continue with Facebook" (Meta's standard Facebook Login button text) and "Sign in with Apple" (Apple's `appleid.auth.js` button). A provider identity is never linked to an existing account by email: a social sign-in whose email is already registered is refused. A new identity chooses an account name in the registration overlay and then continues through the normal tribe and start-position steps; such accounts have no password (use "Forgot your password?" to set one).

The `users.name` and `alidata.tag` UNIQUE keys (case-insensitive under the table collation; alliance names may repeat) have no migration script; worlds installed before them must be reinstalled too. The `activation.newsletter`, `users.newsletter` and `account_oauth_identity` schema additions have no migration script. Worlds installed before them must be reinstalled (see [Reinstalling](install.md#reinstalling) for the safe reset).

## Email: newsletter, notifications and unsubscribe

All game email goes through the `mailServer` queue and is delivered only when SMTP is configured; without it mail stays queued and the pages that queue mail (the admin newsletter page, Options) say that delivery is not configured instead of reporting it as sent.

- **Newsletter.** Multihunter's **Send email** page composes, previews and queues one personalised mail per player who has the newsletter on (`users.newsletter`, changed in Options) and a verified address. One campaign per hour and three per day; each queued mail is rate limited per player. The old account-database `newsletter` table, the companion API's signup into it and the **Import emails** and **Unsubscribe** admin pages are gone.
- **Unsubscribe.** Every mail has the opt-in footer with a signed link (`/unsubscribe/<token>`) that switches off the mail's own kind, or all email, and the queue row's `unsubscribeUrl` becomes the `List-Unsubscribe` and one-click headers. The links are signed with `mail-signing.key` in the state directory (mode `0600`, created on first use); deleting the file invalidates links already sent.

## Gold purchases (Stripe and PayPal)

Players can buy gold through hosted Stripe Checkout and PayPal checkout. The shop is off until an operator configures it, and one operator-chosen currency applies to every package.

Multihunter configures it under **Gold > Payments** in `admin.php?action=payments`:

- **Mode**: *Test* (sandbox keys, no real money) or *Live*. A Stripe `sk_test_`/`rk_test_` key is refused in live mode and the reverse.
- **Currency**: one of USD, EUR, GBP, CAD, AUD, NZD, CHF, SEK, NOK, DKK, PLN, CZK. Package prices are entered in this currency's major units (19.99).
- **Provider credentials**: Stripe secret key and webhook signing secret; PayPal client ID, client secret and webhook ID. Secrets are write-only: the page only shows whether they are set, and a blank field keeps the stored value. Changing the PayPal client ID requires re-entering its secret.
- **Packages**: name, gold, price, image name, offer bonus, best seller / best value badges and an *Active* flag. The seed ships six inactive packages.
- **Purchases enabled**: the master switch. Buy gold appears only when it is on, at least one provider is complete and at least one package is active.

Credentials are stored only in `payments.local.php` in the state directory (mode `0600`, override the path with `TRAVIAN_PAYMENTS_CONFIG`), never in SQL, `globalConfig.php`, logs, forms or the repository. The file is shared by every world on the installation. Orders, events and flags live in the account database; the gold itself is credited in the world named on the order (found through `gameServers.configFileLocation`).

Register these webhook URLs with the providers (the admin page shows them for the current world URL):

| Provider | URL | Events |
| --- | --- | --- |
| Stripe | `<world URL>/payments/stripe/webhook` | `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `charge.refunded`, `charge.dispute.created` |
| PayPal | `<world URL>/payments/paypal/webhook` | `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`, `CUSTOMER.DISPUTE.CREATED` |

Webhooks bypass the session and CSRF; the provider signature (Stripe HMAC with a 5 minute tolerance, PayPal's `verify-webhook-signature`) is the only credential. The return page (`/payment/return`) never credits gold; for PayPal it captures the approved order and returns to the village.

Gold is credited exactly once per order. A refund takes back the proportional share of the purchased gold (a dispute takes all of it); the balance may go negative and flags the account in the admin page; there is no automatic ban. Vouchers and the admin package-code generators are unchanged.

## Activation CAPTCHA

The activation wizard (`/activate.php`) and `/respawn` can ask for a score-based reCAPTCHA v3 before a village is created. It is off by default: with no keys there is no widget, no script is loaded and nothing is verified.

Multihunter configures it under **World > Activation CAPTCHA** in `admin.php?action=captcha`:

1. Create a reCAPTCHA v3 key pair for the world's domain.
2. Enter the site key and the secret key and save. A blank secret keeps the stored one; **Remove the keys** turns the CAPTCHA off again.

The keys are stored only in `captcha.local.php` in the state directory (mode `0600`, override the path with `TRAVIAN_CAPTCHA_CONFIG`), never in SQL, `globalConfig.php`, logs, forms or the repository.

## Discord world-event webhook

The webhook is **off by default**. A multihunter configures it under **Discord webhook** in `admin.php?action=webhook`: the on/off switch, the webhook URL, the events to announce and the World Wonder milestone levels. The URL is a secret, so it is stored only in `webhook.local.php` in the state directory (mode `0600`, override the path with `TRAVIAN_WEBHOOK_CONFIG`), never in SQL, `globalConfig.php`, logs, forms or the repository, and the encrypted state archive of a backup carries it with the other `*.local.*` files. The field is write-only: leave it blank to keep the stored URL, tick *Remove the URL* to delete it (which also switches the webhook off). Only `https://discord.com/api/webhooks/<id>/<token>` addresses are accepted.

Events: world start, world end, artifacts released, World Wonder plans released, a World Wonder level milestone (default 10, 25, 50, 75 and 100), alliance founded, and the round winner. Each is announced at most once. The game writes a row to `webhook_queue` in the world database (so worlds installed before that table existed must be reinstalled) and the engine job `webhookDelivery` posts it: one message per five-second tick at most, each request limited to three seconds, Discord's 429 answers waited out, failures retried with backoff and dropped after eight attempts, and at most 100 messages pending. Messages hold only public facts: the world name, a World Wonder level, or an alliance's public name and tag (the winner's alliance for the round winner); never a player name, id, e-mail, coordinate or address, and Discord mentions are switched off. *Send test message* queues a test post.

## Administrator two-factor sign-in and audit log

### Two-factor sign-in (TOTP)

The administrator accounts (Support, id 0, and Multihunter, id 2) can be protected with a time-based one-time password (RFC 6238: HMAC-SHA1, 30 second steps, 6 digits), implemented locally; no external service is involved and the QR code is rendered by the game itself. **It is off by default**, so a fresh install, the setup wizard and a headless install (`TRAVIAN_INSTALL_*`) behave exactly as before and need no extra step.

The main administrator (Support) sets the **mode** under **World > Two-factor sign-in** in `admin.php?action=twoFactor`:

| Mode | Behaviour |
| --- | --- |
| Off | No codes are asked. |
| Optional | An administrator who has enrolled an authenticator is always asked for a code after the password; others sign in with a password only and can enrol from the same page. |
| Required | Every administrator needs an authenticator. One who has not enrolled is taken through enrolment right after the correct password: scan the QR code (or type the secret) into an authenticator app, confirm with a code, and keep the ten single-use recovery codes, which are shown once. |

Details worth knowing:

- **What is stored.** `admin-2fa.local.json` in the state directory (mode `0600`, written atomically under a lock; override the path with `TRAVIAN_ADMIN_TWO_FACTOR_FILE`): the mode, each enrolled administrator's TOTP secret, the recovery codes as `password_hash` digests and the last accepted time step (a code works once). Nothing is stored in SQL, `globalConfig.php`, logs or the repository. A missing file means *off*. An unreadable or invalid file fails closed (treated as *required*), so the secrets cannot be bypassed by damaging the file.
- **The second step.** After the password, a pending sign-in is kept in the session for five minutes, bound to the account, the client IP and a form token. It carries no rights: only a verified code (or recovery code) creates a one-minute grant that `Session::login()` consumes, which every sign-in path (password form, lobby hand-off, social sign-in) goes through. Sitters can never pass the second step, so a sitter cannot enter a protected administrator account. A protected administrator gets no remember-me cookie and an existing one is not honoured.
- **Rate limiting.** Code attempts use the login throttle in their own `admin2fa` scope: five wrong codes per account and client IP, then a 15 minute lockout, with a separate per-IP limit.
- **Day to day.** An enrolled administrator can mint new recovery codes or remove the authenticator on the same page; both need a current code.
- **Locked out.** Use a recovery code. If those are gone too, set `TRAVIAN_ADMIN_TWO_FACTOR=off` in `.env` and recreate the `php` service (`docker compose up -d php`): this override can only **lower** the stored mode (`required` > `optional` > `off`). It is an emergency break-glass for a locked-out operator, not a way to configure the feature: raising the mode is an admin setting, so the environment can never make the game demand codes nobody has enrolled, and the page shows a notice while the override is active. After signing in, enrol again, remove the variable and recreate the service. As a last resort delete `admin-2fa.local.json` from the state volume.

### Audit log

Every state-changing request in the control panel (a POST that passed the CSRF guard, including a confirmed state-changing GET) and every administrator sign-in event is written to the `admin_log` table of the world: when, the acting administrator (account id and name), the panel action or event, the identifying target (`uid=5 kid=123 section=killHero`), details and the client IP. Recorded events are the panel actions, `sign-in`, `sign-in-failed`, `sign-in-refused` (a sitter), `sign-out` and the two-factor events (`two-factor-passed`, `two-factor-failed`, `two-factor-throttled`, `two-factor-enrolled`, `two-factor-removed`, `two-factor-recovery-codes`, `two-factor-mode`). Only the names of the submitted fields and a whitelist of identifying parameters are stored, never request values, so passwords, codes and secrets cannot reach the log. Viewing the log is not logged.

Read it under **Moderation > Audit log** (`admin.php?action=adminLog`): newest first, 50 per page, filtered by administrator (name or id), action, target text, IP prefix and date range. `admin_log` is world data: it is reshaped in `database/world.sql` (this repository has no migrations, so an existing world needs a reinstall) and the post-round cleanup leaves it alone (the other world tables are emptied), so the trail survives a round reset. Only a full reinstall, which recreates every table from the schema, starts it empty. While both keys are set the wizard loads `recaptcha.net/recaptcha/api.js` and sends the token with the final post; the server verifies it (action `activation`, score at least 0.5) before creating the village and fails closed if the provider cannot be reached. Other providers plug in through `Game\Captcha\CaptchaProvider`.
