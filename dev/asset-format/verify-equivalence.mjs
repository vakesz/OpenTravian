#!/usr/bin/env node
// Proves a formatted file is equivalent to the original reference bytes:
// (SVG) svgo's output of both must be identical. (CSS) esbuild's minified output of both must be identical once the only things a
// formatter may legitimately change are normalised on both sides:
//   - whitespace after `:` and `(`, before `)`, and around `, ; { } /` (never inside strings/url())
//   - the trailing `;` before `}`
//   - hex-digit case of colours
// Whitespace before `:` and between selector/calc tokens is kept, because it is
// significant (`a :hover` vs `a:hover`, `calc(1 -2)`).
//
//   node verify-equivalence.mjs <original> <formatted>
import { readFileSync } from 'node:fs';
import { extname } from 'node:path';
import { transform } from 'esbuild';
import { optimize } from 'svgo';

const [orig, formatted] = process.argv.slice(2);
const loader = { '.css': 'css', '.js': 'js', '.svg': 'svg' }[extname(formatted)];
if (!loader) throw new Error('only css/js/svg are verified this way');

const protectedToken = /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|url\([^)]*\))/g;
const normaliseCss = (code) => code
  .split(protectedToken)
  .map((part, i) => (i % 2 ? part : part
    .replace(/\s*([,;{}/])\s*/g, '$1')
    .replace(/:\s+/g, ':')
    .replace(/\(\s+/g, '(')
    .replace(/\s+\)/g, ')')
    .replace(/;}/g, '}')
    .replace(/#[0-9a-fA-F]{3,8}\b/g, (m) => m.toLowerCase())))
  .join('');

// JS: compare ASTs (locations, raw text and redundant parentheses ignored), so a
// formatter that only re-spaces, re-quotes or drops parentheses is accepted.
import * as babel from 'prettier/plugins/babel';
const dropped = new Set(['start', 'end', 'loc', 'range', 'extra', 'comments', 'leadingComments', 'trailingComments', 'innerComments', 'tokens', 'errors', '__contentEnd']);
const canon = (node) => {
  if (Array.isArray(node)) return node.map(canon);
  if (node && typeof node === 'object') {
    return Object.fromEntries(Object.keys(node).filter((k) => !dropped.has(k)).sort().map((k) => [k, canon(node[k])]));
  }
  return node;
};
const astOf = (f) => JSON.stringify(canon(babel.parsers.babel.parse(readFileSync(f, 'utf8'), {})));

const min = async (f) => {
  if (loader === 'js') return astOf(f);
  // SVG: svgo (whitespace/format-normalising, ids and viewBox kept) must give the same output.
  if (loader === 'svg') {
    return optimize(readFileSync(f, 'utf8'), { multipass: true, plugins: [{ name: 'preset-default', params: { overrides: { cleanupIds: false } } }] }).data;
  }
  const code = (await transform(readFileSync(f, 'utf8'), { loader, minify: true, legalComments: 'none' })).code;
  return loader === 'css' ? normaliseCss(code) : code;
};
const [a, b] = await Promise.all([min(orig), min(formatted)]);
if (a !== b) {
  let i = 0;
  while (a[i] === b[i]) i++;
  console.error(`NOT equivalent: ${orig} vs ${formatted}\n  at ${i}: ${JSON.stringify(a.slice(i - 60, i + 60))}\n         ${JSON.stringify(b.slice(i - 60, i + 60))}`);
  process.exit(1);
}
console.log(`equivalent (${a.length} normalised bytes): ${formatted}`);
