# OpenTravian

OpenTravian is a self-hosted game world. `game/` is the runtime, `assets/gpack/travian/mainPage/` is the game pack, and the images are published multi-arch (amd64 and arm64) containers.

## Play alone on your own computer

This sets up a private world that only your computer can reach, with you as its only player. You need no programming knowledge, about 2 GB of free memory and 15 minutes; the game runs inside Docker, so nothing else is installed on your machine.

1. **Install Docker.** On Windows and macOS install [Docker Desktop](https://www.docker.com/products/docker-desktop/) and start it; on Linux install [Docker Engine](https://docs.docker.com/engine/install/). On Windows also install [WSL](https://learn.microsoft.com/windows/wsl/install) (`wsl --install` in PowerShell) and type every command below in its Ubuntu terminal. On macOS use the Terminal app.

2. **Download the game** and go into its folder:

   ```sh
   git clone https://github.com/vakesz/OpenTravian.git
   cd OpenTravian
   ```

3. **Create the settings file.** This writes a file named `.env` with generated secrets:

   ```sh
   sh config/bootstrap-env.sh
   ```

4. **Describe your world.** Open `.env` in a text editor (`nano .env`; the file is hidden in most file managers because its name starts with a dot) and add these lines at the end. Choose your own name and passwords:

   ```sh
   COMPOSE_FILE=compose.yaml:compose.build.yaml
   TRAVIAN_INSTALL_PLAYER_NAME=Player
   TRAVIAN_INSTALL_PLAYER_PASSWORD=change-this-player-password
   TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD=change-this-admin-password
   TRAVIAN_INSTALL_SPEED=10
   TRAVIAN_INSTALL_FARM_VILLAGES=150
   ```

   - The name takes 3 to 15 letters or digits. Both passwords need at least 12 characters; use only letters, digits and dashes.
   - `SPEED` is how fast the game runs: `1` is the classic pace where buildings take hours, `10` is comfortable alone, and higher is faster still.
   - `FARM_VILLAGES` is how many computer-owned villages are placed on the map for you to raid and conquer, from 0 to 1000.
   - To play another tribe add `TRAVIAN_INSTALL_PLAYER_TRIBE=` with `1` Romans, `2` Teutons, `3` Gauls, `6` Egyptians, `7` Huns or `8` Spartans.

5. **Start the game.** The first start builds the game and creates the world, which takes several minutes. It is done when the command returns:

   ```sh
   docker compose up -d --build --wait
   ```

6. **Play.** Open [http://localhost:8080](http://localhost:8080) in your browser and sign in with the player name and password from step 4.

Afterwards:

| To | Run in the game folder |
| --- | --- |
| Stop the game (the world is kept, and time in it stands still) | `docker compose stop` |
| Start it again | `docker compose start` |
| Manage the world | Sign in as `Multihunter` with the admin password, then open [http://localhost:8080/admin.php](http://localhost:8080/admin.php) |
| Start over with a new world | `docker compose down -v`, change `.env` if you like, then step 5 again. This deletes the old world for good |

If a step fails, see [Troubleshooting](docs/troubleshooting.md); `docker compose logs --tail=50` shows what went wrong. To let other people join, see [Deploy](docs/deploy.md).

## Quick start

For those who know Docker and prefer the setup wizard:

```sh
sh config/bootstrap-env.sh
COMPOSE_FILE=compose.yaml:compose.build.yaml docker compose up -d --build --wait
```

Then open [http://localhost:8080/setup/](http://localhost:8080/setup/), enter the setup token the script printed, and finish the wizard. Prefer no wizard? Set `TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD` in `.env` before the last command and the world installs itself. Requires Docker Engine 24 and Compose 2.18 or newer.

To use the published images instead of building, run plain `docker compose up -d --wait` after `sh config/bootstrap-env.sh` (see [Deploy](docs/deploy.md#published-images)).

## Licence

Original code is MIT (see [LICENSE](LICENSE)). Bundled game assets, strings and third-party libraries keep their own notices in their directories and provenance files; the MIT licence does not extend to them.

OpenTravian is an independent project and is not affiliated with, endorsed by or sponsored by Travian Games GmbH.

## Documentation

| Guide | What it covers |
| --- | --- |
| [Install](docs/install.md) | Prerequisites, the setup wizard, headless install, optional email, reinstalling |
| [Deploy](docs/deploy.md) | Published images, versions, HTTPS with Caddy, your own reverse proxy, hardening |
| [Configuration](docs/configuration.md) | Every environment variable, generated files, proxies and client IPs, languages, social sign-in |
| [Operate](docs/operate.md) | Health and logs, backups and restore, upgrades and versions, resetting a world |
| [Troubleshooting](docs/troubleshooting.md) | Ports, permissions, arm64, OrbStack, io_uring, certificate warnings |
| [Development](docs/development.md) | Dev stack, checks, client tests, static analysis |
| [Translations](docs/translations.md) | Exporting a locale to CSV or PO, importing edits into its hand catalogue, and how that meets the generator |
| [World speed](docs/world-speed.md) | The speed profile |
| [Architecture](docs/architecture.md) | Services, volumes and how the installer is shared |
| [Tools](tools/README.md) | Operator CLIs and scoped migrations |
| [Contributing](docs/development.md) | Contributor rules and local differences |
