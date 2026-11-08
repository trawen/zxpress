# Emulate — русский перевод статей

Журнал [Emulate](https://zxpress.ru/ru/ezines/emulate): 7 выпусков, **87** статей (~1 МБ текста EN). На `/ru/` сейчас показываются английские тексты (fallback).

## Файлы

- `i18n/*.json` / `site/cli/imports/emulate_i18n/`
  - `body_en_plain` / `body_ru_plain` — исходный моноширинный текст
  - `body_en` / `body_ru` — markdown (импорт, `text_type=3`)
  - `title_ru`, meta
- `translate_i18n.mjs` — EN→RU plain
- `to_markdown.mjs` — plain→markdown (ASCII в \`\`\`text)
- `site/cli/import-emulate-articles.php` — заливка (`press_id=66`, markdown)

## Перевод + markdown

```bash
node db/migration/emulate_articles/translate_i18n.mjs --resume --workers=2
node db/migration/emulate_articles/to_markdown.mjs --resume --workers=2
```

Special Edition ~300k — чанки + `ODIROUTER_MODEL_LONG`.

## Импорт на сервере

```bash
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-emulate-articles.php --dry-run
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-emulate-articles.php --apply
```

Только выпуск 01:

```bash
.../import-emulate-articles.php --apply --only=01
```

## Проверка

- https://zxpress.ru/ru/ezines/emulate — оглавления на русском
- открыть статью — тело на русском, EN по переключателю языка / `text_en`
