# Subliminal Extacy #00–#03 AI i18n update

Обновляет уже существующие статьи выпусков SE#00–#03 (press_id=213):
markdown RU/EN + title/meta, `text_type*=3`. Новые статьи не создаёт —
матч по `issue.title` + `articles.number`.

| Номер | issue title | статей |
|------:|------------:|-------:|
| 00 | `00` | 20 |
| 01 | `01` | 35 |
| 02 | `02` | 32 |
| 03 | `03` | 46 |

- тексты: `site/cli/imports/subxtc0{0,1,2,3}_i18n/*.json`
- теги по умолчанию не ставятся; только `--with-tags`

## Запуск

```bash
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc00-03-articles.php --dry-run
docker compose exec -T php php /home/zxpress/web/zxpress.ru/public_html/cli/import-subxtc00-03-articles.php --apply
```

```bash
php db/migration/subxtc00_03_articles/apply.php --apply
```
