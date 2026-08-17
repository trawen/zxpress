# Schema migrations

`update.sh` (and therefore `remote-update.sh`, including `--fast`) applies every
pending file in this directory after `git pull`.

Rules:

- Only `db/migrate/*.sql` is auto-applied. Historical one-shots in
  `db/migration/` are not.
- Name files `YYYYMMDDHHMMSS_short_name.sql` so they run in order.
- New files must be safe to run once. Prefer `information_schema` guards for
  `ALTER TABLE`.
- Applied names are stored in `schema_migrations`. Do not rename a file after
  it has been applied.
