# Subliminal Extacy — категории статей (ezine_categories)

Присваивает категории ZXPRESS всем статьям SE#00–#07 (`press_id=213`) по
`title_*` и `meta_description_*` из i18n JSON (классификация через OdiRouter).

Матч в БД: `issue.title` (двузначный номер выпуска) + `articles.number` (TOC).

## Подготовка (без записи в БД)

```bash
node db/migration/subxtc_article_categories/classify.mjs
node db/migration/subxtc_article_categories/classify.mjs --resume
node db/migration/subxtc_article_categories/build_sql.mjs   # пересобрать SQL из assignments.json
```

Требуется `ODIROUTER_API_KEY` (как в `local/work/alchnews_dl/translate_alchnews_i18n.mjs`).

Пишет в `generated/`:

- `assignments.json` — issue, number, category_id, meta, confidence
- `01_insert_ezine_article_categories.sql` — `INSERT IGNORE` + пересчёт `articles_count`
- `classify_progress.json` — для `--resume`
- `summary.json`

Источник текстов: `site/cli/imports/subxtc{00..07}_i18n/*.json`.

## Применение

```bash
python3 db/migration/subxtc_article_categories/apply.py
```

На сервере после `git pull`:

```bash
cd /home/dockeruser/zxpress
python3 db/migration/subxtc_article_categories/apply.py
```

Повторный `apply` идемпотентен (`INSERT IGNORE`).
