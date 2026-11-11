# Translations

Every installed language is built from layers. For a locale `xx-XX`, later layers win:

1. `en-US` (`game/include/resources/Translation/en-US.php`): defines every key and is the fallback.
2. The hand catalogue `Translation/xx-XX.php`, if the locale has one (today ar-AE, el-GR and fa-IR do).
3. The generated overlay `Translation/generated/xx-XX.php`, imported from the client bundles in `assets/i18n/reference/` and from route captures, and maintained by hand. Each line ends in a comment naming its source (`// bundle:...` or `// page:...`).

The generated overlay is the source of truth for the strings it holds. `tools/translations.php` lets an operator export a locale's catalogue, edit it in a spreadsheet or a translation editor, and import the result into the **hand catalogue**, with checks, without ever touching generated text.

## Export

```sh
docker compose exec -u www-data php php tools/translations.php export de-DE --out=/tmp/de-DE.csv   # or --format=po / --out=x.po
docker compose exec -u www-data php php tools/translations.php status de-DE
```

One row per en-US string, for any installed locale (`en-US` itself works as a template). Add `--bom` for a CSV that Excel opens as UTF-8.

| CSV column | Meaning |
| --- | --- |
| `path` | The key path as a JSON list, for example `["Alliance","Actions"]`. It identifies the row; do not edit it. |
| `section`, `key` | The same path, for reading. |
| `source` | Where the current value comes from, below. |
| `reference` | The bundle keys or capture route a generated value came from. |
| `en-US` | The English text. The importer refuses a row whose English text has changed since the export. |
| `value` | The locale's current value (empty when it falls back to English). **The only column to edit.** |

In the PO file the path is the `msgctxt`, the English text the `msgid`, the current value the `msgstr`, and `#.` comments carry the source and reference. `#, php-format` marks strings with placeholders and `#, fuzzy` entries are ignored on import.

| `source` | Meaning | Importable |
| --- | --- | --- |
| `hand` | A plain string in the hand catalogue | yes |
| `missing` | No value; the game shows English | yes (added to the hand catalogue) |
| `bundle` | Generated from a client bundle | no |
| `capture` | Generated from a route capture | no |
| `computed` | The hand catalogue builds the value with PHP (concatenation, helper calls, interpolation) | no |
| `list` | An entry of a list; an overlay replaces a list as a whole | no |

## Import

```sh
docker compose exec -u www-data php php tools/translations.php import de-DE /tmp/de-DE.csv            # plan only
docker compose exec -u www-data php php tools/translations.php import de-DE /tmp/de-DE.csv --apply
```

Without `--apply` it only prints the plan. Only rows whose `value` differs from the current one are looked at, an empty value never deletes anything, and `en-US` cannot be imported (it defines the keys every locale falls back to). Each row ends up as one of:

- **ACCEPT**: a `hand` or `missing` row with a changed value whose placeholders match the English text.
- **SKIPPED**: an edited `bundle` or `capture` row. It is never written: the generated entry would win at runtime anyway, and a regeneration would restore it. Change the generated overlay itself instead.
- **REJECTED**: a `computed` or `list` row, an unknown path, an English text that changed since the export, or placeholders that do not match.

Placeholders must match the English text: the same `%s` and `%d` in the same number (positional `%1$s` forms must number each one once), and the same `{name}` and `[TOKEN]` placeholders. The pattern is the one file `Translation/placeholders.regex`, read by this tool.

The exit status is 0 unless the file cannot be read or written (2); `--strict` makes any skipped or rejected row exit 1.

### Where it writes

`--apply` writes `game/include/resources/Translation/<locale>.php` through the PHP tokenizer. A plain string literal that is already there is replaced in place, with comments and layout untouched. A key with no hand entry is added to one managed block, between `// --- Imported by tools/translations.php ... ---` and `// --- End of imported translations ---`, just before the final `return $Definition;`. The file is replaced atomically, keeps its mode, is re-parsed, and every written value is read back before the replacement. If the file changes while the import runs, nothing is written.

A locale with no hand catalogue (for example de-DE) needs `--create-hand-file`, which creates `Translation/<locale>.php` holding just that block. The tool writes into the source tree, so run it in a writable checkout (the dev compose layer), not in a published image.

### Hand and generated values

Hand values interact with the generated overlay, which is read after the hand catalogue:

- A hand value **equal** to the generated one shows the same text.
- A hand value that **differs** from a generated entry is not used: the generated entry wins, which is why the importer skips such rows.
- Keys the overlay does not hold keep showing the hand value.

The locale caches follow the file modification times, so a changed catalogue shows up without a restart.
