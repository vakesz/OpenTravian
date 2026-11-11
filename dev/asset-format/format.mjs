#!/usr/bin/env node
// Deterministically formats recovered (minified) CSS/JS/SVG into the readable
// form the repository stores. Never hand-edit a file this produces; re-run it.
//
//   node format.mjs <file>...          rewrite in place
//   node format.mjs --check <file>...  exit 1 if a file is not already formatted
//
// Equivalence (formatted vs original) is checked by verify-equivalence.mjs.
import { readFileSync, writeFileSync } from 'node:fs';
import { extname } from 'node:path';
import * as prettier from 'prettier';

const parsers = { '.css': 'css', '.js': 'babel', '.mjs': 'babel', '.svg': 'html' };
const check = process.argv.includes('--check');
let dirty = 0;
for (const file of process.argv.slice(2).filter((a) => !a.startsWith('--'))) {
  const parser = parsers[extname(file)];
  if (!parser) throw new Error(`unsupported file type: ${file}`);
  const src = readFileSync(file, 'utf8');
  const options = { parser, printWidth: 100, ...(parser === 'html' ? { htmlWhitespaceSensitivity: 'ignore' } : {}) };
  const out = await prettier.format(src, options);
  if (out === src) continue;
  if (check) { console.error(`not formatted: ${file}`); dirty++; } else { writeFileSync(file, out); console.log(`formatted ${file}`); }
}
process.exit(dirty ? 1 : 0);
