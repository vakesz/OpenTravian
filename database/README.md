# Database schemas

- `account.sql` creates the shared account database.
- `world.sql` creates one game world database.

The browser setup wizard imports both schemas into new, empty databases. Make schema changes in the matching file and verify a fresh installation after editing either one.

## Changing a schema

There is no migration path. Edit `account.sql` or `world.sql` only, then reinstall the world so the wizard imports the new schema; a world created from an older schema must be reinstalled (see [Reinstalling](../docs/install.md#reinstalling) for the safe reset).

Dev-only: `dev/schema-diff.php` diffs an installed world against these files and prints (or with `--apply` runs) the reconciling DDL; it is a development aid, not a migration system (see the header of that file for usage).

Recent additions to `world.sql` that need a reinstall on older worlds:

- `activation.newsletter` and `users.newsletter`: the registration overlay's newsletter opt-in.
- `account_oauth_identity`: links a Google, Facebook or Apple identity to an account (unique per provider and subject).
