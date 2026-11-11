# Deploy

Plain `docker compose up -d` is the production configuration. It runs the published images (or images you built locally), keeps MariaDB and Redis private, and never mounts the source tree. Development layers `compose.dev.yaml` on top (see [development.md](development.md)).

## Published images

Tagged releases publish multi-arch (linux/amd64 and linux/arm64) images to the GitHub Container Registry:

- `ghcr.io/vakesz/opentravian-app` (PHP-FPM and the engine)
- `ghcr.io/vakesz/opentravian-web` (nginx with the built assets)

Each release `vX.Y.Z` is tagged `X.Y.Z`, `X.Y` and `latest`. Pin the version in `.env`:

```sh
TRAVIAN_VERSION=1.0.0
```

Compose then pulls `ghcr.io/vakesz/opentravian-app:1.0.0` and `ghcr.io/vakesz/opentravian-web:1.0.0`. To use your own build instead, run `docker compose build` and set `TRAVIAN_VERSION` to a local tag, or override `TRAVIAN_APP_IMAGE` and `TRAVIAN_WEB_IMAGE`. Versioning and upgrades: [operate.md](operate.md#versions-and-upgrades).

### Maintainers: cutting a release

The release workflow only validates the Compose files before it builds and publishes, so it does not run the test suite. Tag only a commit on `main` whose CI run passed, and keep branch protection requiring the `ci.yml` checks on `main` so that is the only way a commit gets there. Before the first `vX.Y.Z`, push a pre-release tag such as `v0.0.1-rc.1` to confirm the whole pipeline end to end.

### Maintainers: make the packages public

GitHub creates a package as private on its first publish and the workflow cannot change that. After the first release, for each of `opentravian-app` and `opentravian-web`: open the repository's **Packages**, choose the package, then **Package settings**, **Danger zone**, **Change visibility**, **Public**. Anonymous `docker pull` works only after that.

## HTTPS with Caddy

`compose.tls.yaml` adds Caddy, which obtains and renews a Let's Encrypt certificate, redirects HTTP to HTTPS and proxies to nginx.

1. Point your domain's DNS at the server and open ports 80 and 443 (and 443/udp for HTTP/3).
2. Enable the layer and set the domain in `.env`:

   ```sh
   COMPOSE_FILE=compose.yaml:compose.tls.yaml
   TRAVIAN_DOMAIN=game.example.com
   ```

3. Start the stack (the world installs at the same time if `TRAVIAN_INSTALL_*` is set, see [install.md](install.md)):

   ```sh
   docker compose up -d --wait
   ```

`TRAVIAN_DOMAIN` also sets `TRAVIAN_BASE_URL` (`https://game.example.com/`), the nginx allowed hosts and `TRAVIAN_TRUSTED_PROXY` (Caddy's fixed address on a private subnet that the layer pins) for you; set any of them explicitly to override. `web` keeps its loopback-only port (`APP_BIND_ADDRESS`), so only Caddy listens publicly. Caddy sends `Strict-Transport-Security: max-age=31536000` (one year, no `includeSubDomains`, no preload), so browsers keep to HTTPS after the first visit; the plain-HTTP `web` service never sends it. Certificates persist in the `caddy_data` volume; keep it, or Caddy re-requests them and may hit Let's Encrypt rate limits. Install the world after the domain is set: the world URL is stored at install time and must match the public address exactly.

To try it on your own machine, use a `.localhost` name with Caddy's internal CA:

```sh
COMPOSE_FILE=compose.yaml:compose.tls.yaml
TRAVIAN_DOMAIN=travian.localhost
TRAVIAN_TLS_INTERNAL=1
```

Browsers warn about the untrusted local certificate until you trust Caddy's root: `docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt .`. If port 443 is taken on your machine (for example by another proxy), change the published port in `compose.tls.yaml` for the test.

## Your own reverse proxy

Skip `compose.tls.yaml` if you already run Traefik, nginx or similar. Route the public hostname to the `web` service, set `TRAVIAN_BASE_URL` to the public HTTPS URL (with a trailing slash), and set `TRAVIAN_TRUSTED_PROXY` to the proxy's address or CIDR as seen from the `web` container, so IP bans and login throttling see real client addresses. Make sure the proxy overwrites, rather than appends to, any client-supplied `X-Forwarded-For`. Details: [configuration.md](configuration.md#client-ip-addresses-and-reverse-proxies).

Keep `APP_BIND_ADDRESS=127.0.0.1` (the default) when a proxy on the same host fronts the stack. Never publish the database or Redis ports.

## Hardening

Every service sets `no-new-privileges` and drops all Linux capabilities except the few it needs (the entrypoints of `db`, `redis`, `php` and `web` start as root and drop to an unprivileged user). Root filesystems are read-only, with tmpfs for scratch paths and named volumes for data. Memory limits are per service and the process cap is shared; all are set in `.env` ([configuration.md](configuration.md#environment-reference)). Size `TRAVIAN_MARIADB_BUFFER_POOL_SIZE` below `TRAVIAN_DB_MEM_LIMIT`. InnoDB neighbor flushing defaults off and the background flush rate defaults to 1000, which suits SSD and virtual disks.
