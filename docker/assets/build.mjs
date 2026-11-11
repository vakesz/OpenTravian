// Minifies the readable repository sources in place inside the image build
// stage. The repository never stores minified assets; only the web image does.
//
//   node build.mjs <out-root>     (expects <out-root>/gpack and <out-root>/public)
//
// CSS/JS  -> esbuild minify; legal comments (/*! ... */, @license) stay inline.
// JSON    -> JSON.stringify(JSON.parse(...)): same data, no whitespace.
// SVG     -> svgo (viewBox is kept by default), keeping every id/class/style that CSS or JS may
//            target. No source maps are produced.
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { extname, join } from 'node:path';
import { transform } from 'esbuild';
import { optimize } from 'svgo';

const root = process.argv[2];
if (!root) throw new Error('usage: node build.mjs <out-root>');

const svgoConfig = {
  multipass: true,
  plugins: [
    {
      name: 'preset-default',
      params: {
        overrides: {
          cleanupIds: false,
          inlineStyles: false,
          mergeStyles: false,
          removeUnknownsAndDefaults: false,
          removeHiddenElems: false,
          collapseGroups: false,
        },
      },
    },
  ],
};

function* walk(dir) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) yield* walk(path);
    else yield path;
  }
}

const stats = { css: [0, 0], js: [0, 0], json: [0, 0], svg: [0, 0] };
for (const file of walk(root)) {
  const type = extname(file).slice(1);
  if (!(type in stats)) continue;
  const src = readFileSync(file, 'utf8');
  let out;
  if (type === 'svg') {
    out = optimize(src, { ...svgoConfig, path: file }).data;
  } else if (type === 'json') {
    out = JSON.stringify(JSON.parse(src));
  } else {
    out = (await transform(src, { loader: type, minify: true, legalComments: 'inline', charset: 'utf8' })).code;
  }
  if (out.length >= src.length) continue;
  writeFileSync(file, out);
  stats[type][0] += src.length;
  stats[type][1] += out.length;
}
for (const [type, [before, after]] of Object.entries(stats)) {
  console.log(`${type}: ${before} -> ${after} bytes`);
}
