# Locale catalogues

`reference/<locale>/` holds the client string bundles for the 37 installed locales: `strings.js` and twenty-four JSON files, stored under descriptive local names (see the name map below).

- Attribution: Travian Games GmbH.
- Upstream licence notice: © 2004 - 2026 Travian Games GmbH. All rights reserved.
- Locales with no bundle, so they are not installed from one: `fa-IR`, `ar-EG`, `ar-SY`, `ar-SA`, `zh-CN`, `zh-HK`, `en-GB`, `en-AU`, `en-NZ`, `es-AR`, `es-MX`, `nl-BE`, `fr-BE`, `uk-UA` and the other codes in `ProfileLanguages`.

These files were the source of `game/include/resources/Translation/generated/<locale>.php` and `locales.php`, which are now maintained by hand, and of the client bundles served at `/js/<locale>/`. The German and English route captures that also fed the overlays are no longer kept in this repository.

## Stored form

The JSON files under `reference/<locale>/` are stored readable (2-space indent, keys in the downloaded order, UTF-8 unescaped, trailing newline), because the repository keeps readable sources only. The transformation is lossless: every file re-parses to exactly the downloaded object (checked per file; duplicate keys that parsing would drop were rejected). `reference/SHA256SUMS.txt` records, for each of the 888 JSON files, the SHA-256 of the bytes as downloaded from the source URL, the SHA-256 of the stored formatted file, and the path. `strings.js` files are stored exactly as downloaded (only the file name is lower-cased).

## File name map

Stored under `reference/<locale>/` with the local name on the left. Bundle keys inside the files keep the original file name as their prefix (for example `karte.zoomIn`, as quoted in the `// bundle:` comments of the generated catalogues).

| Local file | Reference file | Content |
| --- | --- | --- |
| `strings.js` | `Strings.js` | Global shell strings (`Travian.Strings`) and templates |
| `layout.json` | `layout.json` | Page shell, boxes, tooltips |
| `general.json` | `allgemein.json` | General vocabulary, buttons, dates |
| `map.json` | `karte.json` | Map |
| `village-views.json` | `dorf1u2.json` | Village overview and production |
| `player.json` | `spieler.json` | Player profile and settings |
| `api.json` | `api.json` | API messages |
| `quick-links.json` | `quickLinks.json` | Quick links |
| `rally-point.json` | `gid16.json` | Rally point (building 16) |
| `marketplace.json` | `gid17.json` | Marketplace (building 17) |
| `town-hall.json` | `gid24.json` | Town hall (building 24) |
| `residence-palace.json` | `gid25u26.json` | Residence and palace (buildings 25 and 26) |
| `plus.json` | `plus.json` | Plus and gold |
| `auctions.json` | `auctions.json` | Hero auctions |
| `admin.json` | `admin.json` | Support and moderation dialogs |
| `alliance.json` | `allianz.json` | Alliance |
| `items.json` | `items.json` | Hero items |
| `hero.json` | `hero.json` | Hero |
| `tasks.json` | `tasks.json` | Tasks |
| `reports.json` | `berichte.json` | Reports |
| `messages.json` | `nachrichten.json` | Messages |
| `login.json` | `login.json` | Login and registration |
| `raid-list.json` | `raidList.json` | Farm list (rally point tab) |
| `send-troops.json` | `a2b.json` | Send troops (rally point) |
| `troops.json` | `truppen.json` | Troop names and descriptions |

`SHA256SUMS.txt` uses the local names.

## Route captures

Generated overlay lines that end in `// page:<route>` came from a capture of that route in one language. The capture files are no longer kept in this repository.

## Host removal

No stored string, route capture or generated overlay may carry an external game host. The rule was applied to every imported file. Links to such a host are unwrapped to their link text; e-mail addresses and bare URLs at such a host become the local support route `/support/ticket`; capture `final` URLs keep only path and query. The one locale whose Homepage label is its domain name (`ro-RO` `general.startseite`) uses its own capitalised `startseiteCapital` label instead.
