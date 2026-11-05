# Subliminal Extacy #07 articles

Импортирует 14 статей выпуска SE#07 (markdown RU/EN + meta + теги) в уже заведённый номер.

Целевой URL админки: `admin_articles_new.php?id=213&issue=2665`

- `press_id = 213` (Subliminal Extacy)
- `issue_id = 2665` (номер уже создан на сервере)
- тексты: `site/cli/imports/subxtc07_i18n/*.json`
- `text_type` / `text_type_ru` / `text_type_en` = `3` (markdown)
- dual-write в `ezines_v2`
- теги **не** ставятся по умолчанию (AI `tags_ru` шумные); только с `--with-tags`
- нумерация по TOC (пропуски `#10` и `#13` — статей в TAP нет; bonus Big Belly = `#16`)

Повторный запуск идемпотентен: статья с тем же `number` в выпуске пропускается. Перезапись: `--force`.

## Запуск локально (php-контейнер)

```bash
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc07-articles.php --dry-run
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc07-articles.php --apply
```

Или через обёртку (если смонтирован корень репо / `db/`):

```bash
php db/migration/subxtc07_articles/apply.php --dry-run
php db/migration/subxtc07_articles/apply.php --apply
```

## Запуск на сервере

```bash
cd /home/dockeruser/zxpress
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc07-articles.php --dry-run
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc07-articles.php --apply
```

Переопределение id (если на стенде другие):

```bash
docker compose exec -T php php .../import-subxtc07-articles.php --apply --press-id=213 --issue-id=2665
```

## Проверка

- `admin_articles_new.php?id=213&issue=2665` — 14 статей
- публичные URL статей с markdown RU/EN
- `/ru/updates-activity` — события `article.created`
