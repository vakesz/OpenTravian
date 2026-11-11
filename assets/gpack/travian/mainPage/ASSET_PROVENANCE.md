# Imported asset provenance

Record each imported or overwritten Gpack asset here before handoff. The record must identify the exact rendered reference asset and its local caller; a CDN transfer alone is not evidence of either.

Use one row per exact file or tightly bounded directory tree. Keep the upstream licence notice verbatim in the entry or in a repository file linked from the final column. Do not infer rights for assets whose provenance is unknown.

Sources are written as `origin:/path`. The only Travian host a tracked file names is `cdn.legends.travian.com`, the recorded asset source for Gpack files, and it appears only in this file, `NAME_MAP.json` and `tools/`; no automated check enforces this. The origins are: `public-site` (the public Travian homepage site), `lobby` (its lobby news API), `news-media` (the public site's WordPress media host), `reference-world` (the live reference world, read-only), and `cdn.legends.travian.com`, which is the recorded asset source for Gpack files.

| Path | Reference route and state | Local caller | Source | Retrieved (UTC) | Attribution | Upstream licence notice |
| --- | --- | --- | --- | --- | --- | --- |
| `img_{ltr,rtl}/homepage/stage-layer-{1,2,3,4,5}.webp`, `img_{ltr,rtl}/homepage/unit-shadow.webp` | `public-site:/international`, international public homepage, Romans stage | `resources/Templates/system/homepage.php` | `public-site:/image/tribeStage/layer1.webp`; `public-site:/image/tribeStage/layer2.webp`; `public-site:/image/tribeStage/layer3.webp`; `public-site:/image/tribeStage/layer4.webp`; `public-site:/image/tribeStage/layer5.webp`; `public-site:/image/tribeStage/unitShadow.webp` | 2026-11-06T08:11:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/{roman,teuton,gaul}-{1,2,3}.webp` | `public-site:/international`, tribe-stage carousel | `resources/Templates/system/homepage.php` | `public-site:/image/tribeStage/roman1.webp`; `public-site:/image/tribeStage/roman2.webp`; `public-site:/image/tribeStage/roman3.webp`; `public-site:/image/tribeStage/teuton1.webp`; `public-site:/image/tribeStage/teuton2.webp`; `public-site:/image/tribeStage/teuton3.webp`; `public-site:/image/tribeStage/gaul1.webp`; `public-site:/image/tribeStage/gaul2.webp`; `public-site:/image/tribeStage/gaul3.webp` | 2026-11-06T08:11:00Z; 2026-11-06T08:16:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_ltr/homepage/public-wordmark.svg` (LTR tree only; direction-invariant, used by both directions) | Local only: the OpenTravian header wordmark; the reference draws its own logo here | `resources/Templates/system/homepage.php` | Original work drawn for this project (shield emblem, ring and the name as text); no upstream file | 2026-11-11 | OpenTravian contributors | MIT, see the repository `LICENSE` |
| `fonts/homepage/{noto-sans-regular,noto-sans-bold,red-rose-bold}.ttf` | `public-site:/international/news/2026/10/22/winter-time-the-effects-on-your-game/`, rendered public article typography | `lang/overrides.css` | `public-site:/bundle/9ab05beb031b68562995.ttf`; `public-site:/bundle/27d083f7f844fb896ae7.ttf`; `public-site:/bundle/0310e7badf741637965f.ttf` | 2026-11-06T12:59:19Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/{egyptian-small,hun-small}.png` | `public-site:/international`, first-future-gameworld tribe row | `resources/Templates/system/homepage.php` | `public-site:/bundle/49fe09f41ea7a3e66253.png`; `public-site:/bundle/cc90904b88e591c1a6a1.png` | 2026-11-06T09:29:02Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/{spartan-small,viking-small}.png` | `public-site:/international/calendar`, `.calendarGameworld .tribeImages .spartan/.viking` tribe icons | `resources/Templates/system/homepage.php` | `public-site:/bundle/a77793fd5fff8eb7e285.png`; `public-site:/bundle/ce3ae51b0c08b5ad7abe.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-server-start.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/faa30c86298a952e9509.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-speed.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/79adb7a1f967b6507709.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-artefacts.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/b0c2fc2752068d369501.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-construction-plans.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/b6d6326b484e07e4946d.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-server-end.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/9393c0745394bc9ad68e.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-wonder.png` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/05b5e1cc0bba000979b6.png` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-header-default.jpg` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/edcaa04ad86858ddf968.jpg` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/calendar-header-default-speed.jpg` | `public-site:/international/calendar`, `.gameworldDetails` icon / `#calendarGameworldDetails .header .background` artwork | `resources/Templates/system/homepage.php` | `public-site:/bundle/292e9b9fdecd2b8bf5ca.jpg` | 2026-11-06T18:00:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/cta-corner-{top-left,top-right,bottom-left,bottom-right}.png` | `public-site:/international`, public `GO TO LOBBY` and language-selection modal frames | `lang/overrides.css` | `public-site:/bundle/a78dce3ec2672963c250.png`; `public-site:/bundle/9b0c0305796850ef06d8.png`; `public-site:/bundle/2be523b7fa379db45a89.png`; `public-site:/bundle/46de4880db200d7e3e23.png` | 2026-11-06T09:29:02Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/cta-frame.jpg` | `public-site:/international`, `GO TO LOBBY` action | `resources/Templates/system/homepage.php`, `lang/overrides.css` | `public-site:/bundle/22c4efc33e748431458a.jpg` | 2026-11-06T08:11:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/winter-excerpt.jpeg` | `public-site:/international`, Winter Time news card | `resources/Templates/system/homepage.php` | `news-media:/wp-content/uploads/2026/10/winter-excerpt-160x160.jpeg` | 2026-11-06T08:11:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/news-background-1920x1080.jpg` | `public-site:/international/news/2026/10/22/winter-time-the-effects-on-your-game/`, rendered news list (`/international/news`) and article backdrop | `lang/overrides.css`, `.homepageNewsPage`, `.homepageArticlePage` | `public-site:/bundle/e31926cb7977b9f2a04a.jpg` | 2026-11-06T12:59:19Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/winter-banner-1024x379.jpg` | `public-site:/international/news/2026/10/22/winter-time-the-effects-on-your-game/`, rendered Winter Time article banner | `resources/Templates/system/homepage.php` | `news-media:/wp-content/uploads/2026/10/winter-banner-1024x379.jpg` | 2026-11-06T12:59:19Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/ttsquare-160x160.jpg` | `public-site:/ae/news/2025/01/06/tournament/`, Tournament news-card preview | `resources/Templates/system/homepage.php` | `news-media:/wp-content/uploads/2025/05/ttsquare-160x160.jpg` | 2026-11-06T14:25:24Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/tournament-banner-1024x503.jpg` | `public-site:/ae/news/2025/01/06/tournament/`, rendered Tournament article banner | `resources/Templates/system/homepage.php` | `news-media:/wp-content/uploads/2025/05/Screenshot-2025-05-16-at-15.34.24-1024x503.jpg` | 2026-11-06T12:59:19Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/{roman-small.png,teuton-small.png,gaul-small.png}` | `public-site:/international`, next-game-world card | `resources/Templates/system/homepage.php` | `public-site:/bundle/709ec0774e13c8d38905.png`; `public-site:/bundle/806172fb9bb3ecd402c2.png`; `public-site:/bundle/c4c250e333ba8f9a311d.png` | 2026-11-06T08:11:00Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/homepage/social-google.svg` | `public-site:/international#registration`, Google button in the registration overlay (the reference renders it through `accounts.google.com/gsi/client`) | `resources/Templates/system/registrationDialog.php` | `https://developers.google.com/static/identity/images/signin-assets.zip`, file `Android + Web/SVG/Light/Theme=Light, Show text=No, Shape=Square, Platform=Android+Web.svg` | 2026-11-06 | Google LLC | Google "Sign in with Google" branding guidelines (`https://developers.google.com/identity/branding-guidelines`); see "Registration overlay social icons" below |
| `img_{ltr,rtl}/homepage/social-apple.svg` | `public-site:/international#registration`, Apple button in the registration overlay (the reference renders it through `appleid.auth.js`) | `resources/Templates/system/registrationDialog.php` | `https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js`, logo path and black square from its button renderer | 2026-11-06 | Apple Inc. | Apple Human Interface Guidelines for Sign in with Apple and the Apple Developer Program License Agreement; see "Registration overlay social icons" below |
| inline SVG icons in `system/homepage.php` `#homepageNavDialog` (Home, News and updates, Game world calendar, Tournament, Data consent; plus the row chevron) — no separate files | `public-site:/international`, open header menu (`header .pageMenu .menu`) | `resources/Templates/system/homepage.php`, `lang/overrides.css` | `public-site:/bundle/startpage.434.js` (uncompressed SHA-256 `84f9042ad0ec8873767f215c78c39e63faa2214a2c878c45b010c22b4450976c`), inline SVG components `it` (home, viewBox `0 0 18 20`), `st` (news, `0 0 20 18`), `lt` (calendar, `0 0 18 20`), `ht` (tournament, `0 0 20 20`), `nt` (data consent, `0 0 20 20`) and `Ua` (chevron, `0 0 12 7.4`); `path` data copied unchanged | 2026-11-06T18:15:10Z | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `img_{ltr,rtl}/legacy/views/shop/package{1,2,4,5,6}.png` (`package3.png` was already present) | `reference-world:/dorf1.php`, Gold shop dialog (header Gold link), Buy Gold tab package grid, `.packageImage img` | `resources/Templates/payment/buyGold.php`, `resources/Templates/payment/showLowGold.php` (file names stored in `goldProducts.goldProductImageName`) | `https://cdn.legends.travian.com/gpack/692.2/img_ltr/legacy/views/shop/package{1,2,4,5,6}.png`; `https://cdn.legends.travian.com/gpack/692.2/img_rtl/legacy/views/shop/package{1,2,4,5,6}.png` (the two directions differ byte-wise; each is stored in its own tree) | 2026-11-06 | Travian Games GmbH (as identified on the reference page) | © 2004 - 2026 Travian Games GmbH. All rights reserved. |
| `fonts/homepage/noto-serif-bold.ttf` | unrecorded | `lang/overrides.css` | unrecorded | unrecorded | unrecorded | licence not recorded in this repository |
| `fonts/iranyekanweb{light,bold}.{woff,woff2}` | unrecorded | `lang/overrides.css` | unrecorded | unrecorded | unrecorded | licence not recorded in this repository |
| `fonts/IranSans4/{woff,woff2}/IRANSansWeb.{woff,woff2}` | unrecorded | `lang/overrides.css` | unrecorded | unrecorded | unrecorded | licence not recorded in this repository |
| `fonts/roboto{light,regular,bold}.{woff,woff2}` | unrecorded | `lang/overrides.css` | unrecorded | unrecorded | unrecorded | licence not recorded in this repository |

## Homepage copy and public-route provenance

`system/homepage.php` has no runtime reference requests. Its visible public
catalogue values are decoded only from the local
`game/include/resources/Translation/HomepagePublicCatalog.php` snapshot. The
exact language names, country names, route identifiers, flags, public format
locales, directions, and public copy came from
`public-site:/bundle/startpage.434.js` and the corresponding
`public-site:/localisation/{language}.json` catalogues observed on
2026-11-06.

The live `public-site:/ir` route redirects to
`public-site:/international/ir` and renders `en-US`/LTR rather than
providing a `fa-IR` catalogue. `HomepageCtrl` therefore applies an explicit,
homepage-only `fa-IR → en-US` public route mapping; it does not change the
local game's Persian locale or direction elsewhere.

The local news snapshot (`Winter Time – The Effects on Your Game`) was read at
2026-11-06T09:20:23Z from
`lobby:/api/news?lang=en&amount=1`. The game world
card and `/homepage/calendar` are not snapshots: they list this local world
(name, speed, start, enabled tribes, artefact and construction-plan release
dates from the world configuration) using the reference's calendar layout.

The dated Winter Time article snapshot was read at 2026-11-06T12:59:19Z from
`lobby:/api/news/2026/10/22/winter-time-the-effects-on-your-game/?lang=en`.
The `Tournament` control was observed at
`public-site:/ae/tournament` to issue a 301 to
`/ae/news/2025/01/06/tournament/`; its local equivalent issues the same
permanent redirect to the dated local article. The article snapshot was read
at the same time from
`lobby:/api/news/2025/01/06/tournament/?lang=en`.
Both articles are rendered from the typed local
`HomepagePublicArticleCatalog.php` snapshot, rather than from source HTML or a
runtime API call. Their source-content links remain links only where the live
article itself displays them; public shell navigation, assets, CSS, and JS are
all local `/homepage/...` and Gpack resources. Source attribution and licence
notice remain Travian Games GmbH, © 2004 - 2026 Travian Games GmbH. All rights
reserved.

`game/include/resources/HomepagePublicArticleLocales.php` holds the per-language
preview text and body of the two catalogued articles. It was generated on
2026-11-06 from
`lobby:/api/news?lang={lang}&after=&amount=200`, queried
for each of the 35 distinct `lang` values of the 48 public language options
(the same endpoint the reference news pages call). The endpoint answers with the
English article for any language that has no translation: only `de` differs, and
only for the Winter Time article (preview text and body; the Tournament article,
every title, date and image are identical in all languages, which the generator
verifies). The earlier claim that the API returns English for `de` was wrong; it
applied to the Tournament article. Uncompressed override JSON SHA-256
`2c6872e4516aa2e66530ee993f49a5d83469869ab73bab30a4e11db05fe6c8f9`.
The reference article pages read `lobby:/api/news/{id}/?lang={lang}` (observed on `/ae/news/...`: `lang=ar`, which returns the English article); for `de` that endpoint returned the same preview and body as the list endpoint, and the Tournament article was identical to English. Attribution and licence notice remain Travian Games GmbH, © 2004 - 2026 Travian
Games GmbH. All rights reserved.

`game/include/resources/Translation/HomepagePublicCatalog.json` (loaded by the `HomepagePublicCatalog.php` beside it) is a data-only,
readable JSON snapshot of the full current public language selector. It was
generated on 2026-11-06 from
`public-site:/bundle/startpage.434.js` (48 complete picker
records, including route, flag, language/country labels, and direction) and
each distinct source catalogue at
`public-site:/localisation/{language}.json` (37 catalogues and 89
public start-page keys covering the home, news and calendar pages, page titles
and meta descriptions, the registration and lobby-login overlays). Its
stored readable JSON file (`Translation/HomepagePublicCatalog.json`) SHA-256 is
`0f7b3af0198682fd5c28a629e8135974e38f85c4c53fa213f1d2b6619c754082`.
It is called only by the local Homepage controller and eliminates runtime
reference requests for every picker choice. Source attribution and licence
notice remain Travian Games GmbH, © 2004 - 2026 Travian Games GmbH. All rights
reserved.

The public header uses the same source SVG component on the observed LTR and
RTL public routes. It is therefore retained as one direction-invariant local
wordmark rather than being mirrored or redrawn.

## Registration overlay social icons

`resources/Templates/system/registrationDialog.php` inlines the Facebook
button mark (`viewBox="0 0 2048 2048"`, a single path) copied from the `ze`
SVG component of `public-site:/bundle/startpage.434.js`, retrieved
2026-11-06 from the `public-site:/international` start page.
Attribution and notice remain Travian Games GmbH, © 2004 - 2026 Travian Games
GmbH. All rights reserved. The reference renders the Google and Apple buttons
through those providers' own scripts (`accounts.google.com/gsi/client`,
`appleid.auth.js`), so the local buttons use the providers' official artwork:

