# Vendored client libraries

The repository stores the readable upstream builds only. The production web image
minifies them (`docker/assets/build.mjs`, esbuild, legal comments kept inline); the
development bind mount serves them as-is. Retrieved 2026-11-06 from the npm
registry tarballs named below (licence headers are part of the files; d3 3.5.5
ships none in its dist, its BSD licence lives in the npm package).

Equivalence to what the reference served: the previously committed `*.min.js` was
byte-identical to the official minified dist of the same version for jQuery,
Chart.js, d3, d3pie and pixi.js (SHA-256 compared), so the unminified
dist file of that exact release is the same program.

| File | Upstream | Version | Source file in npm tarball | SHA-256 of stored file |
|---|---|---|---|---|
| `jquery-4.0.0.js` | jquery | 4.0.0 | `dist/jquery.js` | f5fb077959ca06faa1dc50761d8bbb836c6c78067932537a2b3fea9e401257c5 |
| `jquery.md5.js` | jquery.md5 plugin | unversioned | previously committed file, already readable, only renamed | cef854567443b5bf8bea0f97eb7203b3944d27bf724d104390cf6bfa08c71e6d |
| `d3/d3.js` | d3 | 3.5.5 | `d3.js` | fd5e0fdc7ff4a93cea2730db5b3a3a73fe54b1a6fe770575e14e72098b873478 |
| `d3/d3pie.js` | d3pie | 0.1.8 | `d3pie/d3pie.js` | 820aaa80b138b76a6a6d65ba61a8fb3cbc18bed97853932376bffa3d77afcab5 |
| `ChartJs/Chart.js` | chart.js | 2.9.4 | `dist/Chart.js` | 7428669133ec3ec4d5d771d4d78e6e16dc9385d6fa189a537276246b679f6a63 |
| `PixiJS/pixi.js` | pixi.js | 8.15.0 | `dist/pixi.js` | b1c0df941377fe7a62bbffcbf82822d047b6c368a12c8323f7bc74bb336147f4 |
| `popper.js` | @popperjs/core | 2.11.6 | `dist/umd/popper.js` | 7ac28acfff63ff5349ebfb4c87b1e82630dffedc3ce32938d97219363ef1cac9 |
| `simplebar.js` | simplebar | 5.3.6 | `dist/simplebar.js` | 47633dd97f12c1aa1501ecd4f9ef466482f45d556fa260b9cb083c69c94f0bf5 |
| `tippy.js` | tippy.js (UMD, Popper external) | unknown | formatted copy of the previous `tippy.min.js` (original SHA-256 a15581a5e19328c1...) | 0e20adb5e16ebec0dd7f7c806b5a78ff8d5955316382330cfa37bafb0b922d98 |

Notes
- `popper.js`: the previous minified file was equivalent to the official 2.11.6
  `popper.min.js` after esbuild canonicalisation; the official unminified UMD build
  replaces it.
- `simplebar.js`: the previous minified file equalled the official 5.3.6 build except
  for one string constant in which a no-break space (U+00A0) had been replaced by a
  plain space; the official build restores it.
- `tippy.js` has no public unminified upstream matching the shipped bytes (its
  version could not be matched against any published 5.x/6.x tarball). It is
  `dev/asset-format/format.mjs` (prettier 3.9.10) output of the previously
  committed file; `dev/asset-format/verify-equivalence.mjs` proves AST equality
  with the original.
