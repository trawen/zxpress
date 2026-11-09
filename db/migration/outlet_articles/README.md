# Outlet — перевод первых номеров (тест)

Press `314`. Issues: `1-3`, `004`…`010`.

## Модель

`.env`: `ODIROUTER_MODEL=gemini-3.5-flash` (long >25k → `ODIROUTER_MODEL_LONG`).

## Прогон

```bash
python3 db/migration/outlet_articles/seed.py
python3 db/migration/outlet_articles/process.py --resume --workers=2
```

Стадии по отдельности: `--stage=translate|markdown|enrich`.

Выход:

- `local/work/OUTLET_texts/articles/*.json`
- `site/cli/imports/outlet_i18n/`
- `db/migration/outlet_articles/i18n/{issue}/`
