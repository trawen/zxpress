# Issue descriptions from Gemini (`local/work/issue-desc-*.json`)

Данные из `local/scripts/generate-issue-description.mjs` → колонки `issue`:

- `description_ru` / `description_en`
- `meta_description_ru` / `meta_description_en`

## Подготовка (без записи в БД)

```bash
python3 db/migration/issue_gemini_descriptions/build.py
```

Пишет в `generated/`:

- `01_update_issue_descriptions.sql` — `UPDATE` по `issue.id`
- `summary.json`

По умолчанию SQL **перезаписывает** все 4 поля для выпусков, у которых есть JSON.

Только пустые поля в БД:

```bash
python3 db/migration/issue_gemini_descriptions/build.py --only-empty
```

(нужен доступ к docker `zxpress_db`)

## Применение (когда будешь готов)

```bash
python3 db/migration/issue_gemini_descriptions/apply.py
```

На сервере: залей `generated/` (или JSON + `build.py`), затем:

```bash
cd /home/dockeruser/zxpress
set -a; . ./.env; set +a
# опционально: ZXPRESS_ISSUE_DESC_WORK=/path/to/local/work python3 …/build.py
python3 db/migration/issue_gemini_descriptions/apply.py
```

Креды: `DB_USER` / `DB_PASS` / `DB_NAME` / `ZXPRESS_MYSQL_CONTAINER` (как у emulator-миграции).
