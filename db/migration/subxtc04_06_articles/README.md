# Subliminal Extacy #04–#06 issues + articles

Создаёт недостающие выпуски SE#04 / #05 / #06 у `press_id=213` и импортирует статьи
(markdown RU/EN + meta). Dual-write в `ezines_v2`.

Админка издания: `admin_issue.php?id=213`

| Номер | title | sort_order | date (UTC) | статей |
|------:|------:|-----------:|-----------:|-------:|
| 04 | `04` | 50 | 2021-03-14 | 15 |
| 05 | `05` | 60 | 2023-12-02 | 13 |
| 06 | `06` | 70 | 2024-03-16 | 15 |

Также поднимает `sort_order` у уже существующего `#07` до `80`, если он ещё `< 80`,
и пересчитывает у издания `press.numbers` + `years_from` / `years_to` (публичное
`smn-press-meta-years`) по min/max датам выпусков; dual-write в `ezines`.

- тексты: `site/cli/imports/subxtc0{4,5,6}_i18n/*.json`
- `text_type*` = `3` (markdown)
- теги **не** ставятся по умолчанию; только с `--with-tags` (в JSON уже из каталога)

Повторный запуск идемпотентен. Перезапись текстов: `--force`.

## Запуск

```bash
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc04-06-articles.php --dry-run
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc04-06-articles.php --apply
```

Только один номер:

```bash
.../import-subxtc04-06-articles.php --apply --only=4
```

Обёртка:

```bash
php db/migration/subxtc04_06_articles/apply.php --dry-run
php db/migration/subxtc04_06_articles/apply.php --apply
```

## Проверка

- `admin_issue.php?id=213` — выпуски `04`/`05`/`06` между `03` и `07`
- `admin_articles_new.php?id=213&issue=<new_id>` — 15 / 13 / 15 статей
