# Troubleshooting

Start with `docker compose ps` and `docker compose logs --tail=100 <service>`.

## Compose refuses to start: "Set MARIADB_ROOT_PASSWORD in .env"

`.env` is missing or a required value is empty. Run `sh config/bootstrap-env.sh`; it fills only the values that are not set yet.

## Port already in use

Another program uses 8080 (or 80/443 with `compose.tls.yaml`). Set `APP_PORT` in `.env` (the base URL follows it unless you set `TRAVIAN_BASE_URL` yourself) and run `docker compose up -d` again. Find the owner with `lsof -iTCP:8080 -sTCP:LISTEN`.

## Permission denied on the state volume or a bind mount

The `php` and `engine` services run as `www-data` with the ids in `APP_UID` and `APP_GID`. A bind-mounted backup export directory must be writable by that user: `sudo chown -R "$(id -u):$(id -g)" /srv/travian-backups`. If your account does not use 1000:1000 (macOS usually uses 501:20), set both values in `.env` and rebuild a locally built image: `docker compose build php`. Published images fix ownership of the state volume when the container starts.

## MariaDB exits with an io_uring or AIO error (OrbStack, some VMs)

The VM denies the io_uring permission MariaDB's native asynchronous I/O needs. Set `TRAVIAN_MARIADB_NATIVE_AIO=0` in `.env` and run `docker compose up -d`.

## arm64 (Apple Silicon, Raspberry Pi)

Published images and every base image are multi-arch, so nothing needs emulation. If a locally built image fails with `exec format error`, a leftover amd64 image is being reused: `docker compose build --pull` and `docker image prune`. To build both architectures yourself: `docker buildx build --platform linux/amd64,linux/arm64 -f docker/php/Dockerfile --target production .`.

## The setup wizard says the token is wrong, or is disabled

The token is `TRAVIAN_SETUP_TOKEN` from `.env`. It must be at least 24 characters; generate one with `openssl rand -hex 32`. After five wrong attempts from one address the wizard locks that address for 15 minutes. The wizard disappears once a world is installed.

## "Database names must point to empty databases"

A previous install left tables behind. Answer **Yes** when the wizard offers to clear exactly those two databases, or run `docker compose run --rm installer install --reset`. See [install.md](install.md#reinstalling).

## Every player appears to share one IP address

Docker's port publishing hides client addresses, so IP bans and login throttling apply to everyone at once. Run behind a reverse proxy and set `TRAVIAN_TRUSTED_PROXY`; `compose.tls.yaml` does this for you. See [deploy.md](deploy.md).

## "Support delivery is not configured for this world"

The world was installed without SMTP, or the support recipient is empty. Multihunter can add both in `admin.php?action=configurationDetails`. Queued mail is delivered once SMTP is configured.

## Missing columns or tables after an update

Schema changes ship without migrations. Reinstall as described in [install.md](install.md#reinstalling).

## Browser shows a certificate warning on `*.localhost`

Caddy's internal CA is not trusted by your system. Trust the root certificate or accept the warning for local testing only; real domains use Let's Encrypt and need no action.
