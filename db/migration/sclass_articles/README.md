# Sinclair Classic articles (AI RU/EN)

Imports markdown bodies, titles, meta and optional tags for press `313` (Sinclair Classic).

Issues already exist (`01`–`10`, `Summer Special`). Source JSON lives in:

- `site/cli/imports/sclass{01..10,summer}_i18n/`
- `db/migration/sclass_articles/i18n/{01..10,summer}/`

```bash
php site/cli/import-sclass-articles.php --dry-run
php site/cli/import-sclass-articles.php --apply --with-tags
```

Issue descriptions: `local/work/sinclair_classic/issue_desc/apply_descriptions.sql`.