- `img_{ltr,rtl}/homepage/social-google.svg`: the unmodified official file
  `Android + Web/SVG/Light/Theme=Light, Show text=No, Shape=Square, Platform=Android+Web.svg`
  from Google's "Sign in with Google" branding asset bundle, downloaded from
  `https://developers.google.com/static/identity/images/signin-assets.zip`
  (linked from `https://developers.google.com/identity/branding-guidelines`),
  retrieved 2026-11-06. Attribution: Google LLC. Terms: Google's Sign in with
  Google branding guidelines permit these assets for presenting Google sign-in
  in an app that implements it; they must not be altered, recoloured or reshaped
  (the file is used byte-for-byte, only scaled to 38×38 by the page). The ZIP
  carries no separate licence file; the guidelines page is the governing notice.
- `img_{ltr,rtl}/homepage/social-apple.svg`: Apple's black, logo-only
  "Sign in with Apple" button (`data-mode="logo-only"`, `data-color="black"`,
  `data-border-radius="4"`, as the reference configures it). Apple publishes no
  downloadable SVG without an Apple ID, so the markup was taken verbatim from
  Apple's own button renderer in
  `https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js`
  (retrieved 2026-11-06): the logo path data and the `viewBox="6 6 44 44"`
  coordinate system of its `P()` function, the black square background
  (`rect`, `ry` = 4% of the 50-unit box) and the `#fff` logo fill that
  `c("black")` returns. The two layers are merged into one file; the path data is
  not redrawn. Attribution: Apple Inc. Terms: Apple's Human Interface Guidelines
  for Sign in with Apple and the Apple Developer Program License Agreement allow
  the button only in apps that implement Sign in with Apple; Apple trademarks and
  the Apple logo remain Apple's property. If the operator requires Apple's
  design-resource file instead, replace this file with it (needs an Apple ID).

