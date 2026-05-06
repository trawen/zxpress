# Test fixtures

## `zxpress_test` database

Integration tests use a **separate MySQL database** `zxpress_test` (not production `zxpress_db`).

- **Creation:** `db/init/zzz-zxpress_test.sql` runs on first MySQL container init (alphabetically after `zxpress_db.mysql.sql`).
- **Grants:** `zxpress_u` receives `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `ALTER` on `zxpress_test.*` (see same file).

## `seed_test_data.sql`

Duplicate of the INSERT block for manual re-seeding if the test DB exists but rows were removed.

## Running integration tests

Set `ZXPRESS_INTEGRATION_TESTS=1` and point `DB_HOST` / `DB_PORT` at a reachable MySQL (from the host use published port, e.g. `3307`, with `docker-compose.test.yml`). See `docs/testing.md`.
