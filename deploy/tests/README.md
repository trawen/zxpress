# Smoke / Integration Tests (zxpress.ru)

## How to run

From project root:

```bash
chmod +x deploy/tests/smoke_http.sh deploy/tests/run_unit_php.sh deploy/tests/test_legacy_storage_mounts.sh deploy/tests/validate_env_contract.sh deploy/tests/test_chronology_startup_logs.sh deploy/tests/test_manticore_index_permissions.sh
LOG_LEVEL=STANDARD ./deploy/tests/validate_env_contract.sh
LOG_LEVEL=STANDARD ./deploy/tests/test_legacy_storage_mounts.sh
LOG_LEVEL=STANDARD ./deploy/tests/test_storage_migration_docs.sh
LOG_LEVEL=STANDARD ./deploy/tests/test_text_storage_alias_target.sh
LOG_LEVEL=STANDARD ./deploy/tests/test_chronology_startup_logs.sh
LOG_LEVEL=STANDARD ./deploy/tests/test_manticore_index_permissions.sh
LOG_LEVEL=STANDARD ./deploy/tests/smoke_http.sh
```

## Requirements

- Docker Compose stack must be running (at least `nginx`, `php`, `db`, `manticore`).
- `docker-compose.yml` must expose Nginx to the host on `http://localhost:80` (port 80).

## What it checks

- `GET /ezines.php` returns `200`
- `GET /rss.php` returns `200`
- `GET /admin_articles.php` without authentication returns `403`
- Legacy storage migration is consistent:
  - `site/archive`, `site/cat`, `site/chapters_images` are absent on host
  - `data/image-archive` exists (runtime target for `/chapters_images/*`)
  - compose config keeps unified `./data -> .../data` bind mount
- Text-storage migration docs are synchronized:
  - `docs/operations.md` contains migration contract + go/no-go + rollback
  - `data/README.md` contains as-is/to-be status + purge gate criteria
- Text storage URL aliases point to `content-store/*`:
  - `/articles/` -> `data/content-store/articles/`
  - `/articles_eng/` -> `data/content-store/articles-eng/`
  - `/chapters/` -> `data/content-store/chapters/`
- PHP startup chronology PNG: recent `php` container logs must show `cache/chronology/zxpress_dinamic.png` and must not reference legacy `data/generated/zxpress_dinamic.png`.
- Manticore indexer: `deploy/scripts/manticore-index-all.sh` completes without `Permission denied` / `FATAL: failed to open` (full reindex; slower).
- Environment contract has all required runtime variables (`DB_*`, `MANTICORE_*`, `APP_*`, `SAPE_USER_HASH`)

Notes:
- The script uses a non-bot `User-Agent` (`SmokeTest/1.0`) so `init.inc` does not disable sessions.