Existing Gpack files whose import history predates this manifest are not relicensed by it. Document their provenance when they are next imported or replaced.

## Stylesheet storage format

`css_ltr/imports.css` and `css_rtl/imports.css` are the game stylesheets. The repository stores them formatted by `dev/asset-format/format.mjs` (prettier 3.9.10, deterministic, no hand edits); the production web image minifies them again. Retrieved 2026-11-06.

| File | SHA-256 of original reference bytes | SHA-256 of stored formatted file |
|---|---|---|
| `css_ltr/imports.css` | `6a45f2d91413f7a96af897cb1f9e74889b4fba5b43bd7368ad599fd271fb2e25` | `a296bdecb449d452d58b01f3aaf871cafef0008080d8f2e9bf5e42d50e892778` |
| `css_rtl/imports.css` | `13562ae7e140b1283cc547ee700b36fb59f21bd60d0000b3c8400983900c8224` | `3ccde40f7aa296b80e08ba115bc6e02795a7a94a33de8d1fcea875d78c00f7dd` |

Equivalence: `node dev/asset-format/verify-equivalence.mjs <original> <stored>` minifies both with esbuild and compares after normalising only whitespace after `:`/`(`, around `, ; { } /` (never inside strings or `url()`), the final `;` before `}`, and hex-colour case. Re-syncing from the reference must format the download before storing it and update both hashes in `tests/contracts/test-gpack-static-asset-contract.php`. Other single-line first-party SVGs were formatted the same way (svgo output of original and formatted file is identical).
